<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\Campaign;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MigrateFromJavaDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:from-java 
                            {--tenant=polanfit-fitness : Slug of the target tenant}
                            {--dry-run : Run in preview mode without making database changes}
                            {--source-host= : Override source MySQL host}
                            {--source-port= : Override source MySQL port}
                            {--source-db= : Override source MySQL database name}
                            {--source-user= : Override source MySQL username}
                            {--source-pass= : Override source MySQL password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Smartly migrate production data from Java MySQL database (smartpass) into Laravel fitcloud-api';

    // ID Remapping lookups
    private array $memberIdMap = [];
    private array $planIdMap = [];
    private array $userIdMap = [];

    // Migration Report Summary
    private array $report = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=================================================");
        $this->info("🚀 FITCLOUD SMART DATA MIGRATION (Java MySQL -> Laravel)");
        $this->info("=================================================");

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn("⚠️  RUNNING IN DRY-RUN (PREVIEW) MODE. No database changes will be written.\n");
        }

        // 1. Resolve Target Tenant
        $tenantSlug = $this->option('tenant');
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (!$tenant) {
            $tenant = Tenant::first();
            if (!$tenant) {
                $tenant = Tenant::create([
                    'name' => 'PolanFit Fitness',
                    'slug' => $tenantSlug ?: 'polanfit-fitness',
                    'domain' => ($tenantSlug ?: 'polanfit-fitness') . '.fitcloud.io',
                    'status' => 'active',
                ]);
            }
        }

        $this->info("🎯 Target Tenant: {$tenant->name} (ID: {$tenant->id}, Slug: {$tenant->slug})");

        // 2. Configure Source Connection if options provided
        if ($this->option('source-host')) {
            Config::set('database.connections.java_mysql.host', $this->option('source-host'));
        }
        if ($this->option('source-port')) {
            Config::set('database.connections.java_mysql.port', $this->option('source-port'));
        }
        if ($this->option('source-db')) {
            Config::set('database.connections.java_mysql.database', $this->option('source-db'));
        }
        if ($this->option('source-user')) {
            Config::set('database.connections.java_mysql.username', $this->option('source-user'));
        }
        if ($this->option('source-pass')) {
            Config::set('database.connections.java_mysql.password', $this->option('source-pass'));
        }

        // Test connection
        try {
            DB::connection('java_mysql')->getPdo();
            $this->info("✅ Successfully connected to Java MySQL database!");
        } catch (\Exception $e) {
            $this->warn("⚠️ Could not establish connection to live Java MySQL database: " . $e->getMessage());
            $this->warn("💡 Will proceed with mock simulation or dry run if connection fails.\n");
        }

        // Start Table Migrations
        $this->migrateTenantUsers($tenant, $isDryRun);
        $this->migrateMembers($tenant, $isDryRun);
        $this->migratePricingPlans($tenant, $isDryRun);
        $this->migrateMemberPlans($tenant, $isDryRun);
        $this->migrateAttendanceLogs($tenant, $isDryRun);
        $this->migrateTransactions($tenant, $isDryRun);
        $this->migrateCampaigns($tenant, $isDryRun);
        $this->migrateGymSettings($tenant, $isDryRun);
        $this->migrateMemberMessages($tenant, $isDryRun);
        $this->migratePaymentTypes($tenant, $isDryRun);
        $this->migrateAccounts($tenant, $isDryRun);

        // Automatically fix & normalize non-bcrypt passwords
        if (!$isDryRun) {
            $this->info("\n🔑 Running password normalization (fix:passwords)...");
            $this->call('fix:passwords');
        }

        // Generate CSV Report
        $this->generateMigrationReport($tenantSlug, $isDryRun);

        $this->info("\n🎉 MIGRATION PROCESS COMPLETED!");
        return 0;
    }

    private function migrateTenantUsers(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n👤 1/11 Migrating Tenant Users (app_user)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $users = DB::connection('java_mysql')->table('app_user')->get();
            $processed = count($users);
            $defaultPasswordHash = Hash::make('password');

            // Load Java app_user_roles and app_user_permissions
            $userRolesMap = DB::connection('java_mysql')->table('app_user_roles')
                ->get()
                ->groupBy('app_user_id')
                ->map(fn($rows) => $rows->pluck('roles')->toArray());

            $userPermissionsMap = DB::connection('java_mysql')->table('app_user_permissions')
                ->get()
                ->groupBy('app_user_id')
                ->map(fn($rows) => $rows->pluck('permission')->toArray());

            foreach ($users as $u) {
                try {
                    $email = strtolower(trim($u->username ?? "user_{$u->id}@fitcloud.local"));
                    if (empty($email)) {
                        $email = "user_{$u->id}@fitcloud.local";
                    }

                    $rolesList = $userRolesMap->get($u->id) ?? [];
                    $permsList = $userPermissionsMap->get($u->id) ?? [];

                    $isSuperAdmin = in_array('ROLE_SUPER_ADMIN', $rolesList) || $u->id == 1 || $email === 'admin@polanfit.com';
                    $isAdmin = in_array('ROLE_ADMIN', $rolesList) || str_contains(strtolower($u->username ?? ''), 'admin');
                    $isManager = in_array('ROLE_MANAGER', $rolesList);
                    $isStaff = in_array('ROLE_STAFF', $rolesList);

                    // Skip pure member accounts from tenant_users table
                    if (!$isSuperAdmin && !$isAdmin && !$isManager && !$isStaff && empty($permsList)) {
                        $skipped++;
                        continue;
                    }

                    if ($isSuperAdmin) {
                        $role = 'owner';
                    } else if ($isAdmin) {
                        $role = 'admin';
                    } else if ($isManager) {
                        $role = 'manager';
                    } else {
                        $role = 'staff';
                    }

                    $existing = TenantUser::whereRaw('LOWER(email) = ?', [$email])->first();

                    if ($existing) {
                        $this->userIdMap[$u->id] = $existing->id;
                        if (!$isDryRun) {
                            $existing->update([
                                'tenant_id' => $tenant->id,
                                'role' => $role,
                                'phone' => $u->phone_number ?? $existing->phone,
                                'name' => $u->full_name ?? $existing->name,
                            ]);
                            setPermissionsTeamId($tenant->id);
                            if (!empty($permsList)) {
                                $existing->syncPermissions($permsList);
                            } else {
                                $existing->syncPermissions(['MANAGE_TRANSACTIONS', 'MANAGE_CHECKINS', 'MANAGE_MEMBERS', 'VIEW_TRANSACTIONS']);
                            }
                        }
                        $created++;
                        continue;
                    }

                    $rawPassword = $u->password ?? null;

                    if (is_string($rawPassword) && !empty($rawPassword)) {
                        $rawPassword = str_replace('{bcrypt}', '', $rawPassword);
                        if (str_starts_with($rawPassword, '$2a$')) {
                            $password = '$2y$' . substr($rawPassword, 4);
                        } else if (str_starts_with($rawPassword, '$2y$') || str_starts_with($rawPassword, '$2b$')) {
                            $password = $rawPassword;
                        } else {
                            $password = Hash::make($rawPassword);
                        }
                    } else {
                        $password = $defaultPasswordHash;
                    }

                    if (!$isDryRun) {
                        $newId = DB::table('tenant_users')->insertGetId([
                            'tenant_id' => $tenant->id,
                            'name' => $u->full_name ?? $u->username,
                            'email' => $email,
                            'phone' => $u->phone_number ?? null,
                            'password' => $password,
                            'role' => $role,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $this->userIdMap[$u->id] = $newId;

                        $tenantUser = TenantUser::find($newId);
                        if ($tenantUser) {
                            setPermissionsTeamId($tenant->id);
                            if (!empty($permsList)) {
                                $tenantUser->syncPermissions($permsList);
                            } else {
                                $tenantUser->syncPermissions(['MANAGE_TRANSACTIONS', 'MANAGE_CHECKINS', 'MANAGE_MEMBERS', 'VIEW_TRANSACTIONS']);
                            }
                        }

                        if ($isSuperAdmin || $isAdmin) {
                            DB::table('users')->updateOrInsert(
                                ['email' => $email],
                                [
                                    'name' => $u->full_name ?? $u->username,
                                    'password' => $password,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }
                    }
                    $created++;
                } catch (\Exception $e) {
                    $errors++;
                }
            }
        } catch (\Exception $e) {
            $this->warn("Notice for app_user connection/fetch: " . $e->getMessage());
        }

        $this->report[] = ['Table' => 'tenant_users', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
        $this->info("   Result: {$created} created, {$skipped} skipped.");
    }

    private function migrateMembers(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n🏋️ 2/11 Migrating Members (member)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $members = DB::connection('java_mysql')
                ->table('member')
                ->leftJoin('app_user', 'member.user_id', '=', 'app_user.id')
                ->select('member.*', 'app_user.username as app_email', 'app_user.phone_number as app_phone')
                ->get();

            $processed = count($members);

            foreach ($members as $m) {
                $uuid = $m->uid ?? (string) Str::uuid();
                $existing = Member::where('tenant_id', $tenant->id)->where('uuid', $uuid)->first();

                if ($existing) {
                    $this->memberIdMap[$m->id] = $existing->id;
                    $skipped++;
                    continue;
                }

                $gender = strtolower($m->gender ?? 'male');
                if ($gender === 'm') $gender = 'male';
                if ($gender === 'f') $gender = 'female';
                if (!in_array($gender, ['male', 'female', 'other'])) $gender = 'male';

                if (!$isDryRun) {
                    $newMember = Member::create([
                        'tenant_id' => $tenant->id,
                        'uuid' => $uuid,
                        'first_name' => $m->first_name ?? 'Member',
                        'last_name' => $m->last_name ?? 'User',
                        'email' => $m->app_email ?? ($m->email ?? null),
                        'phone' => $m->app_phone ?? ($m->phone ?? null),
                        'gender' => $gender,
                        'date_of_birth' => $m->dob ?? null,
                        'address' => $m->address ?? null,
                        'photo_url' => $m->profile_picture_url ?? null,
                        'qr_code' => $m->qr_code ?? null,
                        'fingerprint_template' => $m->biometric_hash ?? null,
                        'biometric_hash' => $m->biometric_hash ?? null,
                        'blood_group' => $m->blood_group ?? null,
                        'genotype' => $m->genotype ?? null,
                        'emergency_contact_name' => $m->contact_name ?? null,
                        'emergency_contact_phone' => $m->contact_phone_number ?? null,
                        'registration_source' => $m->registration_source ?? null,
                        'status' => 'active',
                        'created_at' => $m->registration_date ? Carbon::parse($m->registration_date) : now(),
                        'updated_at' => isset($m->updated_at) && $m->updated_at ? Carbon::parse($m->updated_at) : ($m->registration_date ? Carbon::parse($m->registration_date) : now()),
                    ]);
                    $this->memberIdMap[$m->id] = $newMember->id;
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for members migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'members', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
        $this->info("   Result: {$created} created, {$skipped} skipped.");
    }

    private function migratePricingPlans(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n🏷️ 3/11 Migrating Pricing Plans (pricing_plans)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $plans = DB::connection('java_mysql')->table('pricing_plans')->get();
            $processed = count($plans);

            foreach ($plans as $p) {
                $name = $p->name ?? 'Standard Plan';
                $planName = $p->plan_name ?? null;
                $category = $p->category ?? 'SUBSCRIPTION';

                // Skip FREE / SYSTEM-FR dummy system plans
                if (in_array(strtoupper($name), ['FREE', 'SYSTEM-FR', 'SYSTEM-FREE']) || in_array(strtoupper($planName ?? ''), ['FREE', 'SYSTEM-FR', 'SYSTEM-FREE'])) {
                    $skipped++;
                    continue;
                }

                $existing = PricingPlan::where('tenant_id', $tenant->id)->where('name', $name)->where('category', $category)->first();

                if ($existing) {
                    $this->planIdMap[$p->id] = $existing->id;
                    $skipped++;
                    continue;
                }

                $price = $p->total_fee ?? ($p->price ?? 25000);

                if (!$isDryRun) {
                    $newPlan = PricingPlan::create([
                        'tenant_id' => $tenant->id,
                        'name' => $name,
                        'plan_name' => $planName,
                        'category' => $category,
                        'description' => $p->sub_text ?? $p->category ?? 'Gym Plan',
                        'price' => $price,
                        'duration_days' => $p->duration_days ?? 30,
                        'is_active' => $p->active ?? true,
                    ]);
                    $this->planIdMap[$p->id] = $newPlan->id;
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for pricing_plans migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'pricing_plans', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
        $this->info("   Result: {$created} created, {$skipped} skipped.");
    }

    private function migrateMemberPlans(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n📑 4/11 Migrating Member Subscriptions (member_plans)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $plans = DB::connection('java_mysql')->table('member_plans')->get();
            $processed = count($plans);

            foreach ($plans as $mp) {
                $memberId = $this->memberIdMap[$mp->member_id] ?? null;
                $planId = $this->planIdMap[$mp->pricing_plan_id] ?? null;

                if (!$memberId || !$planId) {
                    $skipped++;
                    continue;
                }

                $status = strtolower($mp->status ?? 'active');
                if (!in_array($status, ['active', 'expired', 'cancelled'])) $status = 'active';

                if (!$isDryRun) {
                    MemberPlan::create([
                        'tenant_id' => $tenant->id,
                        'member_id' => $memberId,
                        'pricing_plan_id' => $planId,
                        'starts_at' => $mp->start_date ? Carbon::parse($mp->start_date) : now(),
                        'expires_at' => $mp->end_date ? Carbon::parse($mp->end_date) : now()->addDays(30),
                        'status' => $status,
                    ]);
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for member_plans migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'member_plans', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
        $this->info("   Result: {$created} created, {$skipped} skipped.");
    }

    private function migrateAttendanceLogs(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n🚪 5/11 Migrating Attendance Logs (attendance_log)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $logs = DB::connection('java_mysql')->table('attendance_log')->get();
            $processed = count($logs);

            foreach ($logs as $l) {
                $memberId = $this->memberIdMap[$l->member_id] ?? null;
                if (!$memberId) {
                    $skipped++;
                    continue;
                }

                $method = strtolower($l->method ?? 'qr_code');
                if (!in_array($method, ['manual', 'qr_code', 'fingerprint', 'geofence'])) $method = 'qr_code';

                if (!$isDryRun) {
                    AttendanceLog::create([
                        'tenant_id' => $tenant->id,
                        'member_id' => $memberId,
                        'check_in_time' => $l->timestamp ? Carbon::parse($l->timestamp) : now(),
                        'check_out_time' => $l->expires_at ? Carbon::parse($l->expires_at) : null,
                        'method' => $method,
                        'is_active_session' => strtoupper($l->status ?? '') === 'GRANTED' && ($l->expires_at ? Carbon::parse($l->expires_at)->isFuture() : false),
                    ]);
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for attendance_log migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'attendance_logs', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
        $this->info("   Result: {$created} created, {$skipped} skipped.");
    }

    private function migrateTransactions(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n💳 6/11 Migrating Transactions (transaction)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $txs = DB::connection('java_mysql')->table('transaction')->get();
            $processed = count($txs);

            foreach ($txs as $t) {
                $memberId = $this->memberIdMap[$t->member_id] ?? null;
                $planId = $this->planIdMap[$t->pricing_plan_id] ?? null;

                if (!$memberId) {
                    $skipped++;
                    continue;
                }

                $status = strtolower($t->status ?? 'successful');
                if ($status === 'success') $status = 'successful';
                if (!in_array($status, ['pending', 'successful', 'failed'])) $status = 'successful';

                $method = strtolower($t->payment_method ?? 'paystack');

                if (!$isDryRun) {
                    Transaction::create([
                        'uuid' => $t->uid ?? (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'member_id' => $memberId,
                        'pricing_plan_id' => $planId,
                        'amount' => $t->amount ?? 0,
                        'platform_fee' => 0.00,
                        'net_amount' => $t->amount ?? 0,
                        'currency' => 'NGN',
                        'payment_method' => $method,
                        'reference' => $t->reference ?? ($t->paystack_reference ?? 'REF-' . Str::random(8)),
                        'status' => $status,
                        'created_at' => $t->transaction_date ? Carbon::parse($t->transaction_date) : now(),
                    ]);
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for transaction migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'transactions', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
        $this->info("   Result: {$created} created, {$skipped} skipped.");
    }

    private function migrateCampaigns(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n🏆 7/11 Migrating Campaigns (campaigns)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $camps = DB::connection('java_mysql')->table('campaigns')->get();
            $processed = count($camps);

            foreach ($camps as $c) {
                if (!$isDryRun) {
                    Campaign::create([
                        'tenant_id' => $tenant->id,
                        'name' => $c->title ?? 'Campaign',
                        'description' => 'Migrated from Java database',
                        'start_date' => $c->start_date ? Carbon::parse($c->start_date) : now(),
                        'end_date' => $c->end_date ? Carbon::parse($c->end_date) : now()->addMonth(),
                        'points_per_checkin' => $c->points_per_checkin ?? 10,
                        'is_active' => $c->active ?? true,
                    ]);
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for campaigns migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'campaigns', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
        $this->info("   Result: {$created} created, {$skipped} skipped.");
    }

    private function migrateGymSettings(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n⚙️ 8/11 Migrating Gym Settings (gym_settings)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $settings = DB::connection('java_mysql')->table('gym_settings')->get();
            $processed = count($settings);

            foreach ($settings as $s) {
                if (!$isDryRun) {
                    DB::table('gym_settings')->insert([
                        'tenant_id' => $tenant->id,
                        'setting_key' => $s->setting_key,
                        'setting_value' => $s->setting_value ?? null,
                        'category' => $s->category ?? 'GENERAL',
                        'label' => $s->label ?? $s->setting_key,
                        'description' => $s->description ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for gym_settings migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'gym_settings', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
    }

    private function migrateMemberMessages(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n📬 9/11 Migrating Member Messages (member_messages)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $msgs = DB::connection('java_mysql')->table('member_messages')->get();
            $processed = count($msgs);

            foreach ($msgs as $m) {
                $memberId = $this->memberIdMap[$m->member_id] ?? null;
                if (!$memberId) {
                    $skipped++;
                    continue;
                }

                if (!$isDryRun) {
                    DB::table('member_messages')->insert([
                        'uuid' => $m->uid ?? (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'member_id' => $memberId,
                        'subject' => $m->subject ?? 'Feedback',
                        'message' => $m->message ?? '',
                        'type' => $m->type ?? 'SUGGESTION',
                        'date_sent' => $m->date_sent ? Carbon::parse($m->date_sent) : now(),
                        'is_read' => $m->is_read ?? false,
                        'is_starred' => $m->is_starred ?? false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for member_messages migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'member_messages', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
    }

    private function migratePaymentTypes(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n🏷️ 10/11 Migrating Payment Types (payment_types)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $pts = DB::connection('java_mysql')->table('payment_types')->get();
            $processed = count($pts);

            foreach ($pts as $pt) {
                if (!$isDryRun) {
                    DB::table('payment_types')->insert([
                        'uuid' => $pt->uid ?? (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'name' => $pt->name ?? 'Payment Type',
                        'code' => $pt->code ?? ('PT_' . Str::random(5)),
                        'default_amount' => $pt->default_amount ?? 0,
                        'is_active' => $pt->is_active ?? true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for payment_types migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'payment_types', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
    }

    private function migrateAccounts(Tenant $tenant, bool $isDryRun)
    {
        $this->info("\n🏦 11/11 Migrating Bank Accounts (accounts)...");
        $processed = 0; $created = 0; $skipped = 0; $errors = 0;

        try {
            $accs = DB::connection('java_mysql')->table('accounts')->get();
            $processed = count($accs);

            foreach ($accs as $a) {
                if (!$isDryRun) {
                    DB::table('accounts')->insert([
                        'uuid' => $a->uid ?? (string) Str::uuid(),
                        'tenant_id' => $tenant->id,
                        'bank_name' => $a->bank_name ?? 'Bank',
                        'account_number' => $a->account_number ?? '0000000000',
                        'account_type' => $a->account_type ?? 'Current',
                        'is_active' => $a->is_active ?? true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $created++;
            }
        } catch (\Exception $e) {
            $this->warn("Notice for accounts migration: " . $e->getMessage());
            $errors++;
        }

        $this->report[] = ['Table' => 'accounts', 'Processed' => $processed, 'Created' => $created, 'Skipped' => $skipped, 'Errors' => $errors];
    }

    private function generateMigrationReport(string $tenantSlug, bool $isDryRun)
    {
        $this->info("\n=================================================");
        $this->info("📊 DATA MIGRATION SUMMARY REPORT");
        $this->info("=================================================");

        $this->table(['Table', 'Processed', 'Created', 'Skipped', 'Errors'], $this->report);

        // Export CSV report to storage
        $timestamp = date('Ymd_His');
        $filename = "migration_report_{$tenantSlug}_{$timestamp}.csv";
        $path = storage_path("app/{$filename}");

        $file = fopen($path, 'w');
        fputcsv($file, ['Table', 'Processed', 'Created', 'Skipped', 'Errors']);
        foreach ($this->report as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        $this->info("📄 Detailed CSV report saved to: {$path}");
    }
}

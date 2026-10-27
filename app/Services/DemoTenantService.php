<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantLifecycleLog;
use App\Models\TenantUser;
use App\Models\ThemeSetting;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoTenantService
{
    private const FEATURES = [
        'member_management',
        'membership_plans',
        'check_ins',
        'payments',
        'dashboard',
        'staff_attendance',
        'campaigns_leaderboard',
        'email_blast',
        'biometric_access',
        'qr_code_access',
        'audit_logs',
        'automated_backups',
        'health_profiles',
    ];

    public function create(array $data, ?TenantUser $createdBy = null): array
    {
        $days = max(1, min(365, (int) ($data['duration_days'] ?? config('fitcloud.demo.days', 30))));
        $graceDays = max(0, min(90, (int) config('fitcloud.demo.grace_days', 7)));

        return DB::transaction(function () use ($data, $createdBy, $days, $graceDays) {
            $slug = $this->uniqueSlug((string) $data['gym_name']);
            $expiresAt = now()->addDays($days);

            $tenant = Tenant::create([
                'name' => $data['gym_name'],
                'slug' => $slug,
                'timezone' => $data['timezone'] ?? 'Africa/Lagos',
                'currency' => $data['currency'] ?? 'NGN',
                'pricing_path' => 'free_tier',
                'plan_tier' => 'starter',
                'status' => 'trialing',
                'is_demo' => true,
                'demo_status' => 'active',
                'demo_expires_at' => $expiresAt,
                'demo_purge_after' => $expiresAt->copy()->addDays($graceDays),
                'demo_notes' => $data['notes'] ?? null,
                'demo_created_by' => $createdBy?->id,
                'setup_completed_at' => now(),
            ]);

            $owner = TenantUser::create([
                'tenant_id' => $tenant->id,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'phone' => $data['owner_phone'] ?? null,
                'password' => Hash::make(Str::random(32)),
                'role' => 'owner',
            ]);

            foreach (self::FEATURES as $featureKey) {
                TenantFeature::create([
                    'tenant_id' => $tenant->id,
                    'feature_key' => $featureKey,
                    'enabled' => true,
                ]);
            }

            ThemeSetting::create([
                'tenant_id' => $tenant->id,
                'primary_color' => $data['primary_color'] ?? '#fa6a2e',
                'accent_color' => $data['accent_color'] ?? '#1e293b',
                'bg_color' => '#0f0f11',
                'text_color' => '#ffffff',
                'font_family' => 'Inter',
                'dark_mode' => true,
            ]);

            $this->seedSampleData($tenant);

            $token = $this->issueAccessToken($owner);

            $this->log($tenant, 'created', $owner, $createdBy, [
                'days' => $days,
                'grace_days' => $graceDays,
            ]);

            return [
                'tenant' => $tenant->fresh(['theme', 'users']),
                'owner' => $owner,
                'token' => $token,
                'access_url' => $this->accessUrl($tenant, $token),
                'member_portal_url' => $tenant->memberPortalUrl(),
                'expires_at' => $tenant->demo_expires_at,
                'purge_after' => $tenant->demo_purge_after,
            ];
        });
    }

    public function extend(Tenant $tenant, int $days, ?TenantUser $actor = null): Tenant
    {
        if (! $tenant->is_demo) {
            throw new RuntimeException('Only demo tenants can be extended.');
        }

        $days = max(1, min(365, $days));
        $base = $tenant->demo_expires_at && $tenant->demo_expires_at->isFuture()
            ? $tenant->demo_expires_at
            : now();

        $expiresAt = $base->copy()->addDays($days);
        $graceDays = max(0, min(90, (int) config('fitcloud.demo.grace_days', 7)));

        $tenant->update([
            'demo_status' => 'active',
            'demo_expires_at' => $expiresAt,
            'demo_purge_after' => $expiresAt->copy()->addDays($graceDays),
        ]);

        $this->log($tenant, 'extended', null, $actor, ['days' => $days]);

        return $tenant->fresh(['theme', 'users']);
    }

    public function convert(Tenant $tenant, ?TenantUser $actor = null): Tenant
    {
        if (! $tenant->is_demo) {
            throw new RuntimeException('This tenant is not a demo.');
        }

        $tenant->update([
            'is_demo' => false,
            'demo_status' => 'converted',
            'demo_converted_at' => now(),
            'demo_expires_at' => null,
            'demo_purge_after' => null,
            'status' => 'active',
        ]);

        $this->log($tenant, 'converted', null, $actor);

        return $tenant->fresh(['theme', 'users']);
    }

    public function accessUrl(Tenant $tenant, string $token): string
    {
        $adminBase = explode('?', $tenant->adminConsoleUrl())[0];

        return $adminBase.'#token='.urlencode($token).'&tenant='.urlencode($tenant->slug);
    }

    public function issueAccessToken(TenantUser $owner): string
    {
        return $owner->createToken('demo-access', ['staff'])->plainTextToken;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'demo-gym';
        $slug = $base.'-demo';
        $counter = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-demo-'.$counter++;
        }

        return $slug;
    }

    private function seedSampleData(Tenant $tenant): void
    {
        $plans = [
            PricingPlan::create([
                'tenant_id' => $tenant->id,
                'name' => 'Monthly Standard',
                'category' => 'SUBSCRIPTION',
                'description' => 'Unlimited gym floor access for 30 days.',
                'price' => 35000,
                'duration_days' => 30,
                'is_active' => true,
            ]),
            PricingPlan::create([
                'tenant_id' => $tenant->id,
                'name' => 'Quarterly VIP',
                'category' => 'EXCLUSIVE',
                'description' => 'Quarterly access with recovery suite privileges.',
                'price' => 95000,
                'duration_days' => 90,
                'is_active' => true,
            ]),
            PricingPlan::create([
                'tenant_id' => $tenant->id,
                'name' => 'Annual Elite',
                'category' => 'EXCLUSIVE',
                'description' => 'Annual all-access membership with priority booking.',
                'price' => 320000,
                'duration_days' => 365,
                'is_active' => true,
            ]),
        ];

        $people = [
            ['Ada', 'Okafor', 'female'],
            ['Tunde', 'Bello', 'male'],
            ['Maya', 'Johnson', 'female'],
            ['Chidi', 'Eze', 'male'],
            ['Zara', 'Musa', 'female'],
            ['Femi', 'Adeyemi', 'male'],
            ['Nneka', 'Obi', 'female'],
            ['Samuel', 'James', 'male'],
        ];

        foreach ($people as $index => [$firstName, $lastName, $gender]) {
            $member = Member::create([
                'tenant_id' => $tenant->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => sprintf('demo.member%02d@example.invalid', $index + 1),
                'phone' => sprintf('080112233%02d', $index + 1),
                'password' => Hash::make(Str::random(32)),
                'gender' => $gender,
                'status' => $index < 6 ? 'active' : 'inactive',
                'registration_source' => 'demo',
                'registration_status' => 'completed',
            ]);

            $plan = $plans[$index % count($plans)];
            $isExpired = $index >= 6;
            $startsAt = $isExpired ? now()->subDays(45) : now()->subDays(10 + $index);

            MemberPlan::create([
                'tenant_id' => $tenant->id,
                'member_id' => $member->id,
                'pricing_plan_id' => $plan->id,
                'starts_at' => $startsAt,
                'expires_at' => $isExpired ? now()->subDays(5) : now()->addDays($plan->duration_days - 10),
                'status' => $isExpired ? 'expired' : 'active',
            ]);

            for ($checkIn = 0; $checkIn < 4; $checkIn++) {
                AttendanceLog::create([
                    'tenant_id' => $tenant->id,
                    'member_id' => $member->id,
                    'check_in_time' => now()->subDays(($index + $checkIn) % 7)->setTime(7 + (($index + $checkIn) % 12), 15),
                    'check_out_time' => now()->subDays(($index + $checkIn) % 7)->setTime(8 + (($index + $checkIn) % 12), 5),
                    'method' => $checkIn % 2 === 0 ? 'qr_code' : 'fingerprint',
                    'is_active_session' => false,
                ]);
            }

            if (! $isExpired) {
                Transaction::create([
                    'tenant_id' => $tenant->id,
                    'member_id' => $member->id,
                    'pricing_plan_id' => $plan->id,
                    'amount' => $plan->price,
                    'platform_fee' => round((float) $plan->price * 0.01, 2),
                    'net_amount' => round((float) $plan->price * 0.99, 2),
                    'currency' => $tenant->currency,
                    'payment_method' => 'demo',
                    'reference' => 'DEMO-'.strtoupper(Str::random(12)),
                    'status' => 'successful',
                    'notes' => 'Sample demo transaction — no real charge.',
                ]);
            }
        }

        TenantFeature::where('tenant_id', $tenant->id)
            ->where('feature_key', 'payments')
            ->update(['enabled' => true]);
    }

    private function log(
        Tenant $tenant,
        string $action,
        ?TenantUser $owner = null,
        ?TenantUser $actor = null,
        array $metadata = []
    ): void {
        $owner = $owner ?: $tenant->users()->where('role', 'owner')->first();

        TenantLifecycleLog::create([
            'tenant_uuid' => $tenant->uuid,
            'gym_name' => $tenant->name,
            'slug' => $tenant->slug,
            'owner_name' => $owner?->name,
            'owner_email' => $owner?->email,
            'action' => $action,
            'notes' => $tenant->demo_notes,
            'metadata' => array_merge($metadata, [
                'demo_expires_at' => $tenant->demo_expires_at?->toIso8601String(),
                'demo_purge_after' => $tenant->demo_purge_after?->toIso8601String(),
                'actor_id' => $actor?->id,
            ]),
        ]);
    }
}

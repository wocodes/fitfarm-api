<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\TenantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the minimum non-sensitive data required by the application.
     *
     * Production administrators must be bootstrapped from environment
     * variables. This seeder never creates known demo accounts in production.
     */
    public function run(): void
    {
        $permissions = [
            ['key' => 'MANAGE_ADMINS', 'label' => 'Manage Admins & Staff', 'category' => 'Administration'],
            ['key' => 'MANAGE_MEMBERS', 'label' => 'Manage Members', 'category' => 'Member Management'],
            ['key' => 'VIEW_TRANSACTIONS', 'label' => 'View Financial Transactions', 'category' => 'Finance'],
            ['key' => 'MANAGE_TRANSACTIONS', 'label' => 'Manage Transactions', 'category' => 'Finance'],
            ['key' => 'MANAGE_PRICING', 'label' => 'Manage Pricing & Plans', 'category' => 'Plans & Subscriptions'],
            ['key' => 'MANAGE_CHECKINS', 'label' => 'Manage Check-in Kiosk', 'category' => 'Operations'],
            ['key' => 'MANAGE_ACCOUNTS', 'label' => 'Manage Accounts & Audits', 'category' => 'Finance & Compliance'],
            ['key' => 'MANAGE_SETTINGS', 'label' => 'Manage Gym Settings', 'category' => 'System Settings'],
            ['key' => 'PAUSE_MEMBERS', 'label' => 'Pause/Unpause Subscriptions', 'category' => 'Plans & Subscriptions'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['key'], 'guard_name' => 'sanctum'],
                [
                    'label' => $permission['label'],
                    'description' => $permission['label'],
                    'category' => $permission['category'],
                ]
            );
        }

        $this->seedSuperAdmin();
        $this->seedDevelopmentLicense();
    }

    /**
     * Create the platform administrator only when explicit credentials are supplied.
     */
    private function seedSuperAdmin(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn(
                'SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD are not set; no platform administrator was created.'
            );

            return;
        }

        TenantUser::updateOrCreate(
            ['email' => strtolower($email)],
            [
                'tenant_id' => null,
                'name' => env('SUPER_ADMIN_NAME', 'Platform Admin'),
                'password' => Hash::make($password),
                'role' => 'ROLE_SUPER_ADMIN',
            ]
        );

        $this->command?->info("Platform administrator ensured for {$email}.");
    }

    /**
     * The demo license is only available outside production.
     */
    private function seedDevelopmentLicense(): void
    {
        if (app()->environment('production')) {
            return;
        }

        License::updateOrCreate(
            ['key' => 'FC-DEMO-2026-KEYS'],
            [
                'plan_tier' => 'pro',
                'allowed_features' => [
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
                ],
                'max_members' => 500,
                'is_active' => true,
            ]
        );
    }
}

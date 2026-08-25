<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\TenantUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Platform Super Admin
        TenantUser::create([
            'name' => 'FitCloud Admin',
            'email' => 'superadmin@fitcloud.io',
            'password' => Hash::make('Secret123!'),
            'role' => 'super_admin',
        ]);

        // 2. Create Demo License Key for Setup Wizard testing
        License::create([
            'key' => 'FC-DEMO-2026-KEYS',
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
        ]);
    }
}

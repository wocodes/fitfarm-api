<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\Permission;
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
        // 1. Seed System Master Permissions Table
        $permissions = [
            [
                'key' => 'MANAGE_ADMINS',
                'label' => 'Manage Admins & Staff',
                'description' => 'Create, update, and manage admin users & permissions',
                'category' => 'Administration',
            ],
            [
                'key' => 'MANAGE_MEMBERS',
                'label' => 'Manage Members',
                'description' => 'Register, edit, view, and manage gym members',
                'category' => 'Member Management',
            ],
            [
                'key' => 'VIEW_TRANSACTIONS',
                'label' => 'View Financial Transactions',
                'description' => 'Access payment records and revenue reports',
                'category' => 'Finance',
            ],
            [
                'key' => 'MANAGE_TRANSACTIONS',
                'label' => 'Manage Transactions',
                'description' => 'Approve, reject, or refund financial payments',
                'category' => 'Finance',
            ],
            [
                'key' => 'MANAGE_PRICING',
                'label' => 'Manage Pricing & Plans',
                'description' => 'Create and modify membership plans & pricing',
                'category' => 'Plans & Subscriptions',
            ],
            [
                'key' => 'MANAGE_CHECKINS',
                'label' => 'Manage Check-in Kiosk',
                'description' => 'Access check-in terminal and view attendance logs',
                'category' => 'Operations',
            ],
            [
                'key' => 'MANAGE_ACCOUNTS',
                'label' => 'Manage Accounts & Audits',
                'description' => 'Configure gym bank accounts and inspect audit logs',
                'category' => 'Finance & Compliance',
            ],
            [
                'key' => 'MANAGE_SETTINGS',
                'label' => 'Manage Gym Settings',
                'description' => 'Configure hardware, QR scanners, and gym info',
                'category' => 'System Settings',
            ],
            [
                'key' => 'PAUSE_MEMBERS',
                'label' => 'Pause/Unpause Subscriptions',
                'description' => 'Freeze or resume active member subscriptions',
                'category' => 'Plans & Subscriptions',
            ],
        ];

        foreach ($permissions as $perm) {
            \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => $perm['key'], 'guard_name' => 'sanctum'],
                [
                    'label' => $perm['label'],
                    'description' => $perm['description'],
                    'category' => $perm['category'],
                ]
            );
        }

        // 2. Ensure default tenant exists
        $tenant = \App\Models\Tenant::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'PolanFit Fitness',
                'slug' => 'polanfit-fitness',
                'status' => 'active',
            ]
        );

        // 3. Create Legacy Migrated Admins (Extracted from MySQL smartpass database)
        $legacyAdmins = [
            [
                'tenant_id' => 1,
                'name' => 'Super Admin',
                'email' => 'admin@polanfit.com',
                'phone' => '07011223341',
                'gender' => 'Male',
                'password' => Hash::make('admin123@'),
                'role' => 'owner',
                'permissions' => array_column($permissions, 'key'),
            ],
            [
                'tenant_id' => 1,
                'name' => 'Alpha Engineer',
                'email' => 'alpha_engineer@polanfit.com',
                'phone' => '08010000000',
                'gender' => 'Male',
                'password' => Hash::make('AlphaEngineer2026!'),
                'role' => 'owner',
                'permissions' => array_column($permissions, 'key'),
            ],
            [
                'tenant_id' => 1,
                'name' => 'Kelechi Sunday',
                'email' => 'kelechisunday20@gmail.com',
                'phone' => '08151348800',
                'gender' => 'Male',
                'password' => Hash::make('Password123!'),
                'role' => 'admin',
                'permissions' => ['MANAGE_TRANSACTIONS', 'MANAGE_CHECKINS', 'MANAGE_MEMBERS', 'VIEW_TRANSACTIONS'],
            ],
            [
                'tenant_id' => 1,
                'name' => 'William Kamal',
                'email' => 'kamal.misc@gmail.com',
                'phone' => '07068535208',
                'gender' => 'Male',
                'password' => Hash::make('Password123!'),
                'role' => 'admin',
                'permissions' => ['MANAGE_TRANSACTIONS', 'MANAGE_CHECKINS', 'MANAGE_MEMBERS', 'VIEW_TRANSACTIONS'],
            ],
            [
                'tenant_id' => 1,
                'name' => 'Onome Ataro',
                'email' => 'ataro.maro@gmail.com',
                'phone' => '08131623907',
                'gender' => 'Female',
                'password' => Hash::make('Password123!'),
                'role' => 'admin',
                'permissions' => ['MANAGE_TRANSACTIONS', 'MANAGE_CHECKINS', 'MANAGE_MEMBERS', 'VIEW_TRANSACTIONS'],
            ],
            [
                'tenant_id' => 1,
                'name' => 'Foreman Admin',
                'email' => 'foreman@gmail.com',
                'phone' => '07011223341',
                'gender' => 'Male',
                'password' => Hash::make('Password123!'),
                'role' => 'admin',
                'permissions' => ['MANAGE_CHECKINS', 'MANAGE_MEMBERS', 'VIEW_TRANSACTIONS'],
            ],
            [
                'tenant_id' => 1,
                'name' => 'Janet Peter',
                'email' => 'adedojajanet8@gmail.com',
                'phone' => '09069986117',
                'gender' => 'Female',
                'password' => Hash::make('Password123!'),
                'role' => 'admin',
                'permissions' => ['MANAGE_TRANSACTIONS', 'MANAGE_CHECKINS', 'MANAGE_MEMBERS', 'VIEW_TRANSACTIONS'],
            ],
        ];

        foreach ($legacyAdmins as $adminData) {
            $perms = $adminData['permissions'];
            unset($adminData['permissions']);

            $user = TenantUser::updateOrCreate(
                ['email' => strtolower($adminData['email'])],
                $adminData
            );

            setPermissionsTeamId($user->tenant_id ?? 1);

            // Assign Spatie Role & Permissions
            $role = \Spatie\Permission\Models\Role::firstOrCreate([
                'name' => $adminData['role'],
                'guard_name' => 'sanctum',
                'tenant_id' => $user->tenant_id ?? 1,
            ]);

            $user->syncPermissions($perms);
            $user->assignRole($role);
        }

        // 3. Create Platform Super Admin
        $superAdminUser = TenantUser::updateOrCreate(
            ['email' => 'superadmin@fitcloud.io'],
            [
                'name' => 'FitCloud Admin',
                'password' => Hash::make('Secret123!'),
                'role' => 'ROLE_SUPER_ADMIN',
            ]
        );
        setPermissionsTeamId(1);
        $superAdminRole = \Spatie\Permission\Models\Role::firstOrCreate([
            'name' => 'ROLE_SUPER_ADMIN',
            'guard_name' => 'sanctum',
            'tenant_id' => 1,
        ]);
        $superAdminUser->syncPermissions(array_column($permissions, 'key'));
        $superAdminUser->assignRole($superAdminRole);

        // 4. Create Demo License Key for Setup Wizard testing
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

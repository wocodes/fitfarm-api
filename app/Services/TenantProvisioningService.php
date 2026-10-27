<?php

namespace App\Services;

use App\Models\License;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantUser;
use App\Models\ThemeSetting;
use App\Services\PlatformSettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TenantProvisioningService
{
    /**
     * Provision a brand-new gym tenant for an authenticated owner.
     */
    public function provisionTenant(array $data, TenantUser $owner): array
    {
        return DB::transaction(function () use ($data, $owner) {
            $owner = TenantUser::whereKey($owner->id)->lockForUpdate()->firstOrFail();

            if ($owner->tenant_id) {
                throw new RuntimeException('This account is already attached to a gym.');
            }

            $slug = Str::slug($data['gym_name']);

            // Ensure unique slug
            $originalSlug = $slug;
            $count = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $originalSlug.'-'.$count++;
            }

            // 1. Create Tenant
            $tenant = Tenant::create([
                'name' => $data['gym_name'],
                'slug' => $slug,
                'logo_url' => $data['logo_url'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
                'currency' => $data['currency'] ?? 'USD',
                'pricing_path' => $data['pricing_path'] ?? 'free_tier',
                'plan_tier' => $data['plan_tier'] ?? 'starter',
                'status' => 'trialing',
                'trial_ends_at' => Carbon::now()->addDays(app(PlatformSettingsService::class)->trialDays()),
                'setup_completed_at' => Carbon::now(),
            ]);

            // 2. Bind License
            if (! empty($data['license_key'])) {
                $license = License::where('key', $data['license_key'])->first();
                if ($license) {
                    $license->update([
                        'tenant_id' => $tenant->id,
                        'activated_at' => Carbon::now(),
                    ]);
                }
            }

            // 3. Attach the already-authenticated owner without resetting their password.
            $owner->update([
                'tenant_id' => $tenant->id,
                'name' => $data['admin_name'],
                'role' => 'owner',
            ]);

            // 4. Provision Selected Features
            $featuresList = [
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

            $enabledFeatures = $data['features'] ?? $featuresList;

            foreach ($featuresList as $featureKey) {
                TenantFeature::create([
                    'tenant_id' => $tenant->id,
                    'feature_key' => $featureKey,
                    'enabled' => in_array($featureKey, $enabledFeatures),
                ]);
            }

            // 5. Provision Theme Settings
            ThemeSetting::create([
                'tenant_id' => $tenant->id,
                'primary_color' => $data['primary_color'] ?? '#fa6a2e',
                'accent_color' => $data['accent_color'] ?? '#1e293b',
                'bg_color' => $data['bg_color'] ?? '#0f0f11',
                'text_color' => $data['text_color'] ?? '#ffffff',
                'font_family' => $data['font_family'] ?? 'Inter',
                'dark_mode' => $data['dark_mode'] ?? true,
                'logo_url' => $data['logo_url'] ?? null,
            ]);

            // 6. Generate Sanctum Auth Token for immediate login
            $token = $owner->createToken('auth-token', ['staff'])->plainTextToken;

            return [
                'tenant' => $tenant,
                'owner' => $owner,
                'token' => $token,
                'urls' => [
                    'member_app' => $tenant->memberPortalUrl(),
                    'admin_app' => $tenant->adminConsoleUrl(),
                ],
            ];
        });
    }
}

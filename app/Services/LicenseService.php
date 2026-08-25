<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Str;

class LicenseService
{
    /**
     * Generate a new unique license key.
     */
    public function generateLicense(string $planTier = 'starter', int $maxMembers = 100, ?array $allowedFeatures = null): License
    {
        $key = 'FC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));

        $defaultFeatures = [
            'member_management',
            'membership_plans',
            'check_ins',
            'payments',
            'dashboard',
            'staff_attendance',
            'campaigns_leaderboard',
            'qr_code_access',
            'audit_logs',
        ];

        return License::create([
            'key' => $key,
            'plan_tier' => $planTier,
            'allowed_features' => $allowedFeatures ?? $defaultFeatures,
            'max_members' => $maxMembers,
            'is_active' => true,
        ]);
    }

    /**
     * Verify a license key during setup.
     */
    public function verifyKey(string $key): array
    {
        $license = License::where('key', trim($key))->first();

        if (!$license) {
            return [
                'valid' => false,
                'message' => 'License key not found. Please check and try again.',
            ];
        }

        if (!$license->is_active) {
            return [
                'valid' => false,
                'message' => 'This license key has been deactivated or disabled.',
            ];
        }

        if ($license->tenant_id) {
            return [
                'valid' => false,
                'message' => 'This license key has already been assigned to another gym.',
            ];
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            return [
                'valid' => false,
                'message' => 'This license key has expired.',
            ];
        }

        return [
            'valid' => true,
            'message' => 'License key verified successfully.',
            'license' => $license,
        ];
    }
}

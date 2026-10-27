<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Str;

class LicenseService
{
    /**
     * Generate a new unique license key (defaults to the configured trial period).
     */
    public function generateLicense(
        string $planTier = 'starter',
        int $maxMembers = 100,
        ?array $allowedFeatures = null,
        string $mode = 'trial',
        ?string $userEmail = null,
        ?int $trialDays = 90
    ): License {
        $key = 'FC-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

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
            'user_email' => $userEmail,
            'mode' => $mode,
            'plan_tier' => $planTier,
            'allowed_features' => $allowedFeatures ?? $defaultFeatures,
            'max_members' => $maxMembers,
            'is_active' => true,
            'expires_at' => $trialDays ? now()->addDays($trialDays) : null,
        ]);
    }

    /**
     * Verify a license key during setup.
     */
    public function verifyKey(string $key): array
    {
        $license = License::where('key', trim($key))->first();

        if (! $license) {
            return [
                'valid' => false,
                'message' => 'License key not found. Please check and try again.',
            ];
        }

        if (! $license->is_active) {
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
                'message' => 'This trial/license key has expired on '.$license->expires_at->toFormattedDateString().'. Please renew to switch to Full mode.',
            ];
        }

        return [
            'valid' => true,
            'message' => 'License key verified successfully ('.strtoupper($license->mode).' mode).',
            'license' => $license,
        ];
    }

    /**
     * Renew an existing license and switch mode (e.g. trial -> full).
     */
    public function renewLicense(string $key, string $targetMode = 'full', int $additionalDays = 365): array
    {
        $license = License::where('key', trim($key))->first();

        if (! $license) {
            return [
                'success' => false,
                'message' => 'License key not found.',
            ];
        }

        $baseDate = ($license->expires_at && $license->expires_at->isFuture()) ? $license->expires_at : now();
        $newExpiry = $baseDate->addDays($additionalDays);

        $license->update([
            'mode' => $targetMode,
            'is_active' => true,
            'expires_at' => $newExpiry,
        ]);

        return [
            'success' => true,
            'message' => 'License key successfully renewed to '.strtoupper($targetMode).' mode (Expires on '.$newExpiry->toFormattedDateString().').',
            'license' => $license,
        ];
    }
}

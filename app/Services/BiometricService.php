<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Member;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BiometricService
{
    /**
     * Register or update a fingerprint template for a member
     */
    public function registerFingerprint(Member $member, string $template): Member
    {
        $member->update([
            'fingerprint_template' => $template,
        ]);

        // Invalidate tenant biometric cache
        Cache::forget("tenant_{$member->tenant_id}_biometrics");

        return $member;
    }

    /**
     * High-speed fingerprint matching and check-in processing
     */
    public function verifyAndCheckIn(Tenant $tenant, string $template): array
    {
        // 1. Get cached fingerprint templates for current tenant
        $cacheKey = "tenant_{$tenant->id}_biometrics";

        $membersWithTemplates = Cache::remember($cacheKey, 600, function () use ($tenant) {
            return Member::where('fingerprint_template', '!=', null)
                ->get(['id', 'uuid', 'first_name', 'last_name', 'photo_url', 'status', 'fingerprint_template']);
        });

        // 2. Perform template comparison
        $matchedMember = null;
        foreach ($membersWithTemplates as $member) {
            if ($member->fingerprint_template === $template || str_contains($template, $member->fingerprint_template)) {
                $matchedMember = $member;
                break;
            }
        }

        if (!$matchedMember) {
            return [
                'success' => false,
                'message' => 'Fingerprint not recognized. No matching member found.',
            ];
        }

        // Reload fresh model instance with relations
        $fullMember = Member::with('activePlan.pricingPlan')->find($matchedMember->id);

        if ($fullMember->status !== 'active') {
            return [
                'success' => false,
                'message' => "Access denied: {$fullMember->formatted_display_name}'s account is currently {$fullMember->status}.",
                'member' => $fullMember,
            ];
        }

        if (!$fullMember->activePlan) {
            return [
                'success' => false,
                'message' => "Access denied: {$fullMember->formatted_display_name} does not have an active subscription plan.",
                'member' => $fullMember,
            ];
        }

        // 3. Record attendance check-in
        $checkIn = AttendanceLog::create([
            'tenant_id' => $tenant->id,
            'member_id' => $fullMember->id,
            'check_in_time' => Carbon::now(),
            'method' => 'fingerprint',
            'is_active_session' => true,
        ]);

        return [
            'success' => true,
            'message' => "Access granted! Welcome, {$fullMember->formatted_display_name}.",
            'data' => [
                'check_in' => $checkIn,
                'member' => [
                    'id' => $fullMember->id,
                    'uuid' => $fullMember->uuid,
                    'display_name' => $fullMember->formatted_display_name,
                    'full_name' => "{$fullMember->first_name} {$fullMember->last_name}",
                    'photo_url' => $fullMember->photo_url,
                    'plan_name' => $fullMember->activePlan->pricingPlan->name ?? 'Active Plan',
                    'plan_expires_at' => $fullMember->activePlan->expires_at->toFormattedDateString(),
                ]
            ]
        ];
    }
}

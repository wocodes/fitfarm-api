<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MemberPortalController extends Controller
{
    /**
     * Get public tenant branding info, theme settings, and active pricing plans.
     */
    public function portalInfo(Request $request)
    {
        $slug = $request->query('tenant');

        $tenant = null;
        if ($slug) {
            $tenant = Tenant::with(['theme', 'pricingPlans'])->where('slug', $slug)->first();
        }

        if (!$tenant) {
            $tenant = Tenant::with(['theme', 'pricingPlans'])->first();
        }

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No active gym tenant found.',
            ], 404);
        }

        $plans = PricingPlan::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => [
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'logo_url' => $tenant->logo_url,
                    'currency' => $tenant->currency ?? 'NGN',
                    'timezone' => $tenant->timezone ?? 'Africa/Lagos',
                ],
                'theme' => $tenant->theme ?? [
                    'primary_color' => '#fa6a2e',
                    'accent_color' => '#1e293b',
                ],
                'pricing_plans' => $plans,
            ]
        ]);
    }

    /**
     * Member Sign In authentication.
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $member = Member::where('email', $validated['email'])->first();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address or password.',
            ], 401);
        }

        $dbPassword = $member->password ?? '';
        if (str_contains($dbPassword, '{bcrypt}')) {
            $dbPassword = str_replace('{bcrypt}', '', $dbPassword);
        }
        if (str_starts_with($dbPassword, '$2a$')) {
            $dbPassword = '$2y$' . substr($dbPassword, 4);
            $member->update(['password' => $dbPassword]);
        }

        $passwordValid = false;
        if (str_starts_with($dbPassword, '$2y$') || str_starts_with($dbPassword, '$2b$')) {
            try {
                $passwordValid = Hash::check($validated['password'], $dbPassword);
            } catch (\Throwable $e) {
                $passwordValid = false;
            }
        }

        if (!$passwordValid && ($validated['password'] === $dbPassword || md5($validated['password']) === $dbPassword)) {
            $passwordValid = true;
            $member->update(['password' => Hash::make($validated['password'])]);
        }

        if (!$passwordValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address or password.',
            ], 401);
        }

        $token = $member->createToken('member-portal-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'member' => $member->load('activePlan.pricingPlan'),
                'token' => $token,
            ]
        ]);
    }

    /**
     * Member Self-Registration.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'tenant_slug' => 'nullable|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:members,email',
            'phone' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $tenant = Tenant::where('slug', $validated['tenant_slug'] ?? '')->first() ?? Tenant::first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No active gym tenant found.',
            ], 422);
        }

        $member = Member::create([
            'tenant_id' => $tenant->id,
            'uuid' => 'FC-M-' . strtoupper(Str::random(6)),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        $token = $member->createToken('member-portal-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account registered successfully.',
            'data' => [
                'member' => $member,
                'token' => $token,
            ]
        ], 201);
    }

    /**
     * Get authenticated member profile, active subscription status, QR code pass, and check-in history.
     */
    public function profile(Request $request)
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $activePlan = MemberPlan::with('pricingPlan')
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        $checkIns = AttendanceLog::where('member_id', $member->id)
            ->latest()
            ->take(10)
            ->get();

        $qrCodePayload = json_encode([
            'member_id' => $member->id,
            'uuid' => $member->uuid,
            'name' => "{$member->first_name} {$member->last_name}",
            'valid_until' => $activePlan?->expires_at?->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'member' => $member,
                'active_plan' => $activePlan,
                'qr_code_token' => base64_encode($qrCodePayload),
                'check_in_history' => $checkIns,
            ]
        ]);
    }
}

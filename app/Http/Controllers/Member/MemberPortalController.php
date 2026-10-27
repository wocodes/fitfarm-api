<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Transaction;
use App\Services\BachsService;
use App\Services\MembershipActivationService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MemberPortalController extends Controller
{
    /**
     * Get public tenant branding info, theme settings, and active pricing plans.
     */
    public function portalInfo(Request $request)
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found.',
            ], 404);
        }

        if (! in_array($tenant->status, ['active', 'trialing'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This gym portal is currently unavailable.',
            ], 404);
        }

        $tenant->load(['theme', 'pricingPlans']);

        $plans = PricingPlan::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        $cms = [
            'hero' => $tenant->getCmsSection('hero'),
            'amenities' => $tenant->getCmsSection('amenities'),
            'classes' => $tenant->getCmsSection('classes'),
            'trainers' => $tenant->getCmsSection('trainers'),
            'testimonials' => $tenant->getCmsSection('testimonials'),
            'faqs' => $tenant->getCmsSection('faqs'),
            'contact_socials' => $tenant->getCmsSection('contact_socials'),
            'section_visibility' => $tenant->getCmsSection('section_visibility'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => [
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'logo_url' => $tenant->logo_url,
                    'currency' => $tenant->currency ?? 'NGN',
                    'timezone' => $tenant->timezone ?? 'Africa/Lagos',
                    'address' => $cms['contact_socials']['address'] ?? null,
                    'phone' => $cms['contact_socials']['phone'] ?? null,
                    'email' => $cms['contact_socials']['email'] ?? null,
                ],
                'theme' => $tenant->theme ?? [
                    'primary_color' => '#fa6a2e',
                    'accent_color' => '#1e293b',
                ],
                'pricing_plans' => $plans,
                'platform' => [
                    'usd_rate' => app(PlatformSettingsService::class)->usdRate(),
                    'default_currency' => app(PlatformSettingsService::class)->defaultCurrency(),
                ],
                'cms' => $cms,
            ],
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

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant || ! in_array($tenant->status, ['active', 'trialing'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found or currently unavailable.',
            ], 422);
        }

        $member = Member::where('tenant_id', $tenant->id)
            ->where('email', $validated['email'])
            ->first();

        if (! $member) {
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
            $dbPassword = '$2y$'.substr($dbPassword, 4);
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

        if (! $passwordValid && ($validated['password'] === $dbPassword || md5($validated['password']) === $dbPassword)) {
            $passwordValid = true;
            $member->update(['password' => Hash::make($validated['password'])]);
        }

        if (! $passwordValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address or password.',
            ], 401);
        }

        $token = $member->createToken('member-portal-token', ['member'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'member' => $member->load('activePlan.pricingPlan'),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Member Self-Registration.
     */
    public function register(Request $request)
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant || ! in_array($tenant->status, ['active', 'trialing'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found or currently unavailable.',
            ], 422);
        }

        $validated = $request->validate([
            'tenant_slug' => 'nullable|string',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('members', 'email')->where('tenant_id', $tenant->id),
            ],
            'phone' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        if (! empty($validated['tenant_slug'])
            && strtolower($validated['tenant_slug']) !== strtolower($tenant->slug)) {
            return response()->json([
                'success' => false,
                'message' => 'The requested gym does not match this portal.',
            ], 422);
        }

        $member = Member::create([
            'tenant_id' => $tenant->id,
            'uuid' => 'FC-M-'.strtoupper(Str::random(6)),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        $token = $member->createToken('member-portal-token', ['member'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Account registered successfully.',
            'data' => [
                'member' => $member,
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Get authenticated member profile, active subscription status, QR code pass, and check-in history.
     */
    public function profile(Request $request)
    {
        $member = $request->user();

        if (! $member) {
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
            ],
        ]);
    }

    /**
     * Get member's current active workout session (if checked in).
     */
    public function activeSession(Request $request)
    {
        $member = $request->user();
        $session = AttendanceLog::where('member_id', $member->id)
            ->where('is_active_session', true)
            ->latest('check_in_time')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => true,
                'data' => [
                    'isCheckedIn' => false,
                    'session' => null,
                ],
            ]);
        }

        $checkInTime = Carbon::parse($session->check_in_time);
        $elapsedSeconds = max(0, $checkInTime->diffInSeconds(now()));

        return response()->json([
            'success' => true,
            'data' => [
                'isCheckedIn' => true,
                'session' => [
                    'id' => $session->id,
                    'check_in_time' => $session->check_in_time,
                    'elapsed_seconds' => $elapsedSeconds,
                    'method' => $session->method ?? 'qr',
                ],
            ],
        ]);
    }

    /**
     * Check out from current active workout session.
     */
    public function checkout(Request $request)
    {
        $member = $request->user();
        $session = AttendanceLog::where('member_id', $member->id)
            ->where('is_active_session', true)
            ->latest('check_in_time')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'No active workout session found to check out.',
            ], 404);
        }

        $now = now();
        $durationMinutes = Carbon::parse($session->check_in_time)->diffInMinutes($now);

        $session->update([
            'is_active_session' => false,
            'check_out_time' => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Checked out successfully! You worked out for {$durationMinutes} minutes.",
            'data' => [
                'session_id' => $session->id,
                'check_out_time' => $now->toIso8601String(),
                'duration_minutes' => $durationMinutes,
            ],
        ]);
    }

    /**
     * Create a hosted Bachs checkout for a plan.
     */
    public function initializePlan(Request $request)
    {
        $member = $request->user();

        $validated = $request->validate([
            'pricing_plan_id' => 'required|integer',
            'currency' => 'nullable|in:NGN,USD',
        ]);

        $plan = PricingPlan::where('tenant_id', $member->tenant_id)
            ->whereKey($validated['pricing_plan_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $tenant = $member->tenant;
        $planCurrency = strtoupper((string) ($tenant?->currency ?: 'NGN'));
        $selectedCurrency = strtoupper((string) ($validated['currency'] ?? $planCurrency));
        $usdRate = app(PlatformSettingsService::class)->usdRate();

        $amount = (float) $plan->price;

        if ($selectedCurrency !== $planCurrency) {
            if ($selectedCurrency === 'USD' && $planCurrency === 'NGN') {
                $amount = round($amount / $usdRate, 2);
            } elseif ($selectedCurrency === 'NGN' && $planCurrency === 'USD') {
                $amount = round($amount * $usdRate, 2);
            }
        }

        $feePercentage = (float) config('fitcloud.platform_fee_percentage', 1.0);
        $platformFee = $tenant?->pricing_path === 'free_tier'
            ? round($amount * ($feePercentage / 100), 2)
            : 0.00;

        $transaction = Transaction::create([
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
            'pricing_plan_id' => $plan->id,
            'reference' => 'FFH-'.strtoupper(Str::random(16)),
            'amount' => $amount,
            'platform_fee' => $platformFee,
            'net_amount' => $amount - $platformFee,
            'currency' => $selectedCurrency,
            'payment_method' => 'bachs',
            'status' => 'pending',
        ]);

        $checkout = app(BachsService::class)->createCheckout($transaction, $member, $tenant);

        if (! $checkout['success']) {
            $transaction->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => $checkout['message'] ?? 'Could not start the payment checkout.',
            ], 422);
        }

        $transaction->update([
            'provider_checkout_id' => $checkout['checkout_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'checkout_url' => $checkout['checkout_url'],
                'reference' => $transaction->reference,
            ],
        ]);
    }

    /**
     * Confirm a completed checkout and activate the member plan.
     */
    public function purchasePlan(Request $request)
    {
        $member = $request->user();

        $validated = $request->validate([
            'checkout_id' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
        ]);

        if (empty($validated['checkout_id']) && empty($validated['reference'])) {
            return response()->json([
                'success' => false,
                'message' => 'A checkout reference is required.',
            ], 422);
        }

        $query = Transaction::where('tenant_id', $member->tenant_id)
            ->where('member_id', $member->id);

        if (! empty($validated['checkout_id'])) {
            $query->where('provider_checkout_id', $validated['checkout_id']);
        } else {
            $query->where('reference', $validated['reference']);
        }

        $transaction = $query->latest('id')->first();

        if (! $transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Payment checkout not found.',
            ], 404);
        }

        if ($transaction->status === 'successful') {
            $plan = MemberPlan::where('tenant_id', $member->tenant_id)
                ->where('member_id', $member->id)
                ->where('status', 'active')
                ->latest('id')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'This payment has already been applied to your membership.',
                'data' => [
                    'member_plan' => $plan?->load('pricingPlan'),
                    'transaction' => $transaction,
                ],
            ]);
        }

        if (! $transaction->provider_checkout_id) {
            return response()->json([
                'success' => false,
                'message' => 'This checkout has no provider reference.',
            ], 422);
        }

        $checkout = app(BachsService::class)->getCheckout($transaction->provider_checkout_id);

        if (! $checkout['success']) {
            return response()->json([
                'success' => false,
                'message' => $checkout['message'] ?? 'Could not verify the payment checkout.',
            ], 422);
        }

        $status = strtolower((string) ($checkout['data']['status'] ?? ''));

        if (! in_array($status, ['completed', 'succeeded', 'paid'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'The payment has not completed yet. Please wait a moment and refresh.',
            ], 422);
        }

        $memberPlan = app(MembershipActivationService::class)->activateFromTransaction($transaction);

        if (! $memberPlan) {
            return response()->json([
                'success' => false,
                'message' => 'The payment could not be applied to a membership plan.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully.',
            'data' => [
                'member_plan' => $memberPlan->load('pricingPlan'),
                'transaction' => $transaction->fresh(),
            ],
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Transaction;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
     * Process & record member subscription plan purchase (e.g. after Paystack verification).
     */
    public function purchasePlan(Request $request)
    {
        $member = $request->user();

        $validated = $request->validate([
            'pricing_plan_id' => 'required|integer',
            'payment_reference' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'channel' => 'nullable|string|max:50',
        ]);

        $plan = PricingPlan::where('tenant_id', $member->tenant_id)
            ->whereKey($validated['pricing_plan_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $expectedAmount = (float) $plan->price;

        if (abs((float) $validated['amount'] - $expectedAmount) > 0.009) {
            return response()->json([
                'success' => false,
                'message' => 'The purchase amount does not match the selected plan.',
            ], 422);
        }

        $reference = trim($validated['payment_reference']);

        if (config('fitcloud.verify_paystack_references', false)) {
            $verification = app(PaystackService::class)->verifyTransaction($reference);

            if (! $verification['success']
                || ($verification['status'] ?? null) !== 'success'
                || ($verification['reference'] ?? null) !== $reference) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment could not be verified with Paystack. Please try again or contact support.',
                ], 422);
            }

            if ((int) ($verification['amount'] ?? 0) !== (int) round($expectedAmount * 100)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The verified payment amount does not match the selected plan.',
                ], 422);
            }

            $verifiedEmail = strtolower((string) ($verification['data']['customer']['email'] ?? ''));

            if ($verifiedEmail !== '' && $verifiedEmail !== strtolower((string) $member->email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The verified payment does not belong to this member account.',
                ], 422);
            }
        } elseif (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Online payments are not configured correctly. Please contact the gym.',
            ], 503);
        }

        return DB::transaction(function () use ($member, $plan, $reference, $expectedAmount) {
            $existingTransaction = Transaction::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if ($existingTransaction) {
                if ((int) $existingTransaction->member_id !== (int) $member->id
                    || $existingTransaction->status !== 'successful') {
                    return response()->json([
                        'success' => false,
                        'message' => 'This payment reference has already been used.',
                    ], 422);
                }

                $existingPlan = MemberPlan::where('tenant_id', $member->tenant_id)
                    ->where('member_id', $member->id)
                    ->where('status', 'active')
                    ->latest('id')
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => 'This payment has already been applied to your membership.',
                    'data' => [
                        'member_plan' => $existingPlan?->load('pricingPlan'),
                        'transaction' => $existingTransaction,
                    ],
                ]);
            }

            $transaction = Transaction::create([
                'tenant_id' => $member->tenant_id,
                'member_id' => $member->id,
                'pricing_plan_id' => $plan->id,
                'reference' => $reference,
                'amount' => $expectedAmount,
                'platform_fee' => 0,
                'net_amount' => $expectedAmount,
                'currency' => $member->tenant?->currency ?? 'NGN',
                'payment_method' => 'paystack',
                'status' => 'successful',
            ]);

            $durationDays = $plan->duration_days ?: 30;
            $existingPlan = MemberPlan::where('tenant_id', $member->tenant_id)
                ->where('member_id', $member->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->latest('expires_at')
                ->first();

            $startsAt = $existingPlan ? $existingPlan->expires_at : now();
            $expiresAt = (clone $startsAt)->addDays($durationDays);

            $memberPlan = MemberPlan::create([
                'tenant_id' => $member->tenant_id,
                'member_id' => $member->id,
                'pricing_plan_id' => $plan->id,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            $member->update(['status' => 'active']);

            return response()->json([
                'success' => true,
                'message' => "Subscription to {$plan->name} activated successfully! Valid until {$expiresAt->format('M d, Y')}.",
                'data' => [
                    'member_plan' => $memberPlan->load('pricingPlan'),
                    'transaction' => $transaction,
                ],
            ], 201);
        });
    }
}

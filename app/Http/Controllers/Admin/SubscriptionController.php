<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of member subscriptions.
     */
    public function index(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $query = MemberPlan::with(['member', 'pricingPlan'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $subscriptions = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
        ]);
    }

    /**
     * Assign a pricing plan to a gym member.
     */
    public function store(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $validated = $request->validate([
            'member_id' => 'required|exists:members,id',
            'pricing_plan_id' => 'required|exists:pricing_plans,id',
            'starts_at' => 'nullable|date',
            'payment_method' => 'nullable|string|in:BANK_TRANSFER,POS,CASH,PAYSTACK',
        ]);

        return DB::transaction(function () use ($tenantId, $validated, $request) {
            $member = Member::where('tenant_id', $tenantId)->findOrFail($validated['member_id']);
            $plan = PricingPlan::where('tenant_id', $tenantId)->findOrFail($validated['pricing_plan_id']);

            $startsAt = $validated['starts_at'] ? Carbon::parse($validated['starts_at']) : Carbon::now();
            $expiresAt = (clone $startsAt)->addDays($plan->duration_days);

            // Deactivate old active plans for member
            MemberPlan::where('tenant_id', $tenantId)
                ->where('member_id', $member->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);

            $memberPlan = MemberPlan::create([
                'tenant_id' => $tenantId,
                'member_id' => $member->id,
                'pricing_plan_id' => $plan->id,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            // Automatically record financial transaction
            $paymentMethod = $validated['payment_method'] ?? 'BANK_TRANSFER';
            $transaction = Transaction::create([
                'tenant_id' => $tenantId,
                'member_id' => $member->id,
                'pricing_plan_id' => $plan->id,
                'amount' => $plan->price,
                'net_amount' => $plan->price,
                'currency' => 'NGN',
                'payment_method' => $paymentMethod,
                'reference' => 'TXN-' . strtoupper(uniqid()),
                'status' => 'approved',
            ]);

            // Audit log entry
            AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $request->user()?->id,
                'user_name' => $request->user()?->name ?? 'Admin',
                'action' => 'PLAN_ASSIGNED',
                'target_type' => 'MemberPlan',
                'target_id' => (string) $memberPlan->id,
                'description' => "Assigned plan '{$plan->name}' to member '{$member->name}' for price NGN {$plan->price}.",
                'new_state' => $memberPlan->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Plan '{$plan->name}' successfully assigned to member.",
                'data' => [
                    'member_plan' => $memberPlan->load(['member', 'pricingPlan']),
                    'transaction' => $transaction,
                ],
            ], 201);
        });
    }

    /**
     * Extend member plan duration and automatically log corresponding financial transaction.
     */
    public function extend(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');

        $validated = $request->validate([
            'extension_days' => 'nullable|integer|min:1',
            'payment_method' => 'nullable|string|in:BANK_TRANSFER,POS,CASH,PAYSTACK',
        ]);

        return DB::transaction(function () use ($tenantId, $id, $validated, $request) {
            $subscription = MemberPlan::with(['member', 'pricingPlan'])
                ->where('tenant_id', $tenantId)
                ->findOrFail($id);

            $plan = $subscription->pricingPlan;
            $extensionDays = $validated['extension_days'] ?? ($plan ? $plan->duration_days : 30);
            $planPrice = $plan ? $plan->price : 0.00;

            $baseDate = ($subscription->expires_at && $subscription->expires_at->isFuture())
                ? $subscription->expires_at
                : Carbon::now();

            $previousState = $subscription->toArray();
            $newExpiry = (clone $baseDate)->addDays($extensionDays);

            $subscription->update([
                'expires_at' => $newExpiry,
                'status' => 'active',
            ]);

            // Record corresponding financial transaction
            $paymentMethod = $validated['payment_method'] ?? 'BANK_TRANSFER';
            $transaction = Transaction::create([
                'tenant_id' => $tenantId,
                'member_id' => $subscription->member_id,
                'pricing_plan_id' => $subscription->pricing_plan_id,
                'amount' => $planPrice,
                'net_amount' => $planPrice,
                'currency' => 'NGN',
                'payment_method' => $paymentMethod,
                'reference' => 'EXT-' . strtoupper(uniqid()),
                'status' => 'approved',
            ]);

            // Log Audit Event
            AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $request->user()?->id,
                'user_name' => $request->user()?->name ?? 'Admin',
                'action' => 'PLAN_EXTENDED',
                'target_type' => 'MemberPlan',
                'target_id' => (string) $subscription->id,
                'description' => "Extended subscription for member '{$subscription->member->name}' by {$extensionDays} days until " . $newExpiry->toFormattedDateString(),
                'previous_state' => $previousState,
                'new_state' => $subscription->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Subscription extended successfully by {$extensionDays} days. Financial transaction recorded.",
                'data' => [
                    'subscription' => $subscription->fresh(['member', 'pricingPlan']),
                    'transaction' => $transaction,
                ]
            ]);
        });
    }

    /**
     * Pause a member subscription.
     */
    public function pause(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');
        $subscription = MemberPlan::with('member')->where('tenant_id', $tenantId)->findOrFail($id);

        $previousState = $subscription->toArray();
        $subscription->update(['status' => 'paused']);

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name ?? 'Admin',
            'action' => 'PLAN_PAUSED',
            'target_type' => 'MemberPlan',
            'target_id' => (string) $subscription->id,
            'description' => "Paused subscription for member '{$subscription->member->name}'.",
            'previous_state' => $previousState,
            'new_state' => $subscription->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription paused successfully.',
            'data' => $subscription,
        ]);
    }

    /**
     * Unpause a member subscription.
     */
    public function unpause(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');
        $subscription = MemberPlan::with('member')->where('tenant_id', $tenantId)->findOrFail($id);

        $previousState = $subscription->toArray();
        $subscription->update(['status' => 'active']);

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name ?? 'Admin',
            'action' => 'PLAN_UNPAUSED',
            'target_type' => 'MemberPlan',
            'target_id' => (string) $subscription->id,
            'description' => "Unpaused subscription for member '{$subscription->member->name}'.",
            'previous_state' => $previousState,
            'new_state' => $subscription->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription unpaused successfully.',
            'data' => $subscription,
        ]);
    }

    /**
     * Get expiring & expired subscriptions queue.
     */
    public function renewals(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $cutoffDate = Carbon::now()->addDays(7);

        $renewals = MemberPlan::with(['member', 'pricingPlan'])
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($cutoffDate) {
                $q->where('expires_at', '<=', $cutoffDate)
                  ->orWhere('status', 'expired');
            })
            ->latest('expires_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $renewals,
        ]);
    }
}

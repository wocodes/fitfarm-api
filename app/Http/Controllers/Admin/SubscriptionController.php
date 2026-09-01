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
        $page = (int) $request->input('page', 1);
        $size = (int) $request->input('size', $request->input('per_page', 20));

        $query = MemberPlan::with(['member', 'pricingPlan'])
            ->where('tenant_id', $tenantId);

        if ($request->boolean('expiringSoon') || $request->input('status') === 'EXPIRING_SOON') {
            $cutoff = Carbon::now()->addDays(7);
            $query->where(function ($q) use ($cutoff) {
                $q->whereBetween('expires_at', [Carbon::now(), $cutoff])
                  ->orWhere('status', 'expired');
            });
        } else if ($request->filled('status')) {
            $statusVal = strtolower($request->input('status'));
            $query->where('status', $statusVal);
        }

        if ($request->filled('pricingPlanId')) {
            $query->where('pricing_plan_id', $request->input('pricingPlanId'));
        }

        if ($request->filled('startDate')) {
            $query->where('starts_at', '>=', Carbon::parse($request->input('startDate')));
        }

        if ($request->filled('endDate')) {
            $query->where('expires_at', '<=', Carbon::parse($request->input('endDate')));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('uid', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest('starts_at')->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'content' => $paginator->items(),
                'totalElements' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
                'currentPage' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
            ],
            'content' => $paginator->items(),
            'totalElements' => $paginator->total(),
        ]);
    }

    /**
     * Assign a pricing plan to a gym member.
     */
    public function store(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $validated = $request->validate([
            'member_id' => 'required',
            'pricing_plan_id' => 'required',
            'starts_at' => 'nullable|date',
            'payment_method' => 'nullable|string|in:BANK_TRANSFER,POS,CASH,PAYSTACK',
        ]);

        return DB::transaction(function () use ($tenantId, $validated, $request) {
            $member = Member::where('tenant_id', $tenantId)
                ->where(function ($q) use ($validated) {
                    $q->where('id', $validated['member_id'])
                      ->orWhere('uuid', $validated['member_id']);
                })->firstOrFail();

            $plan = PricingPlan::where('tenant_id', $tenantId)
                ->where(function ($q) use ($validated) {
                    $q->where('id', $validated['pricing_plan_id'])
                      ->orWhere('uuid', $validated['pricing_plan_id']);
                })->firstOrFail();

            $startsAt = !empty($validated['starts_at']) ? Carbon::parse($validated['starts_at']) : Carbon::now();
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
                'status' => 'successful',
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
                'status' => 'successful',
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
     * Update dates or details for a member plan.
     */
    public function update(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');
        $subscription = MemberPlan::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'startDate' => 'nullable',
            'endDate' => 'nullable',
            'starts_at' => 'nullable',
            'expires_at' => 'nullable',
            'status' => 'nullable|string',
        ]);

        $startsAt = $validated['startDate'] ?? $validated['starts_at'] ?? $subscription->starts_at;
        $expiresAt = $validated['endDate'] ?? $validated['expires_at'] ?? $subscription->expires_at;

        $status = $validated['status'] ?? null;
        if (!$status && $expiresAt) {
            $status = Carbon::parse($expiresAt)->isFuture() ? 'active' : 'expired';
        }

        $subscription->update([
            'starts_at' => $startsAt ? Carbon::parse($startsAt) : $subscription->starts_at,
            'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : $subscription->expires_at,
            'status' => $status ?? $subscription->status,
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Subscription updated successfully.',
            'data' => $subscription->fresh(['member', 'pricingPlan']),
        ]);
    }

    /**
     * Delete a member plan.
     */
    public function destroy(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');
        $subscription = MemberPlan::where('tenant_id', $tenantId)->findOrFail($id);
        $subscription->delete();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Member plan deleted successfully.',
        ]);
    }

    /**
     * Reconcile status of expired / active plans.
     */
    public function reconcileStatuses(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $activatedCount = MemberPlan::where('tenant_id', $tenantId)
            ->where('status', 'expired')
            ->where('expires_at', '>', Carbon::now())
            ->update(['status' => 'active']);

        $expiredCount = MemberPlan::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('expires_at', '<=', Carbon::now())
            ->update(['status' => 'expired']);

        $totalCount = $activatedCount + $expiredCount;

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "Reconciled {$totalCount} member plan statuses ({$activatedCount} reactivated, {$expiredCount} marked expired).",
        ]);
    }

    /**
     * Bulk update dates for multiple member plans.
     */
    public function bulkUpdate(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $validated = $request->validate([
            'planIds' => 'required|array',
            'startDate' => 'nullable',
            'endDate' => 'nullable',
        ]);

        $updateData = [];
        if (!empty($validated['startDate'])) {
            $updateData['starts_at'] = Carbon::parse($validated['startDate']);
        }
        if (!empty($validated['endDate'])) {
            $updateData['expires_at'] = Carbon::parse($validated['endDate']);
            $updateData['status'] = Carbon::parse($validated['endDate'])->isFuture() ? 'active' : 'expired';
        }

        if (!empty($updateData)) {
            MemberPlan::where('tenant_id', $tenantId)
                ->whereIn('id', $validated['planIds'])
                ->update($updateData);
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Bulk update completed successfully.',
        ]);
    }

    /**
     * Get expiring & expired subscriptions queue / renewals log.
     */
    public function renewals(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $page = (int) $request->input('page', 1);
        $size = (int) $request->input('size', $request->input('per_page', 20));

        $query = MemberPlan::with(['member', 'pricingPlan'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('pricingPlanId')) {
            $query->where('pricing_plan_id', $request->input('pricingPlanId'));
        }

        if ($request->filled('startDate')) {
            $query->where('updated_at', '>=', Carbon::parse($request->input('startDate')));
        }

        if ($request->filled('endDate')) {
            $query->where('updated_at', '<=', Carbon::parse($request->input('endDate')));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($sq) use ($search) {
                $sq->whereHas('member', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('uid', 'like', "%{$search}%");
                })->orWhereHas('pricingPlan', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            });
        } else if (!$request->filled('startDate') && !$request->filled('endDate') && !$request->filled('pricingPlanId')) {
            // Default renewals queue: expiring within 7 days or expired
            $cutoffDate = Carbon::now()->addDays(7);
            $query->where(function ($q) use ($cutoffDate) {
                $q->where('expires_at', '<=', $cutoffDate)
                  ->orWhere('status', 'expired');
            });
        }

        $paginator = $query->latest('updated_at')->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'content' => $paginator->items(),
                'totalElements' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
                'currentPage' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
            ],
            'content' => $paginator->items(),
            'totalElements' => $paginator->total(),
        ]);
    }
}

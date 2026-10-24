<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    /**
     * Display financial transactions ledger.
     */
    public function index(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $page = (int) $request->input('page', 1);
        $size = (int) $request->input('size', $request->input('per_page', 20));

        $query = Transaction::with(['member', 'pricingPlan'])
            ->where('tenant_id', $tenantId);

        $method = $request->input('payment_method', $request->input('paymentMethod'));
        if ($method) {
            $query->where(function ($mq) use ($method) {
                $mq->where('payment_method', $method)
                    ->orWhere('payment_method', strtolower($method))
                    ->orWhere('payment_method', strtoupper($method));
            });
        }

        $status = $request->input('status');
        if ($status) {
            $rawStatus = match (strtolower($status)) {
                'completed', 'approved' => 'successful',
                'rejected' => 'failed',
                default => strtolower($status),
            };
            $query->where(function ($sq) use ($rawStatus, $status) {
                $sq->where('status', $rawStatus)
                    ->orWhere('status', strtolower($status))
                    ->orWhere('status', strtoupper($status));
            });
        }

        if ($request->filled('startDate')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('startDate')));
        }

        if ($request->filled('endDate')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('endDate'))->endOfDay());
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('member', function ($mq) use ($search) {
                        $mq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('uid', 'like', "%{$search}%");
                    })->orWhereHas('pricingPlan', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Calculate channel volumes
        $successfulBase = (clone $query)->whereIn('status', ['successful', 'approved', 'SUCCESS']);
        $totalVolume = (float) $successfulBase->sum('amount');

        $bankTransferVolume = (float) (clone $successfulBase)
            ->whereIn('payment_method', ['BANK_TRANSFER', 'bank_transfer', 'bank', 'Bank Transfer'])
            ->sum('amount');

        $posCashVolume = (float) (clone $successfulBase)
            ->whereIn('payment_method', ['POS', 'CASH', 'pos', 'cash', 'POS Terminal', 'Cash'])
            ->sum('amount');

        $paystackVolume = (float) (clone $successfulBase)
            ->whereIn('payment_method', ['PAYSTACK', 'paystack', 'Paystack Online', 'Paystack'])
            ->sum('amount');

        $paginator = $query->latest()->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'summary' => [
                'totalVolume' => $totalVolume,
                'bankTransferVolume' => $bankTransferVolume,
                'posCashVolume' => $posCashVolume,
                'paystackVolume' => $paystackVolume,
            ],
            'data' => [
                'content' => $paginator->items(),
                'totalElements' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
                'currentPage' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'summary' => [
                    'totalVolume' => $totalVolume,
                    'bankTransferVolume' => $bankTransferVolume,
                    'posCashVolume' => $posCashVolume,
                    'paystackVolume' => $paystackVolume,
                ],
            ],
            'content' => $paginator->items(),
            'totalElements' => $paginator->total(),
        ]);
    }

    /**
     * Record a manual financial transaction.
     */
    public function store(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $validated = $request->validate([
            'member_id' => 'nullable|exists:members,id',
            'pricing_plan_id' => 'nullable|exists:pricing_plans,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $reference = $validated['reference'] ?? ('MAN-'.strtoupper(uniqid()));
        $rawStatus = strtolower($validated['status'] ?? 'successful');
        $status = match ($rawStatus) {
            'approved', 'success' => 'successful',
            'rejected' => 'failed',
            default => $rawStatus,
        };

        $recordedBy = $request->input('recorded_by') ?? $request->user()?->name ?? 'Super Admin';

        $transaction = Transaction::create([
            'tenant_id' => $tenantId,
            'member_id' => $validated['member_id'] ?? null,
            'pricing_plan_id' => $validated['pricing_plan_id'] ?? null,
            'amount' => $validated['amount'],
            'net_amount' => $validated['amount'],
            'currency' => 'NGN',
            'payment_method' => strtoupper($validated['payment_method']),
            'reference' => $reference,
            'status' => $status,
            'recorded_by' => $recordedBy,
            'notes' => $request->input('notes'),
        ]);

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name ?? 'Admin',
            'action' => 'TRANSACTION_RECORDED',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
            'description' => "Recorded {$validated['payment_method']} transaction of NGN {$validated['amount']} (Ref: {$reference}).",
            'new_state' => $transaction->toArray(),
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Financial transaction recorded successfully.',
            'data' => $transaction->load(['member', 'pricingPlan']),
        ], 201);
    }

    /**
     * Approve or reject pending transaction.
     */
    public function updateStatus(Request $request, $id)
    {
        $tenantId = $request->get('tenant_id');
        $transaction = Transaction::where('tenant_id', $tenantId)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', (string) $id);
            })->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $rawStatus = strtolower($validated['status']);
        $status = match ($rawStatus) {
            'approved', 'success' => 'successful',
            'rejected' => 'failed',
            default => $rawStatus,
        };

        $previousState = $transaction->toArray();
        $transaction->update(['status' => $status]);

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name ?? 'Admin',
            'action' => 'TRANSACTION_STATUS_UPDATED',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
            'description' => "Updated transaction status to '{$status}' (Ref: {$transaction->reference}).",
            'previous_state' => $previousState,
            'new_state' => $transaction->toArray(),
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "Transaction status updated to {$status}.",
            'data' => $transaction,
        ]);
    }

    /**
     * Update amount of a transaction (Super Admin).
     */
    public function updateAmount(Request $request, $id)
    {
        $tenantId = $request->get('tenant_id');
        $transaction = Transaction::where('tenant_id', $tenantId)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', (string) $id);
            })->firstOrFail();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $previousState = $transaction->toArray();
        $transaction->update([
            'amount' => $validated['amount'],
            'net_amount' => $validated['amount'],
        ]);

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name ?? 'Admin',
            'action' => 'TRANSACTION_AMOUNT_UPDATED',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
            'description' => "Updated transaction amount from {$previousState['amount']} to {$validated['amount']} (Ref: {$transaction->reference}).",
            'previous_state' => $previousState,
            'new_state' => $transaction->toArray(),
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Transaction amount updated successfully.',
            'data' => $transaction,
        ]);
    }

    /**
     * Approve a manual payment.
     */
    public function approve(Request $request, $id)
    {
        $request->merge(['status' => 'approved']);

        return $this->updateStatus($request, $id);
    }

    /**
     * Reject a manual payment.
     */
    public function reject(Request $request, $id)
    {
        $request->merge(['status' => 'rejected']);

        return $this->updateStatus($request, $id);
    }

    /**
     * Delete a transaction (Super Admin).
     */
    public function destroy(Request $request, $id)
    {
        $tenantId = $request->get('tenant_id');
        $transaction = Transaction::where('tenant_id', $tenantId)
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', (string) $id);
            })->firstOrFail();

        $previousState = $transaction->toArray();
        $transaction->delete();

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name ?? 'Admin',
            'action' => 'TRANSACTION_DELETED',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
            'description' => "Deleted transaction of NGN {$previousState['amount']} (Ref: {$previousState['reference']}).",
            'previous_state' => $previousState,
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Transaction deleted successfully.',
        ]);
    }
}

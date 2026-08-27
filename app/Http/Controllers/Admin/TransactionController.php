<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Display financial transactions ledger.
     */
    public function index(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $query = Transaction::with(['member', 'pricingPlan'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('member', function ($mq) use ($search) {
                      $mq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $transactions = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $transactions,
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
            'payment_method' => 'required|string|in:BANK_TRANSFER,POS,CASH,PAYSTACK',
            'reference' => 'nullable|string',
            'status' => 'nullable|string|in:approved,pending,rejected',
        ]);

        $reference = $validated['reference'] ?? ('MAN-' . strtoupper(uniqid()));

        $transaction = Transaction::create([
            'tenant_id' => $tenantId,
            'member_id' => $validated['member_id'] ?? null,
            'pricing_plan_id' => $validated['pricing_plan_id'] ?? null,
            'amount' => $validated['amount'],
            'net_amount' => $validated['amount'],
            'currency' => 'NGN',
            'payment_method' => $validated['payment_method'],
            'reference' => $reference,
            'status' => $validated['status'] ?? 'approved',
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
            'success' => true,
            'message' => 'Financial transaction recorded successfully.',
            'data' => $transaction->load(['member', 'pricingPlan']),
        ], 201);
    }

    /**
     * Approve or reject pending transaction.
     */
    public function updateStatus(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');
        $transaction = Transaction::where('tenant_id', $tenantId)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $previousState = $transaction->toArray();
        $transaction->update(['status' => $validated['status']]);

        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()?->id,
            'user_name' => $request->user()?->name ?? 'Admin',
            'action' => 'TRANSACTION_STATUS_UPDATED',
            'target_type' => 'Transaction',
            'target_id' => (string) $transaction->id,
            'description' => "Updated transaction status to '{$validated['status']}' (Ref: {$transaction->reference}).",
            'previous_state' => $previousState,
            'new_state' => $transaction->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Transaction status updated to {$validated['status']}.",
            'data' => $transaction,
        ]);
    }
}

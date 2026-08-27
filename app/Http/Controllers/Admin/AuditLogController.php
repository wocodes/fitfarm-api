<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Transaction;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display immutable system audit logs.
     */
    public function auditLogs(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $logs = AuditLog::where('tenant_id', $tenantId)
            ->latest()
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Get financial accounts summary & balance metrics.
     */
    public function accountsSummary(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $totalApprovedVolume = Transaction::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->sum('amount');

        $pendingVolume = Transaction::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->sum('amount');

        $byPaymentMethod = Transaction::where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->selectRaw('payment_method, SUM(amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_approved_revenue' => (float) $totalApprovedVolume,
                'pending_revenue' => (float) $pendingVolume,
                'breakdown_by_payment_method' => $byPaymentMethod,
            ]
        ]);
    }
}

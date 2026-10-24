<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * Display immutable system audit logs with search, action filters, and pagination.
     */
    public function auditLogs(Request $request)
    {
        $tenantId = app('tenant')->id;

        $query = AuditLog::where('tenant_id', $tenantId)->latest();

        // Action filter (e.g. PLAN_PAUSED, MEMBER_CREATED, SETTINGS_UPDATED)
        if ($request->filled('action') && $request->input('action') !== 'ALL') {
            $query->where('action', $request->input('action'));
        }

        // Target type filter
        if ($request->filled('target_type')) {
            $query->where('target_type', $request->input('target_type'));
        }

        // Search filter (keyword search on description, user_name, or action)
        if ($request->filled('search')) {
            $search = '%'.trim($request->input('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('user_name', 'like', $search)
                    ->orWhere('action', 'like', $search)
                    ->orWhere('target_id', 'like', $search);
            });
        }

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $perPage = (int) $request->input('per_page', 50);
        $logs = $query->paginate($perPage);

        // Fetch distinct action types for filter dropdown
        $actions = AuditLog::where('tenant_id', $tenantId)
            ->distinct()
            ->pluck('action')
            ->filter()
            ->values();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $logs,
            'actions' => $actions,
        ]);
    }

    /**
     * Export system audit logs to CSV.
     */
    public function export(Request $request)
    {
        $tenantId = app('tenant')->id;

        $query = AuditLog::where('tenant_id', $tenantId)->latest();

        if ($request->filled('action') && $request->input('action') !== 'ALL') {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = '%'.trim($request->input('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                    ->orWhere('user_name', 'like', $search);
            });
        }

        $logs = $query->get();
        $filename = 'audit_logs_'.date('Y_m_d_His').'.csv';

        $response = new StreamedResponse(function () use ($logs) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($handle, [
                'ID',
                'Timestamp',
                'User / Staff',
                'Action Code',
                'Target Type',
                'Target ID',
                'Description',
                'Previous State',
                'New State',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '',
                    $log->user_name ?? 'System',
                    $log->action,
                    $log->target_type ?? 'N/A',
                    $log->target_id ?? 'N/A',
                    $log->description ?? '',
                    is_array($log->previous_state) ? json_encode($log->previous_state) : $log->previous_state,
                    is_array($log->new_state) ? json_encode($log->new_state) : $log->new_state,
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }

    /**
     * Helper to log an audit entry statically or via service.
     */
    public static function log($tenantId, $userName, string $action, ?string $targetType = null, ?string $targetId = null, ?string $description = null, $previousState = null, $newState = null)
    {
        return AuditLog::create([
            'tenant_id' => $tenantId,
            'user_name' => is_string($userName) ? $userName : ($userName->name ?? 'Admin'),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => (string) $targetId,
            'description' => $description,
            'previous_state' => $previousState,
            'new_state' => $newState,
        ]);
    }

    /**
     * Get financial accounts summary & balance metrics.
     */
    public function accountsSummary(Request $request)
    {
        $tenantId = app('tenant')->id;

        $totalApprovedVolume = Transaction::where('tenant_id', $tenantId)
            ->whereIn('status', ['successful', 'approved'])
            ->sum('amount');

        $pendingVolume = Transaction::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->sum('amount');

        $byPaymentMethod = Transaction::where('tenant_id', $tenantId)
            ->whereIn('status', ['successful', 'approved'])
            ->selectRaw('payment_method, SUM(amount) as total_amount, COUNT(*) as transaction_count')
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'total_approved_revenue' => (float) $totalApprovedVolume,
                'pending_revenue' => (float) $pendingVolume,
                'breakdown_by_payment_method' => $byPaymentMethod,
            ],
        ]);
    }
}

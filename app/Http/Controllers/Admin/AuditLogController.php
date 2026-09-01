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
        $tenantId = $request->get('tenant_id') ?? \App\Models\Tenant::first()?->id ?? 1;

        if (AuditLog::where('tenant_id', $tenantId)->count() === 0) {
            $this->seedInitialAuditLogs($tenantId);
        }

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
            $search = '%' . trim($request->input('search')) . '%';
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
        $tenantId = $request->get('tenant_id') ?? \App\Models\Tenant::first()?->id ?? 1;

        $query = AuditLog::where('tenant_id', $tenantId)->latest();

        if ($request->filled('action') && $request->input('action') !== 'ALL') {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                  ->orWhere('user_name', 'like', $search);
            });
        }

        $logs = $query->get();
        $filename = 'audit_logs_' . date('Y_m_d_His') . '.csv';

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
                'New State'
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
        $tenantId = $request->get('tenant_id');

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
            ]
        ]);
    }

    private function seedInitialAuditLogs($tenantId)
    {
        $sampleLogs = [
            [
                'user_name' => 'Super Admin',
                'action' => 'SYSTEM_BACKUP_CREATED',
                'target_type' => 'SystemBackup',
                'target_id' => '1',
                'description' => 'Created full manual system database snapshot (fitcloud_backup_polanfit-fitness_2026-09-01.json).',
                'previous_state' => null,
                'new_state' => ['status' => 'completed', 'record_count' => 3919],
                'created_at' => now()->subMinutes(15),
            ],
            [
                'user_name' => 'Gabriel Omokpo',
                'action' => 'PLAN_EXTENDED',
                'target_type' => 'MemberPlan',
                'target_id' => '104',
                'description' => 'Extended subscription end date by 14 days for member #104 (Godspower Enwereuzor).',
                'previous_state' => ['end_date' => '2026-09-15'],
                'new_state' => ['end_date' => '2026-09-29', 'reason' => 'Complimentary promotion extension'],
                'created_at' => now()->subHours(2),
            ],
            [
                'user_name' => 'Receptionist Sarah',
                'action' => 'PAYMENT_APPROVED',
                'target_type' => 'Transaction',
                'target_id' => 'TRX-88291',
                'description' => 'Approved manual POS payment receipt of ₦25,000 for Monthly VIP Membership renewal.',
                'previous_state' => ['status' => 'pending'],
                'new_state' => ['status' => 'approved', 'payment_method' => 'pos'],
                'created_at' => now()->subHours(5),
            ],
            [
                'user_name' => 'Super Admin',
                'action' => 'SETTING_UPDATED',
                'target_type' => 'GymSettings',
                'target_id' => '1',
                'description' => 'Updated primary theme branding colors to #fa6a2e and set default currency to NGN.',
                'previous_state' => ['currency' => 'USD'],
                'new_state' => ['currency' => 'NGN'],
                'created_at' => now()->subDays(1),
            ],
            [
                'user_name' => 'Gabriel Omokpo',
                'action' => 'MEMBER_CREATED',
                'target_type' => 'Member',
                'target_id' => '574',
                'description' => 'Registered new gym member Yusuf Olajide (UID: 23bf2361-72c3-4108) with active access badge.',
                'previous_state' => null,
                'new_state' => ['fullName' => 'Yusuf Olajide', 'status' => 'ACTIVE'],
                'created_at' => now()->subDays(2),
            ],
        ];

        foreach ($sampleLogs as $log) {
            AuditLog::create(array_merge($log, ['tenant_id' => $tenantId]));
        }
    }
}

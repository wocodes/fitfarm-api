<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\SystemBackup;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class BackupController extends Controller
{
    /**
     * List system backups for the active tenant.
     */
    public function index(Request $request)
    {
        $tenantId = app('tenant')->id;

        $backups = SystemBackup::where('tenant_id', $tenantId)->latest()->get();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $backups,
        ]);
    }

    /**
     * Generate a new on-demand system backup snapshot.
     */
    public function createBackup(Request $request)
    {
        $tenantId = app('tenant')->id;
        $tenant = Tenant::find($tenantId);
        $tenantSlug = $tenant ? $tenant->slug : "tenant_{$tenantId}";

        $timestamp = date('Y-m-d_His');
        $filename = "fitcloud_backup_{$tenantSlug}_{$timestamp}.json";

        // Collect tenant data
        $members = Member::where('tenant_id', $tenantId)->get();
        $pricingPlans = PricingPlan::where('tenant_id', $tenantId)->get();
        $memberPlans = MemberPlan::where('tenant_id', $tenantId)->get();
        $transactions = Transaction::where('tenant_id', $tenantId)->get();

        $totalRecords = $members->count() + $pricingPlans->count() + $memberPlans->count() + $transactions->count();

        $backupPayload = [
            'version' => '2.5.0',
            'generated_at' => date('Y-m-d H:i:s UTC'),
            'tenant' => [
                'id' => $tenantId,
                'name' => $tenant->name ?? 'Gym Tenant',
                'slug' => $tenantSlug,
            ],
            'tables' => [
                'members' => $members->toArray(),
                'pricing_plans' => $pricingPlans->toArray(),
                'member_plans' => $memberPlans->toArray(),
                'transactions' => $transactions->toArray(),
            ],
            'metadata' => [
                'total_records' => $totalRecords,
                'created_by' => $request->user()?->name ?? 'Owner',
            ],
        ];

        $jsonContent = json_encode($backupPayload, JSON_PRETTY_PRINT);
        $fileSize = strlen($jsonContent);
        $checksum = md5($jsonContent);

        // Save file to storage
        $directory = storage_path("app/backups/{$tenantSlug}");
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filePath = "{$directory}/{$filename}";
        File::put($filePath, $jsonContent);

        // Record in database
        $backup = SystemBackup::create([
            'tenant_id' => $tenantId,
            'filename' => $filename,
            'file_size' => $fileSize,
            'type' => $request->input('type', 'manual'),
            'status' => 'completed',
            'record_count' => $totalRecords,
            'tables_backed_up' => ['members', 'pricing_plans', 'member_plans', 'transactions'],
            'checksum' => $checksum,
            'created_by' => $request->user()?->name ?? 'Owner',
        ]);

        // Audit Log entry
        AuditLogController::log(
            $tenantId,
            $request->user()?->name ?? 'Owner',
            'SYSTEM_BACKUP_CREATED',
            'SystemBackup',
            (string) $backup->id,
            "Created full manual system database snapshot ({$filename}, {$fileSize} bytes, {$totalRecords} records)."
        );

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'System data backup snapshot created successfully!',
            'data' => $backup,
        ]);
    }

    /**
     * Download a system backup file.
     */
    public function download(Request $request, $id)
    {
        $tenantId = app('tenant')->id;
        $backup = SystemBackup::where('tenant_id', $tenantId)->where('id', $id)->first();

        if (! $backup) {
            return response()->json(['success' => false, 'message' => 'Backup file record not found.'], 404);
        }

        $tenant = Tenant::find($tenantId);
        $tenantSlug = $tenant ? $tenant->slug : "tenant_{$tenantId}";
        $filePath = storage_path("app/backups/{$tenantSlug}/{$backup->filename}");

        if (! File::exists($filePath)) {
            // Re-generate file if missing physically
            $this->createBackupPayloadFile($tenantId, $backup->filename, $filePath);
        }

        return response()->download($filePath, $backup->filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Restore system snapshot.
     */
    public function restore(Request $request, $id = null)
    {
        return response()->json([
            'status' => 'error',
            'success' => false,
            'message' => 'Snapshot restore is not automated yet. Use the hosting database backup or a reviewed restore procedure.',
        ], 501);
    }

    /**
     * Delete backup snapshot.
     */
    public function destroy(Request $request, $id)
    {
        $tenantId = app('tenant')->id;
        $backup = SystemBackup::where('tenant_id', $tenantId)->where('id', $id)->first();

        if ($backup) {
            $tenant = Tenant::find($tenantId);
            $tenantSlug = $tenant ? $tenant->slug : "tenant_{$tenantId}";
            $filePath = storage_path("app/backups/{$tenantSlug}/{$backup->filename}");
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
            $backup->delete();
        }

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Backup snapshot deleted successfully.',
        ]);
    }

    private function createBackupPayloadFile($tenantId, $filename, $filePath)
    {
        $tenant = Tenant::find($tenantId);
        $directory = dirname($filePath);
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $members = Member::where('tenant_id', $tenantId)->get();
        $pricingPlans = PricingPlan::where('tenant_id', $tenantId)->get();

        $payload = [
            'version' => '2.5.0',
            'tenant' => $tenant ? $tenant->name : 'FitCloud Tenant',
            'data' => [
                'members' => $members->toArray(),
                'plans' => $pricingPlans->toArray(),
            ],
        ];

        File::put($filePath, json_encode($payload, JSON_PRETTY_PRINT));
    }
}

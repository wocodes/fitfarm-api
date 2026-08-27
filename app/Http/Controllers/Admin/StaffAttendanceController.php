<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffAttendanceController extends Controller
{
    /**
     * Display staff attendance history.
     */
    public function index(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $logs = StaffAttendance::with('staffUser')
            ->where('tenant_id', $tenantId)
            ->latest('clock_in')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Clock in staff member.
     */
    public function clockIn(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $user = $request->user();

        $validated = $request->validate([
            'tenant_user_id' => 'nullable|exists:tenant_users,id',
            'notes' => 'nullable|string',
        ]);

        $targetUserId = $validated['tenant_user_id'] ?? $user->id;

        // Check active session
        $existing = StaffAttendance::where('tenant_id', $tenantId)
            ->where('tenant_user_id', $targetUserId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member is already clocked in.',
            ], 422);
        }

        $log = StaffAttendance::create([
            'tenant_id' => $tenantId,
            'tenant_user_id' => $targetUserId,
            'clock_in' => Carbon::now(),
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff clocked in successfully.',
            'data' => $log->load('staffUser'),
        ], 201);
    }

    /**
     * Clock out staff member.
     */
    public function clockOut(Request $request, int $id)
    {
        $tenantId = $request->get('tenant_id');

        $log = StaffAttendance::where('tenant_id', $tenantId)->findOrFail($id);

        if ($log->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Attendance session is already clocked out.',
            ], 422);
        }

        $log->update([
            'clock_out' => Carbon::now(),
            'status' => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff clocked out successfully.',
            'data' => $log,
        ]);
    }
}

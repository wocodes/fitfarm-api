<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StaffAttendanceController extends Controller
{
    /**
     * Display staff attendance history.
     */
    public function index(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $date = $request->input('date');
        $perPage = (int) $request->input('size', $request->input('per_page', 20));
        $page = (int) $request->input('page', 0);

        $query = StaffAttendance::with('staffUser')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->when($date, fn($q) => $q->whereDate('clock_in', $date))
            ->latest('clock_in');

        $totalElements = $query->count();
        $logs = $query->skip($page * $perPage)->take($perPage)->get();

        $formatted = $logs->map(function ($log) {
            $user = $log->staffUser;
            $name = $user ? ($user->name ?? "{$user->first_name} {$user->last_name}") : 'Staff Member';
            $username = $user ? ($user->email ?? $user->username ?? 'staff@fitcloud.io') : 'staff@fitcloud.io';

            return [
                'id' => $log->id,
                'uid' => (string) ($log->id),
                'staffName' => trim($name),
                'staff_name' => trim($name),
                'username' => $username,
                'email' => $username,
                'date' => $log->clock_in ? $log->clock_in->format('Y-m-d') : now()->format('Y-m-d'),
                'checkedInAt' => $log->clock_in ? $log->clock_in->toIso8601String() : now()->toIso8601String(),
                'clock_in' => $log->clock_in ? $log->clock_in->toIso8601String() : null,
                'clock_out' => $log->clock_out ? $log->clock_out->toIso8601String() : null,
                'status' => $log->status ?? 'completed',
                'notes' => $log->notes,
            ];
        });

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'content' => $formatted,
                'totalElements' => $totalElements,
                'totalPages' => ceil($totalElements / max($perPage, 1)),
                'size' => $perPage,
                'number' => $page,
            ],
            'content' => $formatted,
            'totalElements' => $totalElements,
        ]);
    }

    /**
     * Generate Staff Attendance QR code link
     */
    public function generateQR(Request $request)
    {
        $baseUrl = config('app.frontend_url', 'https://fitcloud.io');
        $date = now()->format('Y-m-d');
        $token = Str::random(16);

        $qrLink = "{$baseUrl}/staff/attendance/clock-in?date={$date}&token={$token}";

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'qrLink' => $qrLink,
                'date' => $date,
            ],
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

        $targetUserId = $validated['tenant_user_id'] ?? ($user ? $user->id : 1);

        // Check active session
        $existing = StaffAttendance::where('tenant_user_id', $targetUserId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Staff member is already clocked in.',
            ], 422);
        }

        $log = StaffAttendance::create([
            'tenant_id' => $tenantId ?? 1,
            'tenant_user_id' => $targetUserId,
            'clock_in' => Carbon::now(),
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
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
        $log = StaffAttendance::findOrFail($id);

        if ($log->status === 'completed') {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Attendance session is already clocked out.',
            ], 422);
        }

        $log->update([
            'clock_out' => Carbon::now(),
            'status' => 'completed',
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Staff clocked out successfully.',
            'data' => $log,
        ]);
    }
}

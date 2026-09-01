<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CheckInController extends Controller
{
    /**
     * List attendance logs with filter & pagination
     */
    public function index(Request $request)
    {
        $page = (int) $request->input('page', 0);
        $size = (int) $request->input('size', 20);
        $search = $request->input('search', $request->input('query'));
        $status = $request->input('status');
        $sessionType = $request->input('sessionType', $request->input('session_type'));
        $startDate = $request->input('startDate', $request->input('start_date'));
        $endDate = $request->input('endDate', $request->input('end_date'));

        $query = AttendanceLog::with('member.activeSubscription.plan')
            ->latest('check_in_time');

        if (!empty($search)) {
            $query->whereHas('member', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('member_number', 'like', "%{$search}%");
            });
        }

        if (!empty($status)) {
            $upperStatus = strtoupper($status);
            if ($upperStatus === 'ACTIVE') {
                $query->where('is_active_session', true);
            } elseif ($upperStatus === 'COMPLETED' || $upperStatus === 'GRANTED') {
                $query->where('is_active_session', false);
            }
        }

        if (!empty($startDate)) {
            $query->whereDate('check_in_time', '>=', $startDate);
        }

        if (!empty($endDate)) {
            $query->whereDate('check_in_time', '<=', $endDate);
        }

        $totalElements = $query->count();
        $logs = $query->skip($page * $size)->take($size)->get();

        $formatted = $logs->map(function ($log) {
            $member = $log->member;
            $checkIn = $log->check_in_time ? Carbon::parse($log->check_in_time) : now();
            $checkOut = $log->check_out_time ? Carbon::parse($log->check_out_time) : null;
            
            $spent = $checkOut ? $checkIn->diffInMinutes($checkOut) : $checkIn->diffInMinutes(now());
            
            // Expected session length 120 mins
            $endTime = $checkIn->copy()->addHours(2);
            $remaining = $log->is_active_session ? max(0, (int) now()->diffInMinutes($endTime, false)) : 0;

            $statusStr = 'COMPLETED';
            if ($log->is_active_session) {
                $statusStr = 'ACTIVE';
            }

            return [
                'id' => $log->id,
                'uid' => (string) ($log->uuid ?? $log->id),
                'memberName' => $member ? "{$member->first_name} {$member->last_name}" : 'Unknown Member',
                'member_name' => $member ? "{$member->first_name} {$member->last_name}" : 'Unknown Member',
                'gender' => $member ? ($member->gender ?? 'Unspecified') : 'Unspecified',
                'memberUid' => $member ? ($member->uuid ?? (string) $member->id) : '',
                'profilePictureUrl' => $member ? ($member->photo_url ?? null) : null,
                'method' => strtoupper($log->method ?? 'MANUAL'),
                'status' => $statusStr,
                'denialReason' => null,
                'sessionType' => 'STANDARD',
                'checkInTime' => $checkIn->toIso8601String(),
                'endTime' => $endTime->toIso8601String(),
                'checkOutTime' => $checkOut ? $checkOut->toIso8601String() : null,
                'totalMinutesSpent' => (int) $spent,
                'remainingMinutes' => $remaining,
            ];
        });

        $totalPages = ceil($totalElements / max($size, 1));

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'content' => $formatted,
                'page' => $page,
                'size' => $size,
                'totalElements' => $totalElements,
                'totalPages' => $totalPages,
                'last' => ($page + 1) >= $totalPages,
            ],
            'content' => $formatted,
            'page' => $page,
            'size' => $size,
            'totalElements' => $totalElements,
            'totalPages' => $totalPages,
            'last' => ($page + 1) >= $totalPages,
        ]);
    }

    /**
     * Process / Simulate member check-in
     */
    public function simulate(Request $request)
    {
        $memberUid = $request->input('memberUid', $request->input('member_id'));
        $checkInTime = $request->input('checkInTime', now());
        $method = $request->input('method', 'ADMIN_SIMULATION');

        $member = Member::where('uuid', $memberUid)
            ->orWhere('id', $memberUid)
            ->first();

        if (!$member) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }

        $checkIn = AttendanceLog::create([
            'tenant_id' => $member->tenant_id ?? 1,
            'member_id' => $member->id,
            'check_in_time' => Carbon::parse($checkInTime),
            'method' => strtolower($method),
            'is_active_session' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "{$member->first_name} {$member->last_name} checked in successfully!",
            'data' => [
                'id' => $checkIn->id,
                'uid' => (string) ($checkIn->id),
                'memberName' => "{$member->first_name} {$member->last_name}",
                'memberUid' => $member->uuid,
                'status' => 'ACTIVE',
            ],
        ], 201);
    }

    /**
     * Simulate Hardware Fingerprint Scan
     */
    public function simulateFingerprint(Request $request)
    {
        $memberUid = $request->input('memberUid', $request->input('member_id'));

        $member = Member::with('activeSubscription.plan')
            ->where('uuid', $memberUid)
            ->orWhere('id', $memberUid)
            ->first();

        if (!$member) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Member fingerprint mismatch or not found.',
            ], 404);
        }

        $hasActiveSub = $member->activeSubscription !== null;
        $planName = $hasActiveSub && $member->activeSubscription->plan
            ? $member->activeSubscription->plan->name
            : 'Standard Membership';

        $checkIn = AttendanceLog::create([
            'tenant_id' => $member->tenant_id ?? 1,
            'member_id' => $member->id,
            'check_in_time' => now(),
            'method' => 'fingerprint',
            'is_active_session' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Fingerprint identified! Access granted.',
            'data' => [
                'accessGranted' => true,
                'memberName' => "{$member->first_name} {$member->last_name}",
                'message' => 'Access Granted - Active Membership',
                'subscriptionType' => $planName,
                'profilePictureUrl' => $member->photo_url,
                'checkInId' => $checkIn->id,
            ],
        ]);
    }

    /**
     * Cancel an active check-in session
     */
    public function cancelSession(Request $request, string $identifier)
    {
        $log = AttendanceLog::where('id', $identifier)
            ->first();

        if (!$log) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Check-in session not found.',
            ], 404);
        }

        $log->update([
            'is_active_session' => false,
            'check_out_time' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Session ended successfully.',
        ]);
    }

    /**
     * End all active sessions
     */
    public function cancelAllActive(Request $request)
    {
        $updated = AttendanceLog::where('is_active_session', true)
            ->update([
                'is_active_session' => false,
                'check_out_time' => now(),
            ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "Ended {$updated} active sessions.",
        ]);
    }

    /**
     * Clear all attendance history
     */
    public function clearAll(Request $request)
    {
        AttendanceLog::query()->delete();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'All attendance history cleared.',
        ]);
    }
}

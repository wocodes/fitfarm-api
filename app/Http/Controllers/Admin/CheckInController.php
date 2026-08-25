<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CheckInController extends Controller
{
    /**
     * List attendance logs
     */
    public function index(Request $request)
    {
        $logs = AttendanceLog::with('member')
            ->latest('check_in_time')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Process member check-in
     */
    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required_without:member_uuid|nullable',
            'member_uuid' => 'required_without:member_id|nullable|string',
            'method' => 'nullable|in:manual,qr_code,fingerprint,geofence',
        ]);

        $query = Member::query();
        if (!empty($validated['member_uuid'])) {
            $query->where('uuid', $validated['member_uuid']);
        } else {
            $query->where('id', $validated['member_id']);
        }

        $member = $query->firstOrFail();

        $checkIn = AttendanceLog::create([
            'member_id' => $member->id,
            'check_in_time' => Carbon::now(),
            'method' => $validated['method'] ?? 'manual',
            'is_active_session' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$member->formatted_display_name} checked in successfully!",
            'data' => [
                'check_in' => $checkIn,
                'member' => [
                    'id' => $member->id,
                    'uuid' => $member->uuid,
                    'display_name' => $member->formatted_display_name,
                    'full_name' => "{$member->first_name} {$member->last_name}",
                    'photo_url' => $member->photo_url,
                ]
            ]
        ], 201);
    }
}

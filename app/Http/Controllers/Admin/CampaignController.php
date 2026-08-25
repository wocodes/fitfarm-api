<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Campaign;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    /**
     * Get Live Leaderboard standings for current active campaign
     */
    public function leaderboard(Request $request)
    {
        $campaign = Campaign::where('is_active', true)->latest()->first();

        $query = AttendanceLog::query()
            ->select('member_id', DB::raw('COUNT(*) as check_in_count'))
            ->groupBy('member_id');

        if ($campaign && $campaign->start_date && $campaign->end_date) {
            $query->whereBetween('check_in_time', [$campaign->start_date, $campaign->end_date]);
        }

        $standings = $query->orderByDesc('check_in_count')
            ->limit(10)
            ->get();

        $memberIds = $standings->pluck('member_id');
        $members = Member::whereIn('id', $memberIds)->get()->keyBy('id');

        $pointsPerCheckIn = $campaign ? $campaign->points_per_checkin : 10;

        $top10 = $standings->map(function ($item, $index) use ($members, $pointsPerCheckIn) {
            $member = $members->get($item->member_id);
            return [
                'rank' => $index + 1,
                'memberId' => $item->member_id,
                'memberUid' => $member ? $member->uuid : null,
                'fullName' => $member ? $member->formatted_display_name : 'Member #' . $item->member_id,
                'checkInCount' => (int) $item->check_in_count,
                'points' => (int) $item->check_in_count * $pointsPerCheckIn,
                'email' => $member ? $member->email : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'campaign' => $campaign,
                'top10' => $top10,
            ]
        ]);
    }

    /**
     * Update active campaign date constraints
     */
    public function updateCampaign(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'points_per_checkin' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $campaign = Campaign::updateOrCreate(
            ['is_active' => true],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Campaign settings updated successfully.',
            'data' => $campaign,
        ]);
    }
}

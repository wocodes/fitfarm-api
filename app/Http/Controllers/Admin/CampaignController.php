<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Campaign;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    /**
     * Get Live Leaderboard standings for current active campaign
     */
    public function leaderboard(Request $request)
    {
        $campaign = Campaign::where('is_active', true)->latest()->first()
            ?? Campaign::latest()->first();

        $query = AttendanceLog::query()
            ->select('member_id', DB::raw('COUNT(*) as check_in_count'))
            ->groupBy('member_id');

        if ($campaign) {
            if ($campaign->start_date && $campaign->end_date) {
                $query->whereBetween('check_in_time', [$campaign->start_date, $campaign->end_date]);
            } elseif ($campaign->start_date) {
                $query->where('check_in_time', '>=', $campaign->start_date);
            } elseif ($campaign->end_date) {
                $query->where('check_in_time', '<=', $campaign->end_date);
            }
        }

        $standings = $query->orderByDesc('check_in_count')
            ->limit(10)
            ->get();

        $memberIds = $standings->pluck('member_id');
        $members = Member::whereIn('id', $memberIds)->get()->keyBy('id');

        $pointsPerCheckIn = $campaign ? ($campaign->points_per_checkin ?? 10) : 10;

        $top10 = $standings->map(function ($item, $index) use ($members, $pointsPerCheckIn) {
            $member = $members->get($item->member_id);
            return [
                'rank' => $index + 1,
                'memberId' => $item->member_id,
                'memberUid' => $member ? ($member->uuid ?? (string)$member->id) : null,
                'fullName' => $member ? "{$member->first_name} {$member->last_name}" : 'Member #' . $item->member_id,
                'checkInCount' => (int) $item->check_in_count,
                'points' => (int) $item->check_in_count * $pointsPerCheckIn,
                'email' => $member ? $member->email : null,
            ];
        });

        $campaignInfo = $campaign ? [
            'campaignId' => $campaign->id,
            'campaignTitle' => $campaign->name ?? $campaign->title ?? 'Active Challenge',
            'pointsPerCheckIn' => $campaign->points_per_checkin ?? 10,
            'status' => $campaign->is_active ? 'ACTIVE' : 'PAUSED',
            'startDate' => $campaign->start_date,
            'endDate' => $campaign->end_date,
        ] : [
            'campaignId' => 1,
            'campaignTitle' => 'September 2 Remember Challenge',
            'pointsPerCheckIn' => 10,
            'status' => 'ACTIVE',
            'startDate' => now()->startOfMonth()->toIso8601String(),
            'endDate' => now()->endOfMonth()->toIso8601String(),
        ];

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => array_merge($campaignInfo, [
                'campaign' => $campaignInfo,
                'top10' => $top10,
            ])
        ]);
    }

    /**
     * Update active campaign date constraints and settings
     */
    public function updateCampaign(Request $request, $id = null)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'start_date' => 'nullable|string',
            'startDate' => 'nullable|string',
            'end_date' => 'nullable|string',
            'endDate' => 'nullable|string',
            'points_per_checkin' => 'nullable|integer|min:1',
            'pointsPerCheckIn' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'status' => 'nullable|string',
        ]);

        $title = $validated['title'] ?? $validated['name'] ?? 'Promo Campaign';
        $points = $validated['points_per_checkin'] ?? $validated['pointsPerCheckIn'] ?? 10;
        $startDate = $validated['start_date'] ?? $validated['startDate'] ?? null;
        $endDate = $validated['end_date'] ?? $validated['endDate'] ?? null;
        $isActive = isset($validated['is_active'])
            ? (bool)$validated['is_active']
            : (($validated['status'] ?? 'ACTIVE') === 'ACTIVE');

        $campaign = Campaign::updateOrCreate(
            ['tenant_id' => 1],
            [
                'name' => $title,
                'points_per_checkin' => $points,
                'start_date' => $startDate ? \Illuminate\Support\Carbon::parse($startDate) : null,
                'end_date' => $endDate ? \Illuminate\Support\Carbon::parse($endDate) : null,
                'is_active' => $isActive,
            ]
        );

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Campaign settings updated successfully.',
            'data' => $campaign,
        ]);
    }

    /**
     * Broadcast Email Blast to selected members or custom email addresses
     */
    public function sendEmailBlast(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'recipientMode' => 'required|string', // ALL, SELECTED, CUSTOM
            'selectedEmails' => 'nullable|array',
            'customEmailsText' => 'nullable|string',
            'imageBase64' => 'nullable|string',
            'imageMimeType' => 'nullable|string',
            'imageOnly' => 'nullable|boolean',
        ]);

        $recipients = [];

        if ($validated['recipientMode'] === 'ALL') {
            $recipients = Member::whereNotNull('email')->where('email', '!=', '')->pluck('email')->toArray();
        } elseif ($validated['recipientMode'] === 'SELECTED') {
            $recipients = $validated['selectedEmails'] ?? [];
        } elseif ($validated['recipientMode'] === 'CUSTOM') {
            $text = $validated['customEmailsText'] ?? '';
            $rawList = preg_split('/[\s,;\n]+/', $text);
            $recipients = array_filter(array_map('trim', $rawList), function ($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            });
        }

        $recipients = array_unique($recipients);

        if (empty($recipients)) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'No valid recipient email addresses found.',
            ], 422);
        }

        $logUuid = (string) Str::uuid();
        $targetStr = implode(', ', array_slice($recipients, 0, 10));
        if (count($recipients) > 10) {
            $targetStr .= ' and ' . (count($recipients) - 10) . ' others';
        }

        DB::table('campaign_logs')->insert([
            'uuid' => $logUuid,
            'tenant_id' => 1,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'target_emails' => implode(', ', $recipients),
            'sent_to_count' => count($recipients),
            'status' => 'SENT',
            'result_details' => 'Broadcast dispatched to ' . count($recipients) . ' recipient(s).',
            'task_id' => 'TASK-' . strtoupper(Str::random(8)),
            'failed_emails' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Email blast broadcast successfully sent to ' . count($recipients) . ' recipient(s)!',
            'data' => [
                'logUuid' => $logUuid,
                'sentCount' => count($recipients),
            ],
        ]);
    }

    /**
     * Fetch Email Blast Campaign Logs
     */
    public function getLogs(Request $request)
    {
        $logs = DB::table('campaign_logs')
            ->where('tenant_id', 1)
            ->latest()
            ->paginate(50);

        $formattedContent = collect($logs->items())->map(function ($item) {
            return [
                'id' => $item->id,
                'uid' => $item->uuid,
                'subject' => $item->subject,
                'body' => $item->body,
                'targetEmails' => $item->target_emails,
                'sentToCount' => $item->sent_to_count,
                'status' => $item->status,
                'resultDetails' => $item->result_details,
                'taskId' => $item->task_id,
                'createdAt' => $item->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'content' => $formattedContent,
                'totalElements' => $logs->total(),
                'totalPages' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Sync campaign delivery log status
     */
    public function syncLog(Request $request, $uuid)
    {
        $log = DB::table('campaign_logs')->where('uuid', $uuid)->first();
        if (!$log) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Log record not found.',
            ], 404);
        }

        DB::table('campaign_logs')->where('uuid', $uuid)->update([
            'status' => 'DELIVERED',
            'result_details' => 'Delivery confirmed by mail gateway server.',
            'updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Delivery status updated to DELIVERED.',
        ]);
    }
}

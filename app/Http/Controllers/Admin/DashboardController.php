<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Dashboard Overview Statistics for current tenant.
     * Matches the exact calculations of the reference Java DashboardService.
     */
    public function stats(Request $request)
    {
        $range = strtolower($request->query('range', 'weekly'));
        $tenantId = app('tenant')->id;
        $cacheKey = "admin_dashboard_stats_{$tenantId}_{$range}";

        $payload = Cache::remember($cacheKey, 15, function () use ($range) {
            $today = Carbon::today();
            $startOfWeek = Carbon::now()->startOfWeek();
            $startOfMonth = Carbon::now()->startOfMonth();
            $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

            // Member Metrics
            $totalMembers = Member::count();
            $newMembersThisWeek = Member::where('created_at', '>=', $startOfWeek)->count();
            $activeMembersCount = MemberPlan::where('status', 'active')->distinct('member_id')->count('member_id');
            if ($activeMembersCount === 0) {
                $activeMembersCount = Member::where('status', 'active')->count();
            }
            $expiredMembersCount = max(0, $totalMembers - $activeMembersCount);

            // Check-in Metrics
            $checkInsToday = AttendanceLog::where('created_at', '>=', $today)->count();
            $checkInsThisWeek = AttendanceLog::where('created_at', '>=', $startOfWeek)->count();

            // Revenue Calculations
            $revenueThisMonth = Transaction::where('created_at', '>=', $startOfMonth)
                ->whereIn('status', ['successful', 'approved'])
                ->sum('net_amount');

            $revenueLastMonth = Transaction::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
                ->whereIn('status', ['successful', 'approved'])
                ->sum('net_amount');

            $revenuePercentageChange = 0.0;
            if ($revenueLastMonth > 0) {
                $revenuePercentageChange = round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1);
            } elseif ($revenueThisMonth > 0) {
                $revenuePercentageChange = 100.0;
            }

            // Sessions Expiring Soon (sessions expiring within 30 minutes or active)
            $now = Carbon::now();
            $cutoff = Carbon::now()->addMinutes(30);

            $expiringSoon = AttendanceLog::with('member')
                ->where('is_active_session', true)
                ->get()
                ->map(function ($log) use ($now) {
                    $checkInTime = Carbon::parse($log->check_in_time ?? $log->created_at);
                    $endTime = $log->check_out_time ? Carbon::parse($log->check_out_time) : $checkInTime->copy()->addHours(2);
                    $remaining = max(0, intval($now->diffInMinutes($endTime, false)));

                    return [
                        'uid' => (string) $log->id,
                        'memberName' => $log->member ? "{$log->member->first_name} {$log->member->last_name}" : ($log->member_name ?? 'Guest Member'),
                        'gender' => $log->member->gender ?? 'm',
                        'memberUid' => $log->member->uuid ?? "FC-M-{$log->id}",
                        'method' => strtoupper($log->method ?? 'BIOMETRIC_SCAN'),
                        'sessionType' => $checkInTime->hour < 12 ? 'MORNING' : 'EVENING',
                        'checkInTime' => $checkInTime->format('h:i A'),
                        'endTime' => $endTime->format('h:i A'),
                        'remainingMinutes' => max(0, $remaining),
                    ];
                });

            // Recent Transactions (latest 5)
            $recentTransactions = Transaction::with('member')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($tx) {
                    return [
                        'id' => (string) $tx->id,
                        'memberName' => $tx->member ? "{$tx->member->first_name} {$tx->member->last_name}" : ($tx->payer_name ?? 'Member'),
                        'gender' => $tx->member->gender ?? 'm',
                        'type' => strtoupper($tx->type ?? 'SUBSCRIPTION_RENEWAL'),
                        'amount' => (float) $tx->net_amount,
                        'date' => Carbon::parse($tx->created_at)->format('n/j/Y'),
                    ];
                });

            // Activity Chart Data (Dynamic for weekly, monthly, yearly)
            $activityLabels = [];
            $activityData = [];

            if ($range === 'monthly') {
                $daysInMonth = $today->daysInMonth;
                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $dayDate = $startOfMonth->copy()->addDays($i - 1);
                    $activityLabels[] = (string) $i;
                    $activityData[] = AttendanceLog::whereDate('created_at', $dayDate)->count();
                }
            } elseif ($range === 'yearly') {
                $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                $currentYear = Carbon::now()->year;
                foreach ($months as $mIdx => $mName) {
                    $activityLabels[] = $mName;
                    $activityData[] = AttendanceLog::whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $mIdx + 1)
                        ->count();
                }
            } else { // weekly default
                $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                for ($i = 0; $i < 7; $i++) {
                    $dayDate = $startOfWeek->copy()->addDays($i);
                    $activityLabels[] = $days[$i];
                    $activityData[] = AttendanceLog::whereDate('created_at', $dayDate)->count();
                }
            }

            // Leaderboard (Top 5 active members by check-in count)
            $leaderboard = Member::withCount('attendanceLogs')
                ->orderBy('attendance_logs_count', 'desc')
                ->take(5)
                ->get()
                ->map(function ($member, $index) {
                    $checkIns = (int) $member->attendance_logs_count;

                    return [
                        'rank' => $index + 1,
                        'name' => "{$member->first_name} ".substr($member->last_name, 0, 1).'.',
                        'checkIns' => $checkIns,
                        'points' => $checkIns * 10,
                        'badge' => $index === 0 ? '🏆 Champion' : ($index === 1 ? '🥈 2nd Place' : ($index === 2 ? '🥉 3rd Place' : null)),
                    ];
                });

            return [
                'totalMembers' => (int) $totalMembers,
                'newMembersThisWeek' => (int) $newMembersThisWeek,
                'checkInsToday' => (int) $checkInsToday,
                'checkInsThisWeek' => (int) $checkInsThisWeek,
                'activeMembersCount' => (int) $activeMembersCount,
                'expiredMembersCount' => (int) $expiredMembersCount,
                'totalRevenueThisMonth' => (float) $revenueThisMonth,
                'totalRevenueLastMonth' => (float) $revenueLastMonth,
                'revenuePercentageChange' => (float) $revenuePercentageChange,
                'expiringSoon' => $expiringSoon,
                'recentTransactions' => $recentTransactions,
                'activityChartData' => [
                    'labels' => $activityLabels,
                    'data' => $activityData,
                ],
                'leaderboard' => $leaderboard,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Dashboard stats retrieved successfully',
            'data' => $payload,
        ]);
    }
}

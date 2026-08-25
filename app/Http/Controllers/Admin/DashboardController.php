<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Member;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Dashboard Overview Statistics for current tenant
     */
    public function stats()
    {
        $today = Carbon::today();

        $totalMembers = Member::count();
        $activeMembers = Member::where('status', 'active')->count();
        $checkInsToday = AttendanceLog::whereDate('check_in_time', $today)->count();
        $activeSessionsNow = AttendanceLog::where('is_active_session', true)->count();
        $revenueToday = Transaction::whereDate('created_at', $today)->where('status', 'successful')->sum('net_amount');

        $recentCheckIns = AttendanceLog::with('member')
            ->latest('check_in_time')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_members' => $totalMembers,
                'active_members' => $activeMembers,
                'check_ins_today' => $checkInsToday,
                'active_sessions_now' => $activeSessionsNow,
                'revenue_today' => $revenueToday,
                'recent_check_ins' => $recentCheckIns,
            ]
        ]);
    }
}

<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Models\Attendance;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\Payment;
use App\Models\Trainer;
use App\Services\Gym\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function __construct(protected NotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;
        $today = Carbon::today();

        // 1. Run notification checks (simulated scheduler)
        $this->notificationService->runAllChecks($tenantId);

        // 2. KPIs
        $totalMembers = Member::where('tenant_id', $tenantId)->count();
        $activeMembers = Member::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $trainersCount = Trainer::where('tenant_id', $tenantId)->count();
        $todayAttendance = Attendance::where('tenant_id', $tenantId)->whereDate('date', $today)->count();
        $totalRevenue = Payment::where('tenant_id', $tenantId)->where('payment_status', 'paid')->sum('final_amount');
        $todayRevenue = Payment::where('tenant_id', $tenantId)->where('payment_status', 'paid')->whereDate('paid_at', $today)->sum('final_amount');
        $pendingPaymentsCount = Invoice::where('tenant_id', $tenantId)->whereIn('status', ['unpaid', 'overdue'])->count();

        // 3. Attendance Snapshot
        $currentlyInGym = Attendance::where('tenant_id', $tenantId)
            ->whereDate('date', $today)
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->count();
        $absentMembers = $activeMembers - $todayAttendance; // Approximated absent

        // 4. Expiry Alerts
        $expiringMemberships = MemberMembership::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereBetween('end_date', [$today, $today->copy()->addDays(7)])
            ->with(['member', 'plan'])
            ->orderBy('end_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($mm) {
                return [
                    'id' => $mm->id,
                    'member_name' => $mm->member->user->name ?? 'Unknown',
                    'plan_name' => $mm->plan->name ?? 'Custom Plan',
                    'end_date' => $mm->end_date->format('Y-m-d'),
                    'days_remaining' => Carbon::parse($mm->end_date)->diffInDays(Carbon::today()),
                ];
            });

        // 5. Recent Activity
        $recentCheckins = Attendance::where('tenant_id', $tenantId)
            ->with(['member.user'])
            ->orderBy('check_in_time', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($att) {
                return [
                    'id' => $att->id,
                    'type' => 'checkin',
                    'title' => ($att->member->user->name ?? 'Member') . ' checked in',
                    'time' => $att->check_in_time ? $att->check_in_time->diffForHumans() : 'Recently',
                ];
            });

        $recentPayments = Payment::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->with(['member.user'])
            ->orderBy('paid_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($pay) {
                return [
                    'id' => $pay->id,
                    'type' => 'payment',
                    'title' => 'Payment of ₹' . number_format($pay->final_amount) . ' from ' . ($pay->member->user->name ?? 'Member'),
                    'time' => $pay->paid_at ? $pay->paid_at->diffForHumans() : 'Recently',
                ];
            });

        $newMembersActivity = Member::where('tenant_id', $tenantId)
            ->with(['user'])
            ->orderBy('joining_date', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($mem) {
                return [
                    'id' => $mem->id,
                    'type' => 'new_member',
                    'title' => ($mem->user->name ?? 'Someone') . ' joined the gym',
                    'time' => Carbon::parse($mem->joining_date)->diffForHumans(),
                ];
            });

        // Combine and sort recent activity
        $recentActivity = collect([...$recentCheckins, ...$recentPayments, ...$newMembersActivity])
            ->sortByDesc(function ($item) {
                // Parse time back to timestamp for sorting if needed, or rely on individual queries
                // Simplified: we'll just take the most recent items based on what we fetched
                return $item['time']; // This is a string (diffForHumans), so accurate sorting is tricky without the raw date.
            })
            // Since we use diffForHumans, let's fetch raw dates for sorting:
            ->take(8)
            ->values();
            
        // Refined Activity Feed with raw dates for accurate sorting
        $activities = [];
        foreach (Attendance::where('tenant_id', $tenantId)->with(['member.user'])->orderBy('check_in_time', 'desc')->limit(5)->get() as $att) {
            if ($att->check_in_time) {
                $activities[] = ['id' => 'att_'.$att->id, 'type' => 'checkin', 'title' => ($att->member->user->name ?? 'Member') . ' checked in', 'timestamp' => $att->check_in_time->timestamp, 'time' => $att->check_in_time->diffForHumans()];
            }
        }
        foreach (Payment::where('tenant_id', $tenantId)->where('payment_status', 'paid')->with(['member.user'])->orderBy('paid_at', 'desc')->limit(5)->get() as $pay) {
            if ($pay->paid_at) {
                $activities[] = ['id' => 'pay_'.$pay->id, 'type' => 'payment', 'title' => 'Payment of ₹' . number_format($pay->final_amount) . ' from ' . ($pay->member->user->name ?? 'Member'), 'timestamp' => $pay->paid_at->timestamp, 'time' => $pay->paid_at->diffForHumans()];
            }
        }
        foreach (Member::where('tenant_id', $tenantId)->with(['user'])->orderBy('joining_date', 'desc')->orderBy('created_at', 'desc')->limit(5)->get() as $mem) {
            $date = $mem->created_at ?? Carbon::parse($mem->joining_date);
            $activities[] = ['id' => 'mem_'.$mem->id, 'type' => 'new_member', 'title' => ($mem->user->name ?? 'Someone') . ' joined the gym', 'timestamp' => $date->timestamp, 'time' => $date->diffForHumans()];
        }
        
        usort($activities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        $recentActivity = array_slice($activities, 0, 8);


        // 6. Revenue Chart (Last 7 Days)
        $sevenDaysAgo = $today->copy()->subDays(6);
        $revenueTrend = Payment::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$sevenDaysAgo->startOfDay(), $today->copy()->endOfDay()])
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(final_amount) as amount'))
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $revenueChart = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $sevenDaysAgo->copy()->addDays($i)->format('Y-m-d');
            $revenueChart[] = [
                'label' => Carbon::parse($d)->format('D'),
                'amount' => (float) ($revenueTrend[$d]->amount ?? 0)
            ];
        }

        // 7. Attendance Trend (Last 7 Days)
        $attendanceTrendDB = Attendance::where('tenant_id', $tenantId)
            ->whereBetween('date', [$sevenDaysAgo->startOfDay(), $today->copy()->endOfDay()])
            ->select('date', DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $attendanceChart = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $sevenDaysAgo->copy()->addDays($i)->format('Y-m-d');
            $attendanceChart[] = [
                'label' => Carbon::parse($d)->format('D'),
                'visits' => (int) ($attendanceTrendDB[$d]->count ?? 0)
            ];
        }

        // 8. Top Trainers
        $topTrainers = Trainer::where('tenant_id', $tenantId)
            ->with(['user'])
            ->withCount('assignedMembers')
            ->orderByDesc('assigned_members_count')
            ->limit(4)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->user->name ?? 'Unknown',
                    'specialization' => $t->specialization ?? 'General',
                    'assigned_members' => $t->assigned_members_count,
                ];
            });

        return $this->jsonResponse([
            'data' => [
                'kpis' => [
                    'total_revenue' => (float) $totalRevenue,
                    'total_members' => $totalMembers,
                    'active_members' => $activeMembers,
                    'trainers_count' => $trainersCount,
                    'today_attendance' => $todayAttendance,
                    'today_revenue' => (float) $todayRevenue,
                    'pending_payments' => $pendingPaymentsCount,
                ],
                'attendance_snapshot' => [
                    'check_ins_today' => $todayAttendance,
                    'currently_in_gym' => $currentlyInGym,
                    'absent_members' => $absentMembers > 0 ? $absentMembers : 0,
                ],
                'expiry_alerts' => $expiringMemberships,
                'recent_activity' => $recentActivity,
                'revenue_trend' => $revenueChart,
                'attendance_trend' => $attendanceChart,
                'top_trainers' => $topTrainers,
            ],
        ], 200, $request);
    }
}

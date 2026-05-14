<?php

namespace App\Services\Gym;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\Payment;
use App\Models\Trainer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get the high-level overview dashboard metrics.
     */
    public function getOverview(int $tenantId, ?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        // Previous period for growth calculation
        $daysDiff = $start->diffInDays($end) ?: 1;
        $prevStart = $start->copy()->subDays($daysDiff);
        $prevEnd = $end->copy()->subDays($daysDiff);

        // Revenue
        $currentRevenue = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('final_amount');

        $prevRevenue = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$prevStart, $prevEnd])
            ->sum('final_amount');

        $revenueGrowth = $prevRevenue > 0 ? (($currentRevenue - $prevRevenue) / $prevRevenue) * 100 : ($currentRevenue > 0 ? 100 : 0);

        // Members
        $activeMembers = Member::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        $newMembers = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('joining_date', [$start, $end])
            ->count();

        // Attendance
        $totalAttendance = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->count();

        return [
            'kpis' => [
                'total_revenue' => (float) $currentRevenue,
                'revenue_growth' => round($revenueGrowth, 1),
                'active_members' => $activeMembers,
                'new_members' => $newMembers,
                'total_attendance' => $totalAttendance,
            ],
            'insights' => [
                $revenueGrowth > 0 ? "Revenue increased by " . round($revenueGrowth, 1) . "% compared to the previous period." : ($revenueGrowth < 0 ? "Revenue decreased by " . abs(round($revenueGrowth, 1)) . "% compared to the previous period." : "Revenue remained stable."),
                $newMembers > 0 ? "Acquired {$newMembers} new members in this period." : "No new member acquisitions in this period.",
            ]
        ];
    }

    /**
     * Get detailed revenue report (daily/monthly, payment methods).
     */
    public function getRevenueReport(int $tenantId, ?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();
        
        $daysDiff = $start->diffInDays($end);
        $groupByFormat = $daysDiff > 90 ? '%Y-%m' : '%Y-%m-%d';
        $displayFormat = $daysDiff > 90 ? 'M Y' : 'd M';

        // Trend
        $revenueTrend = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select(
                DB::raw("DATE_FORMAT(paid_at, '{$groupByFormat}') as label_raw"),
                DB::raw('SUM(final_amount) as amount')
            )
            ->groupBy('label_raw')
            ->orderBy('label_raw')
            ->get()
            ->map(function ($item) use ($daysDiff) {
                $date = $daysDiff > 90 ? Carbon::createFromFormat('Y-m', $item->label_raw) : Carbon::createFromFormat('Y-m-d', $item->label_raw);
                return [
                    'label' => $date->format($daysDiff > 90 ? 'M Y' : 'd M'),
                    'amount' => (float) $item->amount,
                ];
            });

        // Payment Methods
        $paymentMethods = Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->select('payment_method', DB::raw('SUM(final_amount) as amount'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => ucfirst($item->payment_method),
                    'value' => (float) $item->amount,
                ];
            });

        $totalRevenue = $revenueTrend->sum('amount');
        $topMethod = $paymentMethods->sortByDesc('value')->first();

        return [
            'trend' => $revenueTrend,
            'methods' => $paymentMethods,
            'insights' => [
                "Total collected revenue is ₹" . number_format($totalRevenue, 2) . ".",
                $topMethod ? "Most popular payment method is {$topMethod['name']}, accounting for ₹" . number_format($topMethod['value'], 2) . "." : "No payments recorded in this period."
            ]
        ];
    }

    /**
     * Get detailed membership report (active, churn, growth).
     */
    public function getMembershipReport(int $tenantId, ?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        // Member status breakdown
        $statusBreakdown = Member::query()
            ->where('tenant_id', $tenantId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $active = $statusBreakdown->firstWhere('status', 'active')->count ?? 0;
        $inactive = $statusBreakdown->firstWhere('status', 'inactive')->count ?? 0;
        $suspended = $statusBreakdown->firstWhere('status', 'suspended')->count ?? 0;
        $total = $active + $inactive + $suspended;

        // Churn Calculation (Expired memberships in period)
        $expiredMemberships = MemberMembership::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['expired', 'cancelled'])
            ->whereBetween('end_date', [$start, $end])
            ->count();

        $churnRate = $total > 0 ? ($expiredMemberships / $total) * 100 : 0;

        // Growth Trend
        $daysDiff = $start->diffInDays($end);
        $groupByFormat = $daysDiff > 90 ? '%Y-%m' : '%Y-%m-%d';

        $growthTrend = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('joining_date', [$start, $end])
            ->select(
                DB::raw("DATE_FORMAT(joining_date, '{$groupByFormat}') as label_raw"),
                DB::raw('COUNT(*) as members')
            )
            ->groupBy('label_raw')
            ->orderBy('label_raw')
            ->get()
            ->map(function ($item) use ($daysDiff) {
                $date = $daysDiff > 90 ? Carbon::createFromFormat('Y-m', $item->label_raw) : Carbon::createFromFormat('Y-m-d', $item->label_raw);
                return [
                    'label' => $date->format($daysDiff > 90 ? 'M Y' : 'd M'),
                    'members' => (int) $item->members,
                ];
            });

        // Expiring Soon
        $expiringSoon = MemberMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereBetween('end_date', [Carbon::today(), Carbon::today()->addDays(7)])
            ->count();

        $inactivePercent = $total > 0 ? round(($inactive / $total) * 100, 1) : 0;

        return [
            'kpis' => [
                'active' => $active,
                'expired_in_period' => $expiredMemberships,
                'churn_rate' => round($churnRate, 1),
                'expiring_next_7_days' => $expiringSoon,
            ],
            'growth_trend' => $growthTrend,
            'status_breakdown' => [
                ['name' => 'Active', 'value' => $active],
                ['name' => 'Inactive', 'value' => $inactive],
                ['name' => 'Suspended', 'value' => $suspended],
            ],
            'insights' => [
                "Current churn rate is " . round($churnRate, 1) . "% for this period.",
                $inactivePercent > 0 ? "{$inactivePercent}% of total members are currently inactive." : "All members are highly active.",
                $expiringSoon > 0 ? "{$expiringSoon} memberships are expiring in the next 7 days. Action required." : "No immediate membership expirations."
            ]
        ];
    }

    /**
     * Get attendance patterns (trends, peak hours).
     */
    public function getAttendanceReport(int $tenantId, ?string $startDate, ?string $endDate): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        // Daily Trend
        $dailyTrend = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->select('date', DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'label' => Carbon::parse($item->date)->format('d M'),
                    'visits' => (int) $item->count,
                ];
            });

        // Peak Hours
        $peakHours = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$start, $end])
            ->whereNotNull('check_in_time')
            ->select(DB::raw('HOUR(check_in_time) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                $hour = (int) $item->hour;
                $label = $hour === 0 ? '12 AM' : ($hour < 12 ? "{$hour} AM" : ($hour === 12 ? '12 PM' : ($hour - 12) . ' PM'));
                return [
                    'label' => $label,
                    'visits' => (int) $item->count,
                ];
            });

        $topHour = $peakHours->sortByDesc('visits')->first();

        return [
            'trend' => $dailyTrend,
            'peak_hours' => $peakHours,
            'insights' => [
                $topHour ? "Peak traffic occurs around {$topHour['label']} with {$topHour['visits']} total visits in this period." : "Not enough data to determine peak hours.",
                "Average daily attendance is " . ($dailyTrend->count() > 0 ? round($dailyTrend->avg('visits')) : 0) . " visits."
            ]
        ];
    }

    /**
     * Get trainer performance (members assigned, attendance handled).
     */
    public function getTrainerPerformance(int $tenantId): array
    {
        $trainers = Trainer::query()
            ->where('tenant_id', $tenantId)
            ->with(['user', 'assignedMembers'])
            ->withCount(['assignedMembers as assigned_members_count'])
            ->get();

        $distribution = $trainers->map(function ($trainer) {
            return [
                'name' => $trainer->user?->name ?? 'Unknown Trainer',
                'value' => $trainer->assigned_members_count,
            ];
        })->filter(fn($t) => $t['value'] > 0)->values();

        $topTrainer = $distribution->sortByDesc('value')->first();

        return [
            'distribution' => $distribution,
            'details' => $trainers->map(function ($trainer) {
                return [
                    'id' => $trainer->id,
                    'name' => $trainer->user?->name ?? 'Unknown',
                    'specialization' => $trainer->specialization,
                    'assigned_members' => $trainer->assigned_members_count,
                ];
            })->sortByDesc('assigned_members')->values(),
            'insights' => [
                $topTrainer ? "{$topTrainer['name']} has the highest engagement, managing {$topTrainer['value']} members." : "No members currently assigned to trainers.",
                "Equitable distribution of members among trainers prevents burnout and improves member retention."
            ]
        ];
    }
}

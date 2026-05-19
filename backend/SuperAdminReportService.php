<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Payment;
use App\Models\CouponUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SuperAdminReportService
{
    /**
     * Get all dashboard metrics based on filters.
     */
    public function getDashboardMetrics(array $filters): array
    {
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : Carbon::now()->endOfDay();
        
        return [
            'revenue'       => $this->getRevenueReports($startDate, $endDate, $filters),
            'gyms'          => $this->getGymReports($startDate, $endDate, $filters),
            'subscriptions' => $this->getSubscriptionReports($startDate, $endDate, $filters),
            'coupons'       => $this->getCouponReports($startDate, $endDate),
            'payments'      => $this->getPaymentReports($startDate, $endDate),
            'growth'        => $this->getGrowthMetrics($startDate, $endDate),
        ];
    }

    /**
     * 1. Revenue Reports
     */
    private function getRevenueReports(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        // Base Query: Only Platform Payments (Exclude Gym-Member payments)
        $baseQuery = Payment::whereNull('tenant_id') // Assuming tenant_id is NOT null for gym-member payments
            ->where('status', 'success')
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Apply Plan Filter if exists
        if (!empty($filters['plan_id'])) {
            $baseQuery->where('plan_id', $filters['plan_id']);
        }

        // Total Revenue
        $totalRevenue = (clone $baseQuery)->sum('amount');

        // Monthly Revenue Chart Data
        $monthlyRevenue = (clone $baseQuery)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Plan-wise Revenue
        $planWise = (clone $baseQuery)
            ->join('plans', 'payments.plan_id', '=', 'plans.id')
            ->select('plans.name', DB::raw('SUM(payments.amount) as total'))
            ->groupBy('plans.name')
            ->get();

        return [
            'total_revenue' => $totalRevenue,
            'monthly_chart' => $monthlyRevenue,
            'plan_wise'     => $planWise,
        ];
    }

    /**
     * 2. Gym Reports
     */
    private function getGymReports(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $baseQuery = Tenant::query();

        if (!empty($filters['status'])) {
            $baseQuery->where('status', $filters['status']);
        }

        $totalGyms = (clone $baseQuery)->count();
        $activeGyms = (clone $baseQuery)->where('status', 'active')->count();
        $inactiveGyms = $totalGyms - $activeGyms;

        $newGymsPerMonth = (clone $baseQuery)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Basic Churn Rate: (Cancelled Subscriptions in period / Total Active at start of period) * 100
        $cancelledInPeriod = TenantSubscription::where('status', 'cancelled')
            ->whereBetween('ends_at', [$startDate, $endDate])
            ->count();
        $churnRate = $activeGyms > 0 ? round(($cancelledInPeriod / $activeGyms) * 100, 2) : 0;

        return [
            'total'          => $totalGyms,
            'active'         => $activeGyms,
            'inactive'       => $inactiveGyms,
            'new_per_month'  => $newGymsPerMonth,
            'churn_rate_pct' => $churnRate,
        ];
    }

    /**
     * 3. Subscription Reports
     */
    private function getSubscriptionReports(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = TenantSubscription::query();

        if (!empty($filters['plan_id'])) {
            $query->where('plan_id', $filters['plan_id']);
        }

        return [
            'active'        => (clone $query)->where('status', 'active')->count(),
            'expired'       => (clone $query)->where('status', 'expired')->count(),
            'trial'         => (clone $query)->where('status', 'trialing')->count(),
            'expiring_soon' => (clone $query)->where('status', 'active')
                                             ->whereBetween('ends_at', [Carbon::now(), Carbon::now()->addDays(7)])
                                             ->count(),
            'plan_distribution' => (clone $query)
                ->join('plans', 'tenant_subscriptions.plan_id', '=', 'plans.id')
                ->select('plans.name', DB::raw('COUNT(*) as count'))
                ->groupBy('plans.name')
                ->get(),
        ];
    }

    /**
     * 4. Coupon Reports
     */
    private function getCouponReports(Carbon $startDate, Carbon $endDate): array
    {
        $usageQuery = CouponUsage::whereBetween('created_at', [$startDate, $endDate]);

        $bestCoupon = (clone $usageQuery)
            ->join('coupons', 'coupon_usages.coupon_id', '=', 'coupons.id')
            ->select('coupons.code', DB::raw('COUNT(*) as total_uses'), DB::raw('SUM(coupon_usages.discount_amount) as revenue_impact'))
            ->groupBy('coupons.code')
            ->orderByDesc('total_uses')
            ->first();

        return [
            'total_usage'    => (clone $usageQuery)->count(),
            'revenue_impact' => (clone $usageQuery)->sum('discount_amount'),
            'best_coupon'    => $bestCoupon ? $bestCoupon->code : null,
        ];
    }

    /**
     * 5. Payment Reports
     */
    private function getPaymentReports(Carbon $startDate, Carbon $endDate): array
    {
        $baseQuery = Payment::whereNull('tenant_id') // Platform context only
            ->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'success'            => (clone $baseQuery)->where('status', 'success')->count(),
            'failed'             => (clone $baseQuery)->where('status', 'failed')->count(),
            'transaction_volume' => (clone $baseQuery)->count(),
            'method_breakdown'   => (clone $baseQuery)
                ->select('payment_method', DB::raw('COUNT(*) as count'))
                ->groupBy('payment_method')
                ->get(),
        ];
    }

    /**
     * 6. Growth Metrics (Period over Period)
     */
    private function getGrowthMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $periodDuration = $startDate->diffInDays($endDate);
        $prevStartDate = (clone $startDate)->subDays($periodDuration);
        $prevEndDate = (clone $startDate)->subSecond();

        $currentRevenue = Payment::whereNull('tenant_id')->where('status', 'success')->whereBetween('created_at', [$startDate, $endDate])->sum('amount');
        $prevRevenue = Payment::whereNull('tenant_id')->where('status', 'success')->whereBetween('created_at', [$prevStartDate, $prevEndDate])->sum('amount');

        $currentGyms = Tenant::whereBetween('created_at', [$startDate, $endDate])->count();
        $prevGyms = Tenant::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();

        $currentSubs = TenantSubscription::whereBetween('created_at', [$startDate, $endDate])->count();
        $prevSubs = TenantSubscription::whereBetween('created_at', [$prevStartDate, $prevEndDate])->count();

        return [
            'revenue_growth_pct'      => $this->calculateGrowth($currentRevenue, $prevRevenue),
            'gym_growth_pct'          => $this->calculateGrowth($currentGyms, $prevGyms),
            'subscription_growth_pct' => $this->calculateGrowth($currentSubs, $prevSubs),
        ];
    }

    /**
     * Helper to calculate percentage growth safely.
     */
    private function calculateGrowth($current, $previous): float
    {
        if ($previous == 0) return $current > 0 ? 100.0 : 0.0;
        return round((($current - $previous) / $previous) * 100, 2);
    }
}
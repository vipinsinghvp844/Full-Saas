<?php

namespace App\Services\SuperAdmin;

use App\Models\Coupon;
use App\Models\Payment;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Repositories\SuperAdmin\DashboardRepository;
use App\Repositories\SuperAdmin\GymRepository;
use App\Repositories\SuperAdmin\SubscriptionRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(
        protected DashboardRepository $dashboardRepository,
        protected GymRepository $gymRepository,
        protected SubscriptionRepository $subscriptionRepository
    ) {
    }

    // ─────────────────────────────────────────────
    //  Platform-only payment base query
    // ─────────────────────────────────────────────
    protected function platformPaymentQuery()
    {
        return Payment::query()
            ->whereNull('payments.member_id')
            ->whereNull('payments.membership_id')
            ->whereHas('invoice', function ($q) {
                $q->whereNotNull('invoices.subscription_id')
                  ->whereNull('invoices.member_id')
                  ->whereNull('invoices.membership_id');
            });
    }

    protected function paidPlatformQuery()
    {
        return $this->platformPaymentQuery()->where(function ($q) {
            $q->where('payments.payment_status', 'paid')
              ->orWhere(function ($q2) {
                  $q2->whereNull('payments.payment_status')->where('payments.status', 'completed');
              });
        });
    }

    protected function percentageChange(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    // ─────────────────────────────────────────────
    //  Overview (all-in-one KPI snapshot)
    // ─────────────────────────────────────────────
    public function overview(array $filters = []): array
    {
        $metrics = $this->dashboardRepository->metricSummary();

        return [
            'summary' => [
                'total_revenue'               => $metrics['total_revenue'],
                'monthly_revenue'             => $metrics['monthly_revenue'],
                'yearly_revenue'              => $metrics['yearly_revenue'],
                'monthly_recurring_revenue'   => $metrics['monthly_recurring_revenue'],
                'total_gyms'                  => $metrics['total_gyms'],
                'active_gyms'                 => $metrics['active_gyms'],
                'inactive_gyms'               => $metrics['inactive_gyms'],
                'active_subscriptions'        => $metrics['active_subscriptions'],
                'expired_subscriptions'       => $metrics['expired_subscriptions'],
                'trial_subscriptions'         => $metrics['trial_subscriptions'],
                'revenue_growth_percentage'   => $metrics['revenue_growth_percentage'],
                'churn_rate'                  => $metrics['churn_rate'],
                'expiring_soon'               => $metrics['expiring_soon'],
                'renewals_this_month'         => $metrics['renewals_this_month'],
                'failed_payments'             => $metrics['failed_payments'],
            ],
            'revenue_growth'          => $this->dashboardRepository->revenueGrowthSeries(),
            'gym_growth'              => $this->dashboardRepository->gymGrowthSeries(),
            'subscription_breakdown'  => $this->dashboardRepository->subscriptionStatusBreakdown(),
            'plan_distribution'       => $this->dashboardRepository->planDistribution(),
        ];
    }

    // ─────────────────────────────────────────────
    //  Revenue Report
    // ─────────────────────────────────────────────
    public function revenueReport(array $filters = []): array
    {
        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $dateTo   = isset($filters['date_to'])   ? Carbon::parse($filters['date_to'])->endOfDay()   : null;
        $planId   = $filters['plan_id'] ?? null;

        $baseQ = $this->paidPlatformQuery()
            ->when($dateFrom, fn($q) => $q->where('payments.created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('payments.created_at', '<=', $dateTo));

        if ($planId) {
            $baseQ->whereHas('invoice.subscription', fn($q) => $q->where('plan_id', $planId));
        }

        $totalRevenue   = (float) (clone $baseQ)->sum('payments.amount');
        $totalDiscount  = (float) (clone $baseQ)->sum('payments.discount');
        $txnCount       = (int)   (clone $baseQ)->count();

        // Monthly series (last 12 months or date range)
        $months = 12;
        $end    = Carbon::now()->startOfMonth();
        $start  = $end->copy()->subMonths($months - 1);

        $monthlyRevenue = (clone $baseQ)
            ->selectRaw("DATE_FORMAT(payments.created_at, '%Y-%m') as period, SUM(payments.amount) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $monthlySeries = collect(range(0, $months - 1))->map(function ($offset) use ($start, $monthlyRevenue) {
            $month = $start->copy()->addMonths($offset);
            $key   = $month->format('Y-m');
            return ['label' => $month->format('M Y'), 'value' => (float) ($monthlyRevenue[$key] ?? 0)];
        })->all();

        // Plan-wise revenue
        $planWise = (clone $baseQ)
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->join('tenant_subscriptions', 'tenant_subscriptions.id', '=', 'invoices.subscription_id')
            ->join('platform_plans', 'platform_plans.id', '=', 'tenant_subscriptions.plan_id')
            ->select('platform_plans.name as label', DB::raw('SUM(payments.amount) as value'))
            ->groupBy('platform_plans.id', 'platform_plans.name')
            ->orderByDesc('value')
            ->get()
            ->map(fn($r) => ['label' => $r->label, 'value' => (float) $r->value])
            ->all();

        // Coupon discount impact
        $totalCouponDiscount = (float) TenantSubscription::query()
            ->whereNotNull('coupon_id')
            ->when($planId, fn($q) => $q->where('plan_id', $planId))
            ->sum('discount_amount');

        $couponCount = (int) TenantSubscription::query()
            ->whereNotNull('coupon_id')
            ->when($planId, fn($q) => $q->where('plan_id', $planId))
            ->count();

        // Failed vs Success
        $failedCount    = (int) (clone $this->platformPaymentQuery())
            ->where(fn($q) => $q->where('payment_status', 'failed')->orWhere('status', 'failed'))
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('created_at', '<=', $dateTo))
            ->count();

        return [
            'summary' => [
                'total_revenue'        => $totalRevenue,
                'total_discount'       => $totalDiscount,
                'net_revenue'          => $totalRevenue - $totalDiscount,
                'transaction_count'    => $txnCount,
                'coupon_discount'      => $totalCouponDiscount,
                'coupon_usage_count'   => $couponCount,
                'failed_count'         => $failedCount,
                'avg_transaction'      => $txnCount > 0 ? round($totalRevenue / $txnCount, 2) : 0,
            ],
            'monthly_series'   => $monthlySeries,
            'plan_wise'        => $planWise,
        ];
    }

    // ─────────────────────────────────────────────
    //  Gym Report
    // ─────────────────────────────────────────────
    public function gymGrowthReport(array $filters = []): array
    {
        $now    = Carbon::now();
        $status = $filters['status'] ?? null;

        $metrics = $this->dashboardRepository->metricSummary();

        // New gyms per month (12 months)
        $months   = 12;
        $end      = $now->copy()->startOfMonth();
        $start    = $end->copy()->subMonths($months - 1);

        $gymCounts = Tenant::query()
            ->when($status, fn($q) => $q->where('status', $status))
            ->whereBetween('created_at', [$start->startOfMonth(), $now->endOfMonth()])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $gymSeries = collect(range(0, $months - 1))->map(function ($offset) use ($start, $gymCounts) {
            $month = $start->copy()->addMonths($offset);
            $key   = $month->format('Y-m');
            return ['label' => $month->format('M Y'), 'value' => (int) ($gymCounts[$key] ?? 0)];
        })->all();

        // Active vs Inactive breakdown
        $statusBreakdown = Tenant::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn($v) => (int) $v)
            ->all();

        // Churn rate history (last 6 months)
        $churnSeries = collect(range(5, 0))->map(function ($offset) use ($now) {
            $month      = $now->copy()->subMonths($offset);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd   = $month->copy()->endOfMonth();

            $activeCount = TenantSubscription::query()
                ->where('created_at', '<=', $monthEnd)
                ->where(fn($q) => $q->where('status', 'active')
                    ->orWhere(fn($q2) => $q2->where('status', 'expired')->where('grace_period_ends_at', '>', $monthEnd)))
                ->count();

            $cancelled = TenantSubscription::query()
                ->whereBetween('cancelled_at', [$monthStart, $monthEnd])
                ->count();

            $churn = $activeCount > 0 ? round(($cancelled / $activeCount) * 100, 2) : 0;

            return ['label' => $month->format('M Y'), 'value' => $churn];
        })->values()->all();

        $latestGyms = $this->gymRepository->paginate(['per_page' => 5, 'sort_by' => 'created_at', 'sort_direction' => 'desc']);

        return [
            'summary' => [
                'total_gyms'          => $metrics['total_gyms'],
                'active_gyms'         => $metrics['active_gyms'],
                'inactive_gyms'       => $metrics['inactive_gyms'],
                'trial_gyms'          => $metrics['trial_gyms'],
                'new_gyms_this_month' => $metrics['new_gyms_this_month'],
                'churn_rate'          => $metrics['churn_rate'],
                'report_generated_at' => $now->toDateTimeString(),
            ],
            'gym_series'        => $gymSeries,
            'status_breakdown'  => $statusBreakdown,
            'churn_series'      => $churnSeries,
            'latest_gyms'       => $latestGyms->getCollection(),
        ];
    }

    // ─────────────────────────────────────────────
    //  Subscription Report
    // ─────────────────────────────────────────────
    public function subscriptionReport(array $filters = []): array
    {
        $this->subscriptionRepository->syncExpiredStatuses();
        $now    = Carbon::now();
        $planId = $filters['plan_id'] ?? null;
        $status = $filters['status'] ?? null;

        $metrics = $this->dashboardRepository->metricSummary();

        // Status breakdown
        $breakdown = $this->dashboardRepository->subscriptionStatusBreakdown();

        // Plan distribution
        $planDist = $this->dashboardRepository->planDistribution();

        // Expiring soon (next 14 days)
        $expiringSoon = TenantSubscription::query()
            ->with(['tenant:id,name,status', 'plan:id,name,plan_type'])
            ->whereIn('status', ['active', 'trial'])
            ->whereBetween('end_date', [$now->toDateString(), $now->copy()->addDays(14)->toDateString()])
            ->orderBy('end_date')
            ->limit(20)
            ->get();

        // Renewals per month
        $months = 12;
        $end    = $now->copy()->startOfMonth();
        $start  = $end->copy()->subMonths($months - 1);

        $renewalCounts = TenantSubscription::query()
            ->when($planId, fn($q) => $q->where('plan_id', $planId))
            ->whereBetween('renewal_date', [$start, $now->endOfMonth()])
            ->selectRaw("DATE_FORMAT(renewal_date, '%Y-%m') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $renewalSeries = collect(range(0, $months - 1))->map(function ($offset) use ($start, $renewalCounts) {
            $month = $start->copy()->addMonths($offset);
            $key   = $month->format('Y-m');
            return ['label' => $month->format('M Y'), 'value' => (int) ($renewalCounts[$key] ?? 0)];
        })->all();

        // New subscriptions per month
        $newSubCounts = TenantSubscription::query()
            ->when($planId, fn($q) => $q->where('plan_id', $planId))
            ->when($status,  fn($q) => $q->where('status', $status))
            ->whereBetween('created_at', [$start->startOfDay(), $now->endOfDay()])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $newSubSeries = collect(range(0, $months - 1))->map(function ($offset) use ($start, $newSubCounts) {
            $month = $start->copy()->addMonths($offset);
            $key   = $month->format('Y-m');
            return ['label' => $month->format('M Y'), 'value' => (int) ($newSubCounts[$key] ?? 0)];
        })->all();

        return [
            'summary' => [
                'active'                     => $metrics['active_subscriptions'],
                'expired'                    => $metrics['expired_subscriptions'],
                'trial'                      => $metrics['trial_subscriptions'],
                'paused'                     => $metrics['paused_subscriptions'],
                'cancelled'                  => $metrics['cancelled_subscriptions'],
                'suspended'                  => $metrics['suspended_subscriptions'],
                'monthly_recurring_revenue'  => $metrics['monthly_recurring_revenue'],
                'renewals_this_month'        => $metrics['renewals_this_month'],
                'expiring_soon_count'        => $expiringSoon->count(),
            ],
            'breakdown'       => $breakdown,
            'plan_dist'       => $planDist,
            'expiring_soon'   => $expiringSoon,
            'renewal_series'  => $renewalSeries,
            'new_sub_series'  => $newSubSeries,
        ];
    }

    // ─────────────────────────────────────────────
    //  Coupon Report
    // ─────────────────────────────────────────────
    public function couponReport(array $filters = []): array
    {
        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $dateTo   = isset($filters['date_to'])   ? Carbon::parse($filters['date_to'])->endOfDay()   : null;

        // Total coupons
        $totalCoupons  = Coupon::count();
        $activeCoupons = Coupon::where('status', 'active')->count();

        // Total usage & discount impact
        $usageQuery = TenantSubscription::query()
            ->whereNotNull('coupon_id')
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('created_at', '<=', $dateTo));

        $totalUsage    = (int)   (clone $usageQuery)->count();
        $totalDiscount = (float) (clone $usageQuery)->sum('discount_amount');
        $usedCount     = (int)   (clone $usageQuery)->count();

        // Per-coupon breakdown
        $couponStats = TenantSubscription::query()
            ->whereNotNull('coupon_id')
            ->join('coupons', 'coupons.id', '=', 'tenant_subscriptions.coupon_id')
            ->when($dateFrom, fn($q) => $q->where('tenant_subscriptions.created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('tenant_subscriptions.created_at', '<=', $dateTo))
            ->select(
                'coupons.code as label',
                'coupons.discount_type',
                'coupons.discount_value',
                'coupons.used_count',
                DB::raw('COUNT(tenant_subscriptions.id) as sub_count'),
                DB::raw('SUM(tenant_subscriptions.discount_amount) as total_discount'),
                DB::raw('SUM(tenant_subscriptions.final_amount) as total_revenue')
            )
            ->groupBy('coupons.id', 'coupons.code', 'coupons.discount_type', 'coupons.discount_value', 'coupons.used_count')
            ->orderByDesc('sub_count')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'code'           => $r->label,
                'discount_type'  => $r->discount_type,
                'discount_value' => (float) $r->discount_value,
                'usage_count'    => (int) $r->used_count,
                'sub_count'      => (int) $r->sub_count,
                'total_discount' => (float) $r->total_discount,
                'total_revenue'  => (float) $r->total_revenue,
            ])
            ->all();

        $bestCoupon = collect($couponStats)->sortByDesc('usage_count')->first();

        // Revenue impact chart (discount per month)
        $months = 12;
        $now    = Carbon::now();
        $end    = $now->copy()->startOfMonth();
        $start  = $end->copy()->subMonths($months - 1);

        $discountMonthly = TenantSubscription::query()
            ->whereNotNull('coupon_id')
            ->whereBetween('created_at', [$start->startOfDay(), $now->endOfDay()])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, SUM(discount_amount) as total")
            ->groupBy('period')
            ->pluck('total', 'period');

        $discountSeries = collect(range(0, $months - 1))->map(function ($offset) use ($start, $discountMonthly) {
            $month = $start->copy()->addMonths($offset);
            $key   = $month->format('Y-m');
            return ['label' => $month->format('M Y'), 'value' => (float) ($discountMonthly[$key] ?? 0)];
        })->all();

        return [
            'summary' => [
                'total_coupons'    => $totalCoupons,
                'active_coupons'   => $activeCoupons,
                'total_usage'      => $usedCount,
                'total_discount'   => $totalDiscount,
                'best_coupon_code' => $bestCoupon['code'] ?? null,
                'best_coupon_uses' => $bestCoupon['usage_count'] ?? 0,
            ],
            'coupon_stats'     => $couponStats,
            'discount_series'  => $discountSeries,
        ];
    }

    // ─────────────────────────────────────────────
    //  Payment Report
    // ─────────────────────────────────────────────
    public function paymentReport(array $filters = []): array
    {
        $dateFrom = isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : null;
        $dateTo   = isset($filters['date_to'])   ? Carbon::parse($filters['date_to'])->endOfDay()   : null;

        $baseQ = $this->platformPaymentQuery()
            ->when($dateFrom, fn($q) => $q->where('payments.created_at', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->where('payments.created_at', '<=', $dateTo));

        $successQ = (clone $baseQ)->where(fn($q) => $q->where('payment_status', 'paid')
            ->orWhere(fn($q2) => $q2->whereNull('payment_status')->where('status', 'completed')));

        $failedQ  = (clone $baseQ)->where(fn($q) => $q->where('payment_status', 'failed')
            ->orWhere('status', 'failed'));

        $pendingQ = (clone $baseQ)->where(fn($q) => $q->where('payment_status', 'pending')
            ->orWhere('status', 'pending'));

        $successCount  = (int)   (clone $successQ)->count();
        $failedCount   = (int)   (clone $failedQ)->count();
        $pendingCount  = (int)   (clone $pendingQ)->count();
        $totalAmount   = (float) (clone $successQ)->sum('payments.amount');
        $totalTxns     = $successCount + $failedCount + $pendingCount;
        $successRate   = $totalTxns > 0 ? round(($successCount / $totalTxns) * 100, 2) : 0;

        // Payment method breakdown
        $methodBreakdown = (clone $baseQ)
            ->select('payments.payment_method as label', DB::raw('COUNT(*) as txn_count'), DB::raw('SUM(payments.amount) as total_amount'))
            ->whereNotNull('payments.payment_method')
            ->groupBy('payments.payment_method')
            ->orderByDesc('txn_count')
            ->get()
            ->map(fn($r) => [
                'label'        => $r->label,
                'txn_count'    => (int) $r->txn_count,
                'total_amount' => (float) $r->total_amount,
            ])
            ->all();

        // Transaction volume per month
        $months = 12;
        $now    = Carbon::now();
        $end    = $now->copy()->startOfMonth();
        $start  = $end->copy()->subMonths($months - 1);

        $txnMonthly = (clone $successQ)
            ->whereBetween('payments.created_at', [$start->startOfDay(), $now->endOfDay()])
            ->selectRaw("DATE_FORMAT(payments.created_at, '%Y-%m') as period, COUNT(*) as txn_count, SUM(payments.amount) as total_amount")
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $txnSeries = collect(range(0, $months - 1))->map(function ($offset) use ($start, $txnMonthly) {
            $month = $start->copy()->addMonths($offset);
            $key   = $month->format('Y-m');
            $row   = $txnMonthly[$key] ?? null;
            return [
                'label'        => $month->format('M Y'),
                'txn_count'    => (int)   ($row?->txn_count ?? 0),
                'total_amount' => (float) ($row?->total_amount ?? 0),
            ];
        })->all();

        return [
            'summary' => [
                'success_count'  => $successCount,
                'failed_count'   => $failedCount,
                'pending_count'  => $pendingCount,
                'total_amount'   => $totalAmount,
                'total_txns'     => $totalTxns,
                'success_rate'   => $successRate,
            ],
            'method_breakdown' => $methodBreakdown,
            'txn_series'       => $txnSeries,
        ];
    }

    // ─────────────────────────────────────────────
    //  Growth Metrics Report
    // ─────────────────────────────────────────────
    public function growthReport(array $filters = []): array
    {
        $now           = Carbon::now();
        $thisMonth     = $now->copy();
        $lastMonth     = $now->copy()->subMonthNoOverflow();
        $twoMonthsAgo  = $now->copy()->subMonths(2);

        // Revenue growth
        $revenueThisMonth  = (float) $this->paidPlatformQuery()->whereYear('payments.created_at', $thisMonth->year)->whereMonth('payments.created_at', $thisMonth->month)->sum('payments.amount');
        $revenueLastMonth  = (float) $this->paidPlatformQuery()->whereBetween('payments.created_at', [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()])->sum('payments.amount');
        $revenuePrev       = (float) $this->paidPlatformQuery()->whereBetween('payments.created_at', [$twoMonthsAgo->copy()->startOfMonth(), $twoMonthsAgo->copy()->endOfMonth()])->sum('payments.amount');

        $revenueGrowthMom  = $this->percentageChange($revenueThisMonth, $revenueLastMonth);
        $revenueMomPrev    = $this->percentageChange($revenueLastMonth, $revenuePrev);

        // Gym growth
        $gymsThisMonth = Tenant::whereYear('created_at', $thisMonth->year)->whereMonth('created_at', $thisMonth->month)->count();
        $gymsLastMonth = Tenant::whereBetween('created_at', [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()])->count();
        $gymGrowthMom  = $this->percentageChange($gymsThisMonth, $gymsLastMonth);

        // Subscription growth
        $subThisMonth = TenantSubscription::whereYear('created_at', $thisMonth->year)->whereMonth('created_at', $thisMonth->month)->count();
        $subLastMonth = TenantSubscription::whereBetween('created_at', [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()])->count();
        $subGrowthMom = $this->percentageChange($subThisMonth, $subLastMonth);

        // MRR vs last month MRR (approximate)
        $metrics       = $this->dashboardRepository->metricSummary();
        $mrrCurrent    = $metrics['monthly_recurring_revenue'];

        // Revenue growth series (12 months)
        $revenueSeries = $this->dashboardRepository->revenueGrowthSeries();
        $gymSeries     = $this->dashboardRepository->gymGrowthSeries();

        // Growth rate month-over-month series from revenue series
        $growthSeries = collect($revenueSeries)->sliding(2)->map(function ($pair) {
            $pair  = $pair->values();
            $prev  = (float) $pair[0]['value'];
            $curr  = (float) $pair[1]['value'];
            $growth = $prev > 0 ? round((($curr - $prev) / $prev) * 100, 2) : ($curr > 0 ? 100 : 0);
            return ['label' => $pair[1]['label'], 'value' => $growth];
        })->values()->all();

        return [
            'summary' => [
                'revenue_growth_mom'     => $revenueGrowthMom,
                'revenue_growth_mom_prev'=> $revenueMomPrev,
                'gym_growth_mom'         => $gymGrowthMom,
                'sub_growth_mom'         => $subGrowthMom,
                'gyms_this_month'        => $gymsThisMonth,
                'gyms_last_month'        => $gymsLastMonth,
                'revenue_this_month'     => $revenueThisMonth,
                'revenue_last_month'     => $revenueLastMonth,
                'mrr'                    => $mrrCurrent,
                'churn_rate'             => $metrics['churn_rate'],
            ],
            'revenue_series' => $revenueSeries,
            'gym_series'     => $gymSeries,
            'growth_series'  => $growthSeries,
        ];
    }

}

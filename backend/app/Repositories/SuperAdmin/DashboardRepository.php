<?php

namespace App\Repositories\SuperAdmin;

use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Trainer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    public function metricSummary(): array
    {
        $now = Carbon::now();
        $activeSubscriptionQuery = $this->activeSubscriptionQuery($now);
        $platformPaymentQuery = $this->platformPaymentQuery();
        $thisMonthRevenue = (float) (clone $platformPaymentQuery)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->sum('amount');
        $lastMonthRevenue = (float) (clone $platformPaymentQuery)
            ->whereBetween('created_at', [
                $now->copy()->subMonthNoOverflow()->startOfMonth(),
                $now->copy()->subMonthNoOverflow()->endOfMonth(),
            ])
            ->sum('amount');

        return [
            'total_gyms' => Tenant::count(),
            'active_gyms' => Tenant::where('status', 'active')->count(),
            'inactive_gyms' => Tenant::where('status', 'inactive')->count(),
            'trial_gyms' => Tenant::query()
                ->whereHas('subscriptions', fn ($query) => $query->where('status', 'trial'))
                ->count(),
            'expired_gyms' => Tenant::query()
                ->whereHas('subscriptions', fn ($query) => $this->applyExpiredSubscriptionScope($query, $now))
                ->count(),
            'total_members' => Member::count(),
            'total_trainers' => Trainer::count(),
            'total_revenue' => (float) (clone $platformPaymentQuery)->sum('amount'),
            'today_revenue' => (float) (clone $platformPaymentQuery)
                ->whereDate('created_at', $now->toDateString())
                ->sum('amount'),
            'monthly_revenue' => $thisMonthRevenue,
            'yearly_revenue' => (float) (clone $platformPaymentQuery)
                ->whereYear('created_at', $now->year)
                ->sum('amount'),
            'failed_payments' => $this->failedPlatformPaymentQuery()->count(),
            'active_subscriptions' => $activeSubscriptionQuery->count(),
            'expired_subscriptions' => $this->expiredSubscriptionQuery($now)->count(),
            'trial_subscriptions' => TenantSubscription::where('status', 'trial')->count(),
            'cancelled_subscriptions' => TenantSubscription::where('status', 'cancelled')->count(),
            'suspended_subscriptions' => TenantSubscription::where('status', 'suspended')->count(),
            'paused_subscriptions' => TenantSubscription::where('status', 'paused')->count(),
            'total_subscriptions' => TenantSubscription::count(),
            'monthly_recurring_revenue' => $this->monthlyRecurringRevenue($now),
            'expiring_soon' => $this->expiringSoonQuery($now)->count(),
            'renewals_this_month' => TenantSubscription::query()
                ->whereIn('status', ['active', 'trial'])
                ->whereBetween('renewal_date', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()])
                ->count(),
            'new_gyms_this_month' => Tenant::query()
                ->whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->count(),
            'revenue_growth_percentage' => $this->percentageChange($thisMonthRevenue, $lastMonthRevenue),
            'churn_rate' => $this->calculateChurnRate(),
        ];
    }

    protected function platformPaymentQuery(): Builder
    {
        return Payment::query()
            ->whereNull('member_id')
            ->whereNull('membership_id')
            ->whereIn('status', ['completed'])
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                    ->orWhereNull('payment_status');
            })
            ->whereHas('invoice', function ($query) {
                $query->whereNotNull('subscription_id')
                    ->whereNull('member_id')
                    ->whereNull('membership_id');
            });
    }

    protected function failedPlatformPaymentQuery(): Builder
    {
        return Payment::query()
            ->whereNull('member_id')
            ->whereNull('membership_id')
            ->where(function ($query) {
                $query->where('status', 'failed')
                    ->orWhere('payment_status', 'failed');
            })
            ->whereHas('invoice', function ($query) {
                $query->whereNotNull('subscription_id')
                    ->whereNull('member_id')
                    ->whereNull('membership_id');
            });
    }

    protected function activeSubscriptionQuery(Carbon $now): Builder
    {
        return TenantSubscription::query()
            ->where(function ($query) use ($now) {
                $query->whereIn('tenant_subscriptions.status', ['active', 'trial'])
                    ->where(function ($q) use ($now) {
                        $q->whereNull('tenant_subscriptions.end_date')
                            ->orWhereDate('tenant_subscriptions.end_date', '>=', $now->toDateString());
                    })
                    ->orWhere(function ($q) use ($now) {
                        $q->where('tenant_subscriptions.status', 'expired')
                            ->where('tenant_subscriptions.grace_period_ends_at', '>', $now);
                    });
            });
    }

    protected function expiredSubscriptionQuery(Carbon $now): Builder
    {
        return $this->applyExpiredSubscriptionScope(TenantSubscription::query(), $now);
    }

    protected function applyExpiredSubscriptionScope(Builder $query, Carbon $now): Builder
    {
        return $query->where(function ($query) use ($now) {
                $query->where('tenant_subscriptions.status', 'expired')
                    ->where(function ($q) use ($now) {
                        $q->whereNull('tenant_subscriptions.grace_period_ends_at')
                            ->orWhere('tenant_subscriptions.grace_period_ends_at', '<=', $now);
                    })
                    ->orWhere(function ($q) use ($now) {
                        $q->whereNotIn('tenant_subscriptions.status', ['expired'])
                            ->whereDate('tenant_subscriptions.end_date', '<', $now->toDateString());
                    });
            });
    }

    protected function expiringSoonQuery(Carbon $now): Builder
    {
        return TenantSubscription::query()
            ->with(['tenant:id,name,status', 'plan:id,name,plan_type'])
            ->whereIn('status', ['active', 'trial'])
            ->whereBetween('end_date', [$now->toDateString(), $now->copy()->addDays(14)->toDateString()]);
    }

    protected function monthlyRecurringRevenue(Carbon $now): float
    {
        return (float) $this->activeSubscriptionQuery($now)
            ->leftJoin('platform_plans', 'platform_plans.id', '=', 'tenant_subscriptions.plan_id')
            ->selectRaw("
                COALESCE(SUM(
                    tenant_subscriptions.final_amount /
                    CASE
                        WHEN platform_plans.plan_type = 'yearly' THEN 12
                        WHEN platform_plans.plan_type = 'quarterly' THEN 3
                        WHEN platform_plans.duration > 1 THEN platform_plans.duration
                        ELSE 1
                    END
                ), 0) as mrr
            ")
            ->value('mrr');
    }

    public function gymGrowthSeries(int $months = 12): array
    {
        return $this->monthlySeries(
            Tenant::query(),
            'created_at',
            $months,
            fn ($query) => $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
                ->groupBy('period')
                ->pluck('total', 'period')
        );
    }

    public function memberGrowthSeries(int $months = 12): array
    {
        return $this->monthlySeries(
            Member::query(),
            'created_at',
            $months,
            fn ($query) => $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COUNT(*) as total")
                ->groupBy('period')
                ->pluck('total', 'period')
        );
    }

    public function revenueGrowthSeries(int $months = 12): array
    {
        return $this->monthlySeries(
            $this->platformPaymentQuery(),
            'created_at',
            $months,
            fn ($query) => $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COALESCE(SUM(amount), 0) as total")
                ->groupBy('period')
                ->pluck('total', 'period')
        );
    }

    public function growthRateSeries(int $months = 12): array
    {
        $revenue = collect($this->revenueGrowthSeries($months + 1));

        return $revenue->slice(1)
            ->values()
            ->map(function (array $point, int $index) use ($revenue) {
                return [
                    'label' => $point['label'],
                    'value' => $this->percentageChange((float) $point['value'], (float) $revenue[$index]['value']),
                ];
            })
            ->all();
    }

    public function recentActivities(int $limit = 8): Collection
    {
        return ActivityLog::query()
            ->with(['user:id,name,email', 'tenant:id,name,status'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function latestGyms(int $limit = 6): Collection
    {
        return Tenant::query()
            ->with(['owner:id,name,email', 'activeSubscription.plan:id,name,plan_type'])
            ->withCount(['members', 'trainers'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function planDistribution(): array
    {
        $distribution = TenantSubscription::query()
            ->join('platform_plans', 'platform_plans.id', '=', 'tenant_subscriptions.plan_id')
            ->select('platform_plans.name as label', DB::raw('COUNT(*) as value'))
            ->whereIn('tenant_subscriptions.status', ['active', 'trial'])
            ->groupBy('platform_plans.id', 'platform_plans.name')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'value' => (int) $row->value,
            ])
            ->all();

        if ($distribution !== []) {
            return $distribution;
        }

        return PlatformPlan::query()
            ->select('name as label')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn ($plan) => ['label' => (string) $plan->label, 'value' => 0])
            ->all();
    }

    public function expiringSoon(int $limit = 6): Collection
    {
        return $this->expiringSoonQuery(Carbon::now())
            ->orderBy('end_date')
            ->limit($limit)
            ->get();
    }

    public function failedPayments(int $limit = 6): Collection
    {
        return $this->failedPlatformPaymentQuery()
            ->with(['invoice.tenant:id,name,status', 'invoice.subscription.plan:id,name,plan_type'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function inactiveGyms(int $limit = 6): Collection
    {
        return Tenant::query()
            ->with(['owner:id,name,email', 'activeSubscription.plan:id,name,plan_type'])
            ->withCount(['members', 'trainers'])
            ->whereIn('status', ['inactive', 'suspended'])
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    public function topGymsByRevenue(int $limit = 5): Collection
    {
        return Tenant::query()
            ->select('tenants.*')
            ->selectSub(function ($query) {
                $query->from('payments')
                    ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->whereColumn('payments.tenant_id', 'tenants.id')
                    ->whereNull('payments.member_id')
                    ->whereNull('payments.membership_id')
                    ->whereNull('invoices.member_id')
                    ->whereNull('invoices.membership_id')
                    ->whereNotNull('invoices.subscription_id')
                    ->where('payments.status', 'completed')
                    ->where(function ($query) {
                        $query->where('payments.payment_status', 'paid')
                            ->orWhereNull('payments.payment_status');
                    })
                    ->selectRaw('COALESCE(SUM(payments.amount), 0)');
            }, 'platform_revenue')
            ->with(['activeSubscription.plan:id,name,plan_type'])
            ->withCount(['members', 'trainers'])
            ->orderByDesc('platform_revenue')
            ->limit($limit)
            ->get();
    }

    public function mostActiveGyms(int $limit = 5): Collection
    {
        return Tenant::query()
            ->with(['activeSubscription.plan:id,name,plan_type'])
            ->withCount(['members', 'trainers', 'branches'])
            ->orderByDesc('members_count')
            ->orderByDesc('trainers_count')
            ->limit($limit)
            ->get();
    }

    public function revenueTotalsByStatus(): array
    {
        return Payment::query()
            ->whereNull('member_id')
            ->whereNull('membership_id')
            ->whereHas('invoice', function ($query) {
                $query->whereNotNull('subscription_id')
                    ->whereNull('member_id')
                    ->whereNull('membership_id');
            })
            ->select('status', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    protected function percentageChange(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    public function subscriptionStatusBreakdown(): array
    {
        $counts = TenantSubscription::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value);

        $statusKeys = ['active', 'trial', 'expired', 'paused', 'cancelled', 'suspended'];

        return collect($statusKeys)
            ->mapWithKeys(fn ($status) => [$status => $counts->get($status, 0)])
            ->all();
    }

    protected function calculateChurnRate(): float
    {
        $now = Carbon::now();
        $lastMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $activeLastMonth = TenantSubscription::query()
            ->where('created_at', '<=', $lastMonthEnd)
            ->where(function ($query) use ($lastMonthEnd) {
                $query->where('status', 'active')
                      ->orWhere(function ($q) use ($lastMonthEnd) {
                          $q->where('status', 'expired')
                            ->where('grace_period_ends_at', '>', $lastMonthEnd);
                      });
            })
            ->count();

        $cancelledThisMonth = TenantSubscription::query()
            ->whereBetween('cancelled_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        if ($activeLastMonth === 0) {
            return 0.0;
        }

        return round(($cancelledThisMonth / $activeLastMonth) * 100, 2);
    }

    protected function monthlySeries($baseQuery, string $column, int $months, callable $aggregator): array
    {
        $end = Carbon::now()->startOfMonth();
        $start = $end->copy()->subMonths($months - 1);

        $aggregated = $aggregator(
            (clone $baseQuery)->whereBetween($column, [$start->copy()->startOfMonth(), $end->copy()->endOfMonth()])
        );

        return collect(range(0, $months - 1))
            ->map(function (int $offset) use ($start, $aggregated) {
                $month = $start->copy()->addMonths($offset);
                $key = $month->format('Y-m');

                return [
                    'label' => $month->format('M Y'),
                    'value' => (float) ($aggregated[$key] ?? 0),
                ];
            })
            ->all();
    }
}

<?php

namespace App\Repositories\SuperAdmin;

use App\Models\ActivityLog;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Trainer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    public function metricSummary(): array
    {
        $now = Carbon::now();

        return [
            'total_gyms' => Tenant::count(),
            'active_gyms' => Tenant::where('status', 'active')->count(),
            'inactive_gyms' => Tenant::where('status', 'inactive')->count(),
            'total_members' => Member::count(),
            'total_trainers' => Trainer::count(),
            'monthly_revenue' => (float) Payment::query()
                ->where('status', 'completed')
                ->whereYear('created_at', $now->year)
                ->whereMonth('created_at', $now->month)
                ->sum('amount'),
            'yearly_revenue' => (float) Payment::query()
                ->where('status', 'completed')
                ->whereYear('created_at', $now->year)
                ->sum('amount'),
            'active_subscriptions' => TenantSubscription::query()
                ->whereIn('status', ['active', 'trial'])
                ->where(function ($query) use ($now) {
                    $query->whereDate('end_date', '>=', $now->toDateString())
                          ->orWhere(function ($q) {
                              $q->where('status', 'expired')
                                ->where('grace_period_ends_at', '>', $now);
                          });
                })
                ->count(),
            'expired_subscriptions' => TenantSubscription::query()
                ->where(function ($query) use ($now) {
                    $query->where('status', 'expired')
                          ->where(function ($q) use ($now) {
                              $q->whereNull('grace_period_ends_at')
                                ->orWhere('grace_period_ends_at', '<=', $now);
                          })
                          ->orWhereDate('end_date', '<', $now->toDateString());
                })
                ->count(),
            'trial_subscriptions' => TenantSubscription::where('status', 'trial')->count(),
            'cancelled_subscriptions' => TenantSubscription::where('status', 'cancelled')->count(),
            'suspended_subscriptions' => TenantSubscription::where('status', 'suspended')->count(),
            'paused_subscriptions' => TenantSubscription::where('status', 'paused')->count(),
            'total_subscriptions' => TenantSubscription::count(),
            'monthly_recurring_revenue' => (float) TenantSubscription::query()
                ->whereIn('status', ['active', 'trial'])
                ->where(function ($query) use ($now) {
                    $query->whereDate('end_date', '>=', $now->toDateString())
                          ->orWhere(function ($q) {
                              $q->where('status', 'expired')
                                ->where('grace_period_ends_at', '>', $now);
                          });
                })
                ->sum('final_amount'),
            'renewals_this_month' => TenantSubscription::query()
                ->whereYear('updated_at', $now->year)
                ->whereMonth('updated_at', $now->month)
                ->where('status', 'active')
                ->whereDate('end_date', '>', $now->toDateString())
                ->count(),
            'churn_rate' => $this->calculateChurnRate(),
        ];
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
            Payment::query()->where('status', 'completed'),
            'created_at',
            $months,
            fn ($query) => $query->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period, COALESCE(SUM(amount), 0) as total")
                ->groupBy('period')
                ->pluck('total', 'period')
        );
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

    public function revenueTotalsByStatus(): array
    {
        return Payment::query()
            ->select('status', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    public function subscriptionStatusBreakdown(): array
    {
        return TenantSubscription::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    protected function calculateChurnRate(): float
    {
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();

        $activeLastMonth = TenantSubscription::query()
            ->where('created_at', '<=', $lastMonth->endOfMonth())
            ->where(function ($query) use ($lastMonth) {
                $query->where('status', 'active')
                      ->orWhere(function ($q) use ($lastMonth) {
                          $q->where('status', 'expired')
                            ->where('grace_period_ends_at', '>', $lastMonth->endOfMonth());
                      });
            })
            ->count();

        $cancelledThisMonth = TenantSubscription::query()
            ->whereBetween('cancelled_at', [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()])
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

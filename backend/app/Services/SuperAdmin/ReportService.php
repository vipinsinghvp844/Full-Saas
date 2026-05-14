<?php

namespace App\Services\SuperAdmin;

use App\Repositories\SuperAdmin\DashboardRepository;
use App\Repositories\SuperAdmin\GymRepository;
use App\Repositories\SuperAdmin\SubscriptionRepository;
use Carbon\Carbon;

class ReportService
{
    public function __construct(
        protected DashboardRepository $dashboardRepository,
        protected GymRepository $gymRepository,
        protected SubscriptionRepository $subscriptionRepository
    ) {
    }

    public function overview(): array
    {
        $metrics = $this->dashboardRepository->metricSummary();

        return [
            'summary' => [
                'monthly_revenue' => $metrics['monthly_revenue'],
                'yearly_revenue' => $metrics['yearly_revenue'],
                'total_gyms' => $metrics['total_gyms'],
                'active_subscriptions' => $metrics['active_subscriptions'],
            ],
            'revenue_growth' => $this->dashboardRepository->revenueGrowthSeries(),
            'gym_growth' => $this->dashboardRepository->gymGrowthSeries(),
            'subscription_breakdown' => $this->dashboardRepository->subscriptionStatusBreakdown(),
        ];
    }

    public function revenueReport(): array
    {
        $statusTotals = $this->dashboardRepository->revenueTotalsByStatus();

        return [
            'summary' => [
                'completed' => $statusTotals['completed'] ?? 0,
                'pending' => $statusTotals['pending'] ?? 0,
                'failed' => $statusTotals['failed'] ?? 0,
                'refunded' => $statusTotals['refunded'] ?? 0,
            ],
            'series' => $this->dashboardRepository->revenueGrowthSeries(),
        ];
    }

    public function gymGrowthReport(): array
    {
        $metrics = $this->dashboardRepository->metricSummary();
        $gymsThisMonth = $this->gymRepository->paginate([
            'per_page' => 5,
            'sort_by' => 'created_at',
            'sort_direction' => 'desc',
        ]);

        return [
            'summary' => [
                'total_gyms' => $metrics['total_gyms'],
                'active_gyms' => $metrics['active_gyms'],
                'inactive_gyms' => $metrics['inactive_gyms'],
                'report_generated_at' => Carbon::now()->toDateTimeString(),
            ],
            'series' => $this->dashboardRepository->gymGrowthSeries(),
            'latest_gyms' => $gymsThisMonth->getCollection(),
        ];
    }

    public function subscriptionReport(): array
    {
        $this->subscriptionRepository->syncExpiredStatuses();
        $metrics = $this->dashboardRepository->metricSummary();

        return [
            'summary' => [
                'active' => $metrics['active_subscriptions'],
                'expired' => $metrics['expired_subscriptions'],
                'trial' => $metrics['trial_subscriptions'],
                'paused' => $metrics['paused_subscriptions'],
                'cancelled' => $metrics['cancelled_subscriptions'],
                'suspended' => $metrics['suspended_subscriptions'],
                'monthly_recurring_revenue' => $metrics['monthly_recurring_revenue'],
                'renewals_this_month' => $metrics['renewals_this_month'],
                'expiring_soon' => $this->subscriptionRepository->expiringSoon()->count(),
            ],
            'breakdown' => $this->dashboardRepository->subscriptionStatusBreakdown(),
            'expiring_soon' => $this->subscriptionRepository->expiringSoon(),
        ];
    }
}

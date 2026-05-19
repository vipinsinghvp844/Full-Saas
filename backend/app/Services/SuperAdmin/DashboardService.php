<?php

namespace App\Services\SuperAdmin;

use App\Repositories\SuperAdmin\DashboardRepository;
use App\Repositories\SuperAdmin\SubscriptionRepository;

class DashboardService
{
    public function __construct(
        protected DashboardRepository $dashboardRepository,
        protected SubscriptionRepository $subscriptionRepository
    ) {
    }

    public function summary(): array
    {
        $this->subscriptionRepository->syncExpiredStatuses();

        return [
            'metrics' => $this->dashboardRepository->metricSummary(),
            'charts' => [
                'gym_growth' => $this->dashboardRepository->gymGrowthSeries(),
                'revenue_growth' => $this->dashboardRepository->revenueGrowthSeries(),
                'growth_rate' => $this->dashboardRepository->growthRateSeries(),
                'member_growth' => $this->dashboardRepository->memberGrowthSeries(),
                'plan_distribution' => $this->dashboardRepository->planDistribution(),
            ],
            'alerts' => [
                'expiring_subscriptions' => $this->dashboardRepository->expiringSoon(),
                'failed_payments' => $this->dashboardRepository->failedPayments(),
                'inactive_gyms' => $this->dashboardRepository->inactiveGyms(),
            ],
            'recent_activity' => $this->dashboardRepository->recentActivities(),
            'latest_gyms' => $this->dashboardRepository->latestGyms(),
            'top_gyms' => [
                'highest_paying' => $this->dashboardRepository->topGymsByRevenue(),
                'most_active' => $this->dashboardRepository->mostActiveGyms(),
            ],
        ];
    }
}

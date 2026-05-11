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
                'member_growth' => $this->dashboardRepository->memberGrowthSeries(),
            ],
            'recent_activity' => $this->dashboardRepository->recentActivities(),
            'latest_gyms' => $this->dashboardRepository->latestGyms(),
        ];
    }
}

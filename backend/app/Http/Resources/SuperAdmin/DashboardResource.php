<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'metrics' => $this['metrics'],
            'charts' => $this['charts'],
            'alerts' => [
                'expiring_subscriptions' => SubscriptionResource::collection($this['alerts']['expiring_subscriptions'])->resolve(),
                'failed_payments' => PaymentResource::collection($this['alerts']['failed_payments'])->resolve(),
                'inactive_gyms' => GymResource::collection($this['alerts']['inactive_gyms'])->resolve(),
            ],
            'recent_activity' => ActivityResource::collection($this['recent_activity'])->resolve(),
            'latest_gyms' => GymResource::collection($this['latest_gyms'])->resolve(),
            'top_gyms' => [
                'highest_paying' => GymResource::collection($this['top_gyms']['highest_paying'])->resolve(),
                'most_active' => GymResource::collection($this['top_gyms']['most_active'])->resolve(),
            ],
        ];
    }
}

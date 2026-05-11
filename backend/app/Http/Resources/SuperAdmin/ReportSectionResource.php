<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this['summary'] ?? [],
            'series' => $this['series'] ?? [],
            'breakdown' => $this['breakdown'] ?? [],
            'expiring_soon' => isset($this['expiring_soon'])
                ? SubscriptionResource::collection($this['expiring_soon'])->resolve()
                : [],
            'latest_gyms' => isset($this['latest_gyms'])
                ? GymResource::collection($this['latest_gyms'])->resolve()
                : [],
            'revenue_growth' => $this['revenue_growth'] ?? [],
            'gym_growth' => $this['gym_growth'] ?? [],
            'subscription_breakdown' => $this['subscription_breakdown'] ?? [],
        ];
    }
}

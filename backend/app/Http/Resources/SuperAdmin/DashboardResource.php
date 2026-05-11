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
            'recent_activity' => ActivityResource::collection($this['recent_activity'])->resolve(),
            'latest_gyms' => GymResource::collection($this['latest_gyms'])->resolve(),
        ];
    }
}

<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'plan_type' => $this->plan_type,
            'price' => $this->price,
            'duration' => $this->duration,
            'discount' => $this->discount,
            'max_members' => $this->max_members,
            'max_trainers' => $this->max_trainers,
            'max_branches' => $this->max_branches,
            'features' => $this->features ?? [],
            'status' => $this->status,
            'subscriptions_count' => $this->subscriptions_count ?? 0,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

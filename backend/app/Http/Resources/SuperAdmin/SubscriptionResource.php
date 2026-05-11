<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status, // active, expired, paused, trial
            'status_label' => ucfirst($this->status),
            'payment_status' => $this->payment_status, // paid, pending, failed
            'final_amount' => round((float) ($this->final_amount ?? 0.0), 2),
            
            // Standardized Dates
            'start_date' => $this->start_date ? $this->start_date->toDateString() : null,
            'end_date' => $this->end_date ? $this->end_date->toDateString() : null,
            'renewal_date' => $this->renewal_date ? $this->renewal_date->toDateString() : null,
            'next_billing_date' => $this->next_billing_date ? $this->next_billing_date->toDateString() : null,
            
            // Relationships
            'tenant' => [
                'id' => $this->tenant?->id,
                'name' => $this->tenant?->name,
                'email' => $this->tenant?->email,
            ],
            'plan' => new PlanResource($this->whenLoaded('plan')),
            
            // Metadata
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Http\Resources\SuperAdmin\PlanResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenant = $this->tenant;
        $owner = $tenant?->owner;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => ucfirst($this->status),
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'price' => round((float) ($this->price ?? 0.0), 2),
            'discount_amount' => round((float) ($this->discount_amount ?? 0.0), 2),
            'final_amount' => round((float) ($this->final_amount ?? 0.0), 2),

            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'renewal_date' => $this->renewal_date?->toDateString(),
            'next_billing_date' => $this->next_billing_date?->toDateString(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'paused_at' => $this->paused_at?->toDateTimeString(),
            'resumed_at' => $this->resumed_at?->toDateTimeString(),

            'tenant' => [
                'id' => $tenant?->id,
                'name' => $tenant?->name,
                'email' => $tenant?->email,
                'status' => $tenant?->status,
                'owner_id' => optional($owner)->id,
                'owner_name' => optional($owner)->name,
                'owner_email' => optional($owner)->email,
            ],
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'coupon' => $this->whenLoaded('coupon', fn () => [
                'id' => $this->coupon?->id,
                'code' => $this->coupon?->code,
                'discount_type' => $this->coupon?->discount_type,
                'discount_value' => $this->coupon?->discount_value,
            ]),
            'billing' => [
                'start_date' => $this->start_date?->toDateString(),
                'end_date' => $this->end_date?->toDateString(),
                'renewal_date' => $this->renewal_date?->toDateString(),
                'next_billing_date' => $this->next_billing_date?->toDateString(),
                'payment_status' => $this->payment_status,
                'payment_method' => $this->payment_method,
                'final_amount' => round((float) ($this->final_amount ?? 0.0), 2),
            ],
            'is_expired' => $this->status === 'expired' || ($this->end_date && now()->gt($this->end_date)),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

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
            'tenant' => [
                'id' => $this->tenant?->id,
                'name' => $this->tenant?->name,
                'status' => $this->tenant?->status,
                'owner_name' => $this->tenant?->owner?->name,
                'owner_email' => $this->tenant?->owner?->email,
            ],
            'plan' => [
                'id' => $this->plan?->id,
                'name' => $this->plan?->name,
                'plan_type' => $this->plan?->plan_type,
                'price' => $this->plan?->price,
            ],
            'coupon' => $this->coupon ? [
                'id' => $this->coupon->id,
                'code' => $this->coupon->code,
                'discount_type' => $this->coupon->discount_type,
                'discount_value' => $this->coupon->discount_value,
            ] : null,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'price' => $this->price,
            'discount_amount' => $this->discount_amount,
            'final_amount' => $this->final_amount,
            'payment_method' => $this->payment_method,
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'is_expired' => $this->end_date?->isPast() ?? false,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

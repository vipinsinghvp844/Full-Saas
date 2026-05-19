<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant?->id,
                'name' => $this->tenant?->name,
            ]),
            'tenant_id' => $this->tenant_id,
            'code' => $this->code,
            'type' => $this->discount_type,
            'value' => $this->discount_value,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'max_discount' => $this->max_discount,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_to?->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
            'usage_limit' => $this->usage_limit,
            'used_count' => $this->used_count,
            'status' => $this->status,
            'is_expired' => $this->isExpired(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

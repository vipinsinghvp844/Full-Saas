<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'gym' => [
                'id' => $this->invoice?->tenant?->id,
                'name' => $this->invoice?->tenant?->name,
            ],
            'subscription' => [
                'id' => $this->invoice?->subscription?->id,
                'plan_name' => $this->invoice?->subscription?->plan?->name,
            ],
            'invoice' => [
                'id' => $this->invoice?->id,
                'status' => $this->invoice?->status,
                'due_date' => $this->invoice?->due_date?->toDateString(),
            ],
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}

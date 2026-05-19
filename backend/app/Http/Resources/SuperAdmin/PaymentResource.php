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
            'discount' => $this->discount ?? 0,
            'final_amount' => $this->final_amount ?? $this->amount,
            'payment_method' => $this->payment_method,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'payment_status' => $this->payment_status ?? match ($this->status) {
                'completed' => 'paid',
                'failed' => 'failed',
                default => 'pending',
            },
            'paid_at' => $this->paid_at?->toDateTimeString(),
            'notes' => $this->notes,
            'source' => in_array($this->payment_method, ['stripe', 'razorpay'], true)
                ? "{$this->payment_method}_webhook"
                : 'manual_platform_billing',
            'gym' => [
                'id' => $this->invoice?->tenant?->id ?? $this->tenant?->id,
                'name' => $this->invoice?->tenant?->name ?? $this->tenant?->name,
                'status' => $this->invoice?->tenant?->status ?? $this->tenant?->status,
            ],
            'subscription' => [
                'id' => $this->invoice?->subscription?->id,
                'plan_name' => $this->invoice?->subscription?->plan?->name,
                'plan_type' => $this->invoice?->subscription?->plan?->plan_type,
            ],
            'invoice' => [
                'id' => $this->invoice?->id,
                'invoice_number' => $this->invoice?->invoice_number,
                'status' => $this->invoice?->status,
                'due_date' => $this->invoice?->due_date?->toDateString(),
                'amount' => $this->invoice?->amount,
                'final_amount' => $this->invoice?->final_amount,
            ],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

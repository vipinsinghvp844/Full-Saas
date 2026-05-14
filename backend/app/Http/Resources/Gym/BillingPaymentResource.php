<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Resources\Json\JsonResource;

class BillingPaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'member' => $this->whenLoaded('member', function () {
                return [
                    'id' => $this->member?->id,
                    'name' => $this->member?->user?->name,
                    'email' => $this->member?->user?->email,
                    'phone' => $this->member?->phone,
                ];
            }),
            'membership_id' => $this->membership_id,
            'membership' => $this->whenLoaded('membership', function () {
                if (! $this->membership) {
                    return null;
                }

                return [
                    'id' => $this->membership->id,
                    'plan' => [
                        'id' => $this->membership->plan?->id,
                        'name' => $this->membership->plan?->name,
                    ],
                    'start_date' => optional($this->membership->start_date)->toDateString(),
                    'end_date' => optional($this->membership->end_date)->toDateString(),
                    'payment_status' => $this->membership->payment_status,
                ];
            }),
            'invoice' => $this->whenLoaded('invoice', function () {
                return [
                    'id' => $this->invoice?->id,
                    'invoice_number' => $this->invoice?->invoice_number,
                    'status' => $this->invoice?->status,
                    'due_date' => optional($this->invoice?->due_date)->toDateString(),
                ];
            }),
            'amount' => $this->amount,
            'discount' => $this->discount,
            'final_amount' => $this->final_amount,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'transaction_id' => $this->transaction_id,
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}

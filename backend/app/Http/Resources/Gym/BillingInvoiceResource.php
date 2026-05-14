<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Resources\Json\JsonResource;

class BillingInvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
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
            'total_amount' => $this->total_amount ?? $this->amount,
            'discount' => $this->discount,
            'final_amount' => $this->final_amount ?? $this->amount,
            'status' => $this->status,
            'due_date' => optional($this->due_date)->toDateString(),
            'payments_count' => $this->whenCounted('payments'),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}

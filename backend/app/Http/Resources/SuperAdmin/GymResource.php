<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GymResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'logo_url' => $this->logo_url,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'gst_number' => $this->gst_number,
            'status' => $this->status,
            'owner' => [
                'id' => $this->owner?->id,
                'name' => $this->owner?->name,
                'email' => $this->owner?->email,
            ],
            'counts' => [
                'members' => $this->members_count ?? 0,
                'trainers' => $this->trainers_count ?? 0,
                'branches' => $this->branches_count ?? 0,
                'subscriptions' => $this->subscriptions_count ?? 0,
            ],
            'platform_revenue' => (float) ($this->platform_revenue ?? 0),
            'active_subscription' => $this->whenLoaded('activeSubscription', function () {
                if (! $this->activeSubscription) {
                    return null;
                }

                return [
                    'id' => $this->activeSubscription->id,
                    'plan_name' => $this->activeSubscription->plan?->name,
                    'plan_type' => $this->activeSubscription->plan?->plan_type,
                    'status' => $this->activeSubscription->status,
                    'start_date' => $this->activeSubscription->start_date?->toDateString(),
                    'end_date' => $this->activeSubscription->end_date?->toDateString(),
                    'final_amount' => $this->activeSubscription->final_amount,
                ];
            }),
            'subscriptions' => $this->whenLoaded('subscriptions', fn () => SubscriptionResource::collection($this->subscriptions)->resolve()),
            'recent_invoices' => $this->whenLoaded('invoices', function () {
                return $this->invoices
                    ->sortByDesc('created_at')
                    ->take(5)
                    ->map(fn ($invoice) => [
                        'id' => $invoice->id,
                        'amount' => $invoice->amount,
                        'status' => $invoice->status,
                        'due_date' => $invoice->due_date?->toDateString(),
                        'payments_count' => $invoice->payments->count(),
                    ])
                    ->values()
                    ->all();
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

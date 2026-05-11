<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Use model attributes for derivation but exclude them from final output
        $derivedPlanType = $this->plan_type ?? match (true) {
            (int) $this->duration === 1 => 'monthly',
            (int) $this->duration === 3 => 'quarterly',
            (int) $this->duration === 12 => 'yearly',
            default => 'custom',
        };

        $billingCycle = $derivedPlanType;

        $durationDays = match ($derivedPlanType) {
            'monthly' => 30,
            'quarterly' => 90,
            'yearly' => 365,
            default => 30,
        };

        $durationMonthsNormalized = (int) $this->duration ?: match ($derivedPlanType) {
            'monthly' => 1,
            'quarterly' => 3,
            'yearly' => 12,
            default => 1,
        };

        // Fix precision: Rounding to 2 decimals for currency fields
        $basePrice = round((float) ($this->price ?? 0.0), 2);
        $discountPercentage = round((float) ($this->discount ?? 0.0), 2);
        $finalPrice = round($basePrice * (1 - ($discountPercentage / 100)), 2);

        $features = is_array($this->features) ? $this->features : [];

        $addons = match ($derivedPlanType) {
            'quarterly' => ['gym bottle', 'gym bag'],
            'yearly' => ['gym bottle', 'gym bag', 'diet consultation', 'PT credits', 'priority support'],
            default => [],
        };

        $isUnlimited = is_null($this->max_members) && is_null($this->max_trainers) && is_null($this->max_branches);

        $slug = $this->slug ?: \Illuminate\Support\Str::slug((string) $this->name);

        return [
            // Normalized fields only
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $slug,
            'description' => $this->description ?? '',
            'billing_cycle' => $billingCycle,
            'duration_days' => $durationDays,
            'duration_months' => $durationMonthsNormalized,
            'base_price' => $basePrice,
            'discount_percentage' => $discountPercentage,
            'final_price' => $finalPrice,
            'max_members' => $this->max_members,
            'max_trainers' => $this->max_trainers,
            'max_branches' => $this->max_branches,
            // Future-proofing other limits
            'max_staff' => null,
            'max_classes' => null,
            'max_inventory_items' => null,
            'is_unlimited' => $isUnlimited,
            'features' => $features,
            'addons' => $addons,
            'status' => $this->status ?? 'active',
            'subscriptions_count' => $this->subscriptions_count ?? 0,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\PlatformPlan;
use App\Models\TenantSubscription;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TenantSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();
        $plans = PlatformPlan::all();

        if ($tenants->isEmpty() || $plans->isEmpty()) {
            return;
        }

        // Use plan_type to find specific plans
        $planMap = $plans->keyBy('name');

        foreach ($tenants as $index => $tenant) {
            // Scenario 1: Active Yearly Subscription (Enterprise Example)
            if ($index === 0) {
                $this->createSub($tenant, $planMap['Yearly Plan'] ?? $plans->first(), 'active', 'paid', now()->subMonths(2));
                continue;
            }

            // Scenario 2: Expired Subscription (Red Flag)
            if ($index === 1) {
                $this->createSub($tenant, $planMap['Monthly Plan'] ?? $plans->first(), 'expired', 'paid', now()->subMonths(3), now()->subMonths(2));
                continue;
            }

            // Scenario 3: Paused Subscription (Yellow)
            if ($index === 2) {
                $this->createSub($tenant, $planMap['Quarterly Plan'] ?? $plans->first(), 'paused', 'paid', now()->subMonths(1));
                continue;
            }

            // Scenario 4: Trial Subscription (Blue)
            if ($index === 3) {
                $this->createSub($tenant, $planMap['Monthly Plan'] ?? $plans->first(), 'trial', 'pending', now());
                continue;
            }
        }
    }

    private function createSub($tenant, $plan, $status, $payStatus, $start, $end = null)
    {
        $startDate = Carbon::parse($start);
        $duration = (int) ($plan->duration ?: 1);
        
        if (!$end) {
            $endDate = (clone $startDate)->addMonths($duration);
        } else {
            $endDate = Carbon::parse($end);
        }

        // Using the normalized pricing logic from PlanResource
        $finalPrice = $plan->price * (1 - (($plan->discount ?? 0) / 100));

        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'renewal_date' => $endDate,
            'next_billing_date' => $endDate,
            'payment_status' => $payStatus,
            'final_amount' => round($finalPrice, 2),
        ]);
    }
}
<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\PlatformPlan;
use App\Models\TenantSubscription;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class TenantSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $plans = PlatformPlan::all()->keyBy('name');

        if ($plans->isEmpty()) {
            return;
        }

        $subscriptions = [
            [
                'slug' => 'power-house-gym',
                'name' => 'Power House Gym',
                'email' => 'info@powerhousegym.com',
                'phone' => '9876543210',
                'address' => '123 Fitness Avenue, City Center',
                'status' => 'active',
                'owner_name' => 'Power House Gym Admin',
                'owner_email' => 'admin@powerhousegym.com',
                'plan' => 'Yearly Plan',
                'subscription_status' => 'active',
                'payment_status' => 'paid',
                'start_date' => '-2 months',
                'end_date' => '+10 months',
                'renewal_date' => '+10 months',
            ],
            [
                'slug' => 'iron-core-gym',
                'name' => 'Iron Core Gym',
                'email' => 'info@ironcoregym.com',
                'phone' => '9123456780',
                'address' => '56 Strength Street, Uptown',
                'status' => 'active',
                'owner_name' => 'Iron Core Gym Owner',
                'owner_email' => 'admin@ironcoregym.com',
                'plan' => 'Monthly Plan',
                'subscription_status' => 'expired',
                'payment_status' => 'paid',
                'start_date' => '-5 months',
                'end_date' => '-2 months',
            ],
            [
                'slug' => 'pulse-fitness',
                'name' => 'Pulse Fitness',
                'email' => 'info@pulsefitness.com',
                'phone' => '9988776655',
                'address' => '89 Energy Boulevard, Downtown',
                'status' => 'active',
                'owner_name' => 'Pulse Fitness Admin',
                'owner_email' => 'admin@pulsefitness.com',
                'plan' => 'Quarterly Plan',
                'subscription_status' => 'paused',
                'payment_status' => 'paid',
                'start_date' => '-3 months',
                'end_date' => '+1 month',
                'paused_at' => '-7 days',
            ],
            [
                'slug' => 'flex-factory',
                'name' => 'Flex Factory',
                'email' => 'info@flexfactory.com',
                'phone' => '9012345678',
                'address' => '42 Strength Lane, Westside',
                'status' => 'active',
                'owner_name' => 'Flex Factory Admin',
                'owner_email' => 'admin@flexfactory.com',
                'plan' => 'Monthly Plan',
                'subscription_status' => 'trial',
                'payment_status' => 'pending',
                'start_date' => 'now',
                'end_date' => '+14 days',
            ],
            [
                'slug' => 'peak-performance-club',
                'name' => 'Peak Performance Club',
                'email' => 'info@peakperformance.com',
                'phone' => '9246813570',
                'address' => '101 Muscle Drive, Midtown',
                'status' => 'active',
                'owner_name' => 'Peak Performance Admin',
                'owner_email' => 'admin@peakperformance.com',
                'plan' => 'Monthly Plan',
                'subscription_status' => 'active',
                'payment_status' => 'paid',
                'start_date' => '-1 month',
                'end_date' => '+15 days',
                'renewal_date' => '+15 days',
            ],
            [
                'slug' => 'zen-fitness-studio',
                'name' => 'Zen Fitness Studio',
                'email' => 'info@zenfitness.com',
                'phone' => '9393939393',
                'address' => '225 Serenity Road, Lakeview',
                'status' => 'active',
                'owner_name' => 'Zen Fitness Admin',
                'owner_email' => 'admin@zenfitness.com',
                'plan' => 'Yearly Plan',
                'subscription_status' => 'expired',
                'payment_status' => 'paid',
                'start_date' => '-13 months',
                'end_date' => '-1 month',
                'grace_period_ends_at' => '+10 days',
            ],
            [
                'slug' => 'velocity-health',
                'name' => 'Velocity Health',
                'email' => 'info@velocityhealth.com',
                'phone' => '9112233445',
                'address' => '77 Speed Lane, Downtown',
                'status' => 'active',
                'owner_name' => 'Velocity Health Admin',
                'owner_email' => 'admin@velocityhealth.com',
                'plan' => 'Quarterly Plan',
                'subscription_status' => 'cancelled',
                'payment_status' => 'paid',
                'start_date' => '-5 months',
                'end_date' => '-1 month',
                'cancelled_at' => '-2 days',
            ],
            [
                'slug' => 'summit-strength',
                'name' => 'Summit Strength',
                'email' => 'info@summitstrength.com',
                'phone' => '9001122334',
                'address' => '65 Summit Street, Hilltop',
                'status' => 'active',
                'owner_name' => 'Summit Strength Admin',
                'owner_email' => 'admin@summitstrength.com',
                'plan' => 'Yearly Plan',
                'subscription_status' => 'suspended',
                'payment_status' => 'paid',
                'start_date' => '-2 months',
                'end_date' => '+3 months',
            ],
            [
                'slug' => 'core-balance-gym',
                'name' => 'Core Balance Gym',
                'email' => 'info@corebalancegym.com',
                'phone' => '9034567890',
                'address' => '11 Balance Blvd, Riverside',
                'status' => 'active',
                'owner_name' => 'Core Balance Owner',
                'owner_email' => 'admin@corebalancegym.com',
                'plan' => 'Yearly Plan',
                'subscription_status' => 'active',
                'payment_status' => 'paid',
                'start_date' => '-1 year',
                'end_date' => '+1 year',
                'renewal_date' => 'now',
            ],
            [
                'slug' => 'urban-flex',
                'name' => 'Urban Flex',
                'email' => 'info@urbanflex.com',
                'phone' => '9023456781',
                'address' => '88 Urban Avenue, Central',
                'status' => 'active',
                'owner_name' => 'Urban Flex Admin',
                'owner_email' => 'admin@urbanflex.com',
                'plan' => 'Monthly Plan',
                'subscription_status' => 'active',
                'payment_status' => 'paid',
                'start_date' => '-10 days',
                'end_date' => '+20 days',
                'renewal_date' => '+20 days',
            ],
        ];

        foreach ($subscriptions as $item) {
            $startDate = Carbon::parse($item['start_date']);

            $tenant = Tenant::firstOrNew(['slug' => $item['slug']]);
            $tenant->fill([
                'name' => $item['name'],
                'email' => $item['email'],
                'phone' => $item['phone'],
                'address' => $item['address'],
                'status' => $item['status'],
            ]);

            if (! $tenant->exists) {
                $tenantCreationDate = $startDate->copy()->subDays(2);
                if ($tenantCreationDate->gt(Carbon::now())) {
                    $tenantCreationDate = Carbon::now();
                }
                $tenant->created_at = $tenantCreationDate;
                $tenant->updated_at = $tenantCreationDate;
            }

            $tenant->save();

            $owner = User::updateOrCreate(
                ['email' => $item['owner_email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $item['owner_name'],
                    'password' => bcrypt('password'),
                ]
            );

            $tenant->update(['owner_user_id' => $owner->id]);

            $plan = $plans->get($item['plan']) ?? $plans->first();
            $endDate = Carbon::parse($item['end_date'] ?? $startDate->copy()->addMonths($plan->duration));
            $renewalDate = isset($item['renewal_date'])
                ? Carbon::parse($item['renewal_date'])
                : $endDate;
            $gracePeriodEndsAt = isset($item['grace_period_ends_at'])
                ? Carbon::parse($item['grace_period_ends_at'])
                : null;
            $cancelledAt = isset($item['cancelled_at'])
                ? Carbon::parse($item['cancelled_at'])
                : null;
            $pausedAt = isset($item['paused_at'])
                ? Carbon::parse($item['paused_at'])
                : null;
            $finalAmount = $this->calculateFinalAmount($plan, $item['subscription_status']);

            $subscription = TenantSubscription::firstOrNew([
                'tenant_id' => $tenant->id,
            ]);

            $subscription->fill([
                'plan_id' => $plan->id,
                'status' => $item['subscription_status'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'renewal_date' => $renewalDate,
                'next_billing_date' => $item['next_billing_date'] ?? $endDate,
                'grace_period_ends_at' => $gracePeriodEndsAt,
                'payment_status' => $item['payment_status'],
                'payment_method' => 'manual',
                'price' => round((float) $plan->price, 2),
                'discount_amount' => round((float) $plan->discount, 2),
                'final_amount' => round($finalAmount, 2),
                'paused_at' => $pausedAt,
                'cancelled_at' => $cancelledAt,
            ]);

            if (! $subscription->exists) {
                $subscription->created_at = $startDate;
            }

            $subscription->save();

            $this->seedInvoicesAndPayments($tenant, $subscription, $plan, $startDate, $endDate, $item['payment_status']);
        }
    }

    private function calculateFinalAmount($plan, string $status): float
    {
        if ($status === 'trial') {
            return 0.0;
        }

        $price = (float) $plan->price;
        $discount = (float) ($plan->discount ?? 0);

        return round($price * (1 - ($discount / 100)), 2);
    }

    private function billingIntervalMonths(PlatformPlan $plan): int
    {
        return match ($plan->plan_type) {
            'yearly' => 12,
            'quarterly' => 3,
            'monthly' => 1,
            default => (int) $plan->duration ?: 1,
        };
    }

    private function seedInvoicesAndPayments(
        Tenant $tenant,
        TenantSubscription $subscription,
        PlatformPlan $plan,
        Carbon $startDate,
        Carbon $endDate,
        string $paymentStatus
    ): void {
        $intervalMonths = max(1, $this->billingIntervalMonths($plan));
        $cycleDate = $startDate->copy()->startOfDay();
        $lastCycleDate = $endDate->copy()->startOfDay();

        while ($cycleDate->lte($lastCycleDate)) {
            $invoice = Invoice::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'due_date' => $cycleDate->toDateString(),
                ],
                [
                    'amount' => round($subscription->final_amount, 2),
                    'total_amount' => round($subscription->final_amount, 2),
                    'discount' => 0,
                    'final_amount' => round($subscription->final_amount, 2),
                    'status' => $paymentStatus === 'paid' ? 'paid' : 'pending',
                    'created_at' => $cycleDate,
                    'updated_at' => $cycleDate,
                ]
            );

            if (! $invoice->invoice_number) {
                $invoice->update([
                    'invoice_number' => sprintf('SAAS-%s-%06d', $cycleDate->format('Y'), $invoice->id),
                ]);
            }

            if ($paymentStatus === 'paid' && $cycleDate->lte(Carbon::now())) {
                $transactionId = sprintf('txn_%s_%s', $subscription->id, $cycleDate->format('Ymd'));

                $invoice->payments()->updateOrCreate(
                    [
                        'invoice_id' => $invoice->id,
                        'transaction_id' => $transactionId,
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'amount' => round($subscription->final_amount, 2),
                        'discount' => 0,
                        'final_amount' => round($subscription->final_amount, 2),
                        'payment_method' => 'manual',
                        'transaction_id' => $transactionId,
                        'status' => 'completed',
                        'payment_status' => 'paid',
                        'paid_at' => $cycleDate->copy()->addDay(),
                        'created_at' => $cycleDate->copy()->addDay(),
                        'updated_at' => $cycleDate->copy()->addDay(),
                    ]
                );
            }

            $cycleDate->addMonths($intervalMonths);
        }
    }
}

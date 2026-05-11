<?php

namespace Database\Factories;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantSubscription>
 */
class TenantSubscriptionFactory extends Factory
{
    protected $model = TenantSubscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => PlatformPlan::factory(),
            'start_date' => fake()->date(),
            'end_date' => fake()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'status' => fake()->randomElement(['active', 'expired', 'cancelled']),
        ];
    }
}

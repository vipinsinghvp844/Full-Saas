<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => TenantSubscription::factory(),
            'amount' => fake()->randomFloat(2, 50, 1000),
            'status' => fake()->randomElement(['pending', 'paid', 'overdue', 'cancelled']),
            'due_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
        ];
    }
}

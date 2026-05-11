<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'payment_method' => fake()->randomElement(['credit_card', 'paypal', 'cash']),
            'transaction_id' => fake()->uuid(),
            'status' => fake()->randomElement(['pending', 'completed', 'failed', 'refunded']),
        ];
    }
}

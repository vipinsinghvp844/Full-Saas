<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'expense_category_id' => ExpenseCategory::factory(),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'expense_date' => fake()->date(),
            'description' => fake()->sentence(),
        ];
    }
}

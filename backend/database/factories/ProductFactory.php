<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->words(2, true) . ' Product',
            'category' => fake()->randomElement(['Supplements', 'Equipment', 'Apparel']),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 5, 299),
            'stock_quantity' => fake()->numberBetween(10, 200),
            'min_stock' => fake()->numberBetween(1, 10),
        ];
    }
}

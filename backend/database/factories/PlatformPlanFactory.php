<?php

namespace Database\Factories;

use App\Models\PlatformPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformPlan>
 */
class PlatformPlanFactory extends Factory
{
    protected $model = PlatformPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true) . ' Plan',
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 19, 499),
            'duration' => fake()->randomElement([1, 3, 6, 12]),
            'features' => [fake()->words(3, true), fake()->words(4, true)],
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}

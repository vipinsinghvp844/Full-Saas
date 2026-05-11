<?php

namespace Database\Factories;

use App\Models\GymClass;
use App\Models\Tenant;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymClass>
 */
class GymClassFactory extends Factory
{
    protected $model = GymClass::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['Cardio', 'Strength', 'Wellness', 'Mind & Body']),
            'max_participants' => fake()->numberBetween(10, 25),
            'duration_minutes' => fake()->numberBetween(30, 75),
            'trainer_id' => Trainer::factory(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trainer>
 */
class TrainerFactory extends Factory
{
    protected $model = Trainer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'specialization' => fake()->randomElement(['Strength Training', 'Yoga', 'Functional Training', 'Cardio']),
            'experience_years' => fake()->numberBetween(1, 10),
            'certifications' => fake()->sentence(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}

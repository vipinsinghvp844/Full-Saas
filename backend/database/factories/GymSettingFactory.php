<?php

namespace Database\Factories;

use App\Models\GymSetting;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymSetting>
 */
class GymSettingFactory extends Factory
{
    protected $model = GymSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'key' => fake()->randomElement(['currency', 'timezone', 'contact_email', 'contact_phone']),
            'value' => fake()->word(),
        ];
    }
}

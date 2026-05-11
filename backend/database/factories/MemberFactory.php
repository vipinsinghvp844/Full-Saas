<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

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
            'date_of_birth' => fake()->date(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'emergency_contact' => fake()->phoneNumber(),
            'membership_status' => fake()->randomElement(['active', 'inactive', 'expired', 'suspended']),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}

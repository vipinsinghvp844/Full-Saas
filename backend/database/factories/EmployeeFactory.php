<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

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
            'branch_id' => Branch::factory(),
            'position' => fake()->jobTitle(),
            'hire_date' => fake()->date(),
            'salary' => fake()->randomFloat(2, 25000, 60000),
            'status' => fake()->randomElement(['active', 'inactive', 'terminated']),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}

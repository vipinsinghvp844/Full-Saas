<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->randomElement(['dashboard', 'members', 'trainers', 'classes', 'inventory', 'expenses', 'payments']),
            'guard_name' => 'web',
        ];
    }
}

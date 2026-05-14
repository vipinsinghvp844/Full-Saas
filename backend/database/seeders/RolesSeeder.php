<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    protected function getTenant(): Tenant
    {
        return Tenant::firstOrCreate(
            ['slug' => 'power-house-gym'],
            [
                'name' => 'Power House Gym',
                'email' => 'info@powerhousegym.com',
                'phone' => '9876543210',
                'address' => '123 Fitness Avenue, City Center',
                'status' => 'active',
            ]
        );
    }

    public function run(): void
    {
        $tenant = $this->getTenant();

        $roles = [
            'Super Admin',
            'Gym Admin',
            'Manager',
            'Trainer',
            'Receptionist',
            'Accountant',
            'trainer',
            'Member',
        ];

        foreach ($roles as $roleName) {
            Role::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $roleName,
                    'guard_name' => 'web',
                ],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]
            );
        }
    }
}

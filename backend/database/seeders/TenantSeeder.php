<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'power-house-gym'],
            [
                'name' => 'Power House Gym',
                'email' => 'info@powerhousegym.com',
                'phone' => '9876543210',
                'address' => '123 Fitness Avenue, City Center',
                'status' => 'active',
            ]
        );

        $adminUser = User::updateOrCreate(
            ['email' => 'admin@powerhousegym.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Power House Gym Admin',
                'password' => 'password',
            ]
        );

        // Ensure tenant always has a valid owner user for API consistency
        $tenant->update([
            'owner_user_id' => $adminUser->id,
        ]);

        $adminRole = Role::where('tenant_id', $tenant->id)
            ->where('name', 'Gym Admin')
            ->first();

        if ($adminRole) {
            $adminUser->roles()->syncWithoutDetaching([
                $adminRole->id => ['tenant_id' => $tenant->id],
            ]);
        }

        Branch::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'name' => 'Main Branch',
            ],
            [
                'address' => '123 Fitness Avenue, City Center',
                'phone' => '9876543210',
                'manager_id' => null,
                'created_by' => $adminUser->id,
                'updated_by' => $adminUser->id,
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
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

        $user = User::updateOrCreate(
            ['email' => 'admin@gym.com'],
            [
                'tenant_id' => null,
                'name' => 'Super Admin',
                'password' => 'password',
            ]
        );

        $role = Role::where('tenant_id', $tenant->id)
            ->where('name', 'Super Admin')
            ->first();

        if ($role) {
            $user->roles()->syncWithoutDetaching([
                $role->id => ['tenant_id' => $tenant->id],
            ]);
        }
    }
}

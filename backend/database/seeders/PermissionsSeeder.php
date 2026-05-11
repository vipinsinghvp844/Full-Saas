<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
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

        $permissions = [
            'dashboard',
            'members',
            'trainers',
            'classes',
            'inventory',
            'expenses',
            'payments',
        ];

        $permissionIds = [];

        foreach ($permissions as $permissionName) {
            $permission = Permission::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]
            );

            $permissionIds[] = $permission->id;
        }

        $superAdminRole = Role::where('tenant_id', $tenant->id)
            ->where('name', 'Super Admin')
            ->first();

        if ($superAdminRole) {
            $attach = [];

            foreach ($permissionIds as $id) {
                $attach[$id] = ['tenant_id' => $tenant->id];
            }

            $superAdminRole->permissions()->syncWithoutDetaching($attach);
        }
    }
}

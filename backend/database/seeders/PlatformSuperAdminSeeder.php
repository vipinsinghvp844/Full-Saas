<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Platform owner — not a gym (tenant) user.
 * Login: superadmin@platform.com / password
 * Opens: /super-admin/dashboard
 */
class PlatformSuperAdminSeeder extends Seeder
{
    public const EMAIL = 'superadmin@platform.com';

    public const LEGACY_EMAIL = 'admin@gym.com';

    public const PASSWORD = 'password';

    public function run(): void
    {
        $anchorTenant = Tenant::query()->orderBy('id')->first();

        if (! $anchorTenant) {
            $this->command?->warn('PlatformSuperAdminSeeder: no tenant found. Run TenantSubscriptionSeeder first.');

            return;
        }

        $superAdminRole = Role::query()->updateOrCreate(
            [
                'tenant_id' => $anchorTenant->id,
                'name' => 'Super Admin',
                'guard_name' => 'web',
            ],
            [
                'tenant_id' => $anchorTenant->id,
                'name' => 'Super Admin',
                'guard_name' => 'web',
            ]
        );

        $this->attachPlatformPermissions($anchorTenant, $superAdminRole);

        foreach ([self::EMAIL, self::LEGACY_EMAIL] as $email) {
            $this->upsertPlatformUser($email, $superAdminRole, $anchorTenant->id);
        }
    }

    protected function upsertPlatformUser(string $email, Role $role, int $pivotTenantId): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'tenant_id' => null,
                'name' => 'Platform Super Admin',
                'password' => self::PASSWORD,
                'is_active' => true,
            ]
        );

        // Platform owner must not belong to any gym.
        $user->update(['tenant_id' => null]);

        $user->roles()->sync([
            $role->id => ['tenant_id' => $pivotTenantId],
        ]);
    }

    protected function attachPlatformPermissions(Tenant $anchorTenant, Role $superAdminRole): void
    {
        $names = [
            'dashboard',
            'members',
            'trainers',
            'classes',
            'inventory',
            'expenses',
            'payments',
            'settings',
            'reports',
        ];

        $attach = [];

        foreach ($names as $name) {
            $permission = Permission::query()->updateOrCreate(
                [
                    'tenant_id' => $anchorTenant->id,
                    'name' => $name,
                    'guard_name' => 'web',
                ],
                [
                    'tenant_id' => $anchorTenant->id,
                    'name' => $name,
                    'guard_name' => 'web',
                ]
            );

            $attach[$permission->id] = ['tenant_id' => $anchorTenant->id];
        }

        $superAdminRole->permissions()->syncWithoutDetaching($attach);
    }
}

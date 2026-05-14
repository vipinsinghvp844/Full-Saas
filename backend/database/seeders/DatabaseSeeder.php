<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PermissionsSeeder::class,
            SuperAdminSeeder::class,
            PlatformPlanSeeder::class,
            TenantSeeder::class,
            TenantSubscriptionSeeder::class,
            GymSettingsSeeder::class,
            EmployeeSeeder::class,
            TrainerSeeder::class,
            MemberSeeder::class,
            ClassSeeder::class,
            InventorySeeder::class,
            ExpenseSeeder::class,
        ]);
    }
}


<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Fresh demo dataset: 10 gyms, each with staff, trainers, members,
     * memberships, invoices/payments, classes, attendance, and expenses.
     *
     * Run: php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        $this->call([
            PlatformPlanSeeder::class,
            TenantSubscriptionSeeder::class,
            PlatformSuperAdminSeeder::class,
            DemoGymDataSeeder::class,
        ]);
    }
}

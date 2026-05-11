<?php

namespace Database\Seeders;

use App\Models\PlatformPlan;
use Illuminate\Database\Seeder;

class PlatformPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Monthly Plan',
                'description' => 'Monthly access with unlimited classes and full gym access.',
                'price' => 49.99,
                'duration' => 1,
                'features' => ['Access to all equipment', 'Unlimited group classes', 'Free fitness assessment'],
                'status' => 'active',
            ],
            [
                'name' => 'Quarterly Plan',
                'description' => 'Three months of gym membership with priority booking.',
                'price' => 129.99,
                'duration' => 3,
                'features' => ['Access to all equipment', 'Unlimited group classes', 'Quarterly personal training session'],
                'status' => 'active',
            ],
            [
                'name' => 'Yearly Plan',
                'description' => 'Annual membership with premium benefits and discounted rates.',
                'price' => 449.99,
                'duration' => 12,
                'features' => ['All gym access', 'Unlimited classes', 'Monthly trainer consultation', 'Discounted store purchases'],
                'status' => 'active',
            ],
        ];

        foreach ($plans as $plan) {
            PlatformPlan::updateOrCreate(
                ['name' => $plan['name']],
                $plan
            );
        }
    }
}

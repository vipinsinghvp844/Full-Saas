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
                'plan_type' => 'monthly',
                'discount' => 0,
                'max_members' => 100,
                'max_trainers' => 5,
                'max_branches' => 1,
                'features' => ['Access to all equipment', 'Unlimited group classes', 'Free fitness assessment'],
                'status' => 'active',
            ],
            [
                'name' => 'Quarterly Plan',
                'description' => 'Three months of gym membership with priority booking.',
                'price' => 149.99,
                'duration' => 3,
                'plan_type' => 'quarterly',
                'discount' => 10,
                'max_members' => 300,
                'max_trainers' => 10,
                'max_branches' => 2,
                'features' => ['Access to all equipment', 'Unlimited group classes', 'Quarterly personal training session', 'Priority booking'],
                'status' => 'active',
            ],
            [
                'name' => 'Yearly Plan',
                'description' => 'Annual membership with premium benefits and discounted rates.',
                'price' => 599.99,
                'duration' => 12,
                'plan_type' => 'yearly',
                'discount' => 20,
                // Unlimited representation using NULL as per enterprise standards
                'max_members' => null,
                'max_trainers' => null,
                'max_branches' => null,
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

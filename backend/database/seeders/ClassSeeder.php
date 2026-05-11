<?php

namespace Database\Seeders;

use App\Models\GymClass;
use App\Models\Tenant;
use App\Models\Trainer;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
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

        $trainerIds = Trainer::where('tenant_id', $tenant->id)
            ->pluck('id')
            ->toArray();

        if (empty($trainerIds)) {
            return;
        }

        $classes = [
            [
                'name' => 'Power Yoga',
                'description' => 'A balanced yoga class for strength and flexibility.',
                'category' => 'Mind & Body',
                'max_participants' => 20,
                'duration_minutes' => 60,
            ],
            [
                'name' => 'HIIT Blast',
                'description' => 'High intensity interval training for maximum calorie burn.',
                'category' => 'Cardio',
                'max_participants' => 18,
                'duration_minutes' => 45,
            ],
            [
                'name' => 'Strength Circuit',
                'description' => 'Full body resistance training using machines and free weights.',
                'category' => 'Strength',
                'max_participants' => 16,
                'duration_minutes' => 50,
            ],
            [
                'name' => 'Core & Stability',
                'description' => 'Focused class on core strength and functional movement.',
                'category' => 'Wellness',
                'max_participants' => 15,
                'duration_minutes' => 45,
            ],
        ];

        foreach ($classes as $index => $classData) {
            GymClass::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $classData['name'],
                ],
                array_merge($classData, [
                    'trainer_id' => $trainerIds[$index % count($trainerIds)],
                ])
            );
        }
    }
}

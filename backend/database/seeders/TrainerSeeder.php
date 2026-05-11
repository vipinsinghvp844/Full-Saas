<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Seeder;

class TrainerSeeder extends Seeder
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

        $trainers = [
            [
                'name' => 'Alex Strong',
                'email' => 'alex.strong@powerhousegym.com',
                'specialization' => 'Strength Training',
                'experience_years' => 5,
                'certifications' => 'Certified Strength and Conditioning Specialist',
            ],
            [
                'name' => 'Sophie Fit',
                'email' => 'sophie.fit@powerhousegym.com',
                'specialization' => 'Yoga & Pilates',
                'experience_years' => 4,
                'certifications' => 'Registered Yoga Teacher',
            ],
            [
                'name' => 'Noah Flex',
                'email' => 'noah.flex@powerhousegym.com',
                'specialization' => 'Functional Training',
                'experience_years' => 6,
                'certifications' => 'Functional Movement Systems Certified',
            ],
        ];

        foreach ($trainers as $trainerData) {
            $user = User::updateOrCreate(
                ['email' => $trainerData['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $trainerData['name'],
                    'password' => 'password',
                ]
            );

            Trainer::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                [
                    'specialization' => $trainerData['specialization'],
                    'experience_years' => $trainerData['experience_years'],
                    'certifications' => $trainerData['certifications'],
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            $role = Role::where('tenant_id', $tenant->id)
                ->where('name', 'Trainer')
                ->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['tenant_id' => $tenant->id],
                ]);
            }
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TrainerSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            return;
        }

        $branch = Branch::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'name' => 'Main Branch',
            ],
            [
                'address' => '123 Fitness Avenue, City Center',
                'phone' => '9876543210',
            ]
        );

        $trainerRole = Role::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'name' => 'Trainer',
                'guard_name' => 'web',
            ],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Trainer',
                'guard_name' => 'web',
            ]
        );

        Role::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'name' => 'trainer',
                'guard_name' => 'web',
            ],
            [
                'tenant_id' => $tenant->id,
                'name' => 'trainer',
                'guard_name' => 'web',
            ]
        );

        $trainers = [
            [
                'name' => 'John Trainer',
                'email' => 'john.trainer@example.com',
                'specialization' => 'Strength Training',
                'experience' => 5,
                'certifications' => 'ACE Certified',
                'bio' => 'Certified strength coach with 5 years of experience.',
                'phone' => '9812345670',
            ],
            [
                'name' => 'Alex Fitness Coach',
                'email' => 'alex.coach@example.com',
                'specialization' => 'Yoga & Pilates',
                'experience' => 4,
                'certifications' => 'RYT-200',
                'bio' => 'Helping members achieve flexibility and balance.',
                'phone' => '9812345671',
            ],
        ];

        foreach ($trainers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                ]
            );

            $employee = Employee::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                [
                    'branch_id' => $branch->id,
                    'role' => 'trainer',
                    'position' => 'Trainer',
                    'hire_date' => now()->subMonths(10)->toDateString(),
                    'salary' => 45000,
                    'shift' => '9 AM - 6 PM',
                    'status' => 'active',
                    'phone' => $data['phone'],
                ]
            );

            $user->roles()->syncWithoutDetaching([
                $trainerRole->id => ['tenant_id' => $tenant->id],
            ]);

            Trainer::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                [
                    'employee_id' => $employee->id,
                    'specialization' => $data['specialization'],
                    'experience_years' => $data['experience'],
                    'certifications' => $data['certifications'],
                    'bio' => $data['bio'],
                    'phone' => $data['phone'],
                    'salary' => 45000,
                    'shift' => '9 AM - 6 PM',
                    'status' => 'active',
                ]
            );
        }
    }
}

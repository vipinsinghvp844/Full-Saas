<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
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

        $members = [
            [
                'name' => 'Liam Brooks',
                'email' => 'liam.brooks@powerhousegym.com',
                'date_of_birth' => now()->subYears(30)->toDateString(),
                'gender' => 'male',
                'emergency_contact' => 'Jane Brooks - 9876543211',
            ],
            [
                'name' => 'Emma Carter',
                'email' => 'emma.carter@powerhousegym.com',
                'date_of_birth' => now()->subYears(28)->toDateString(),
                'gender' => 'female',
                'emergency_contact' => 'Sarah Carter - 9876543212',
            ],
            [
                'name' => 'Ethan Hayes',
                'email' => 'ethan.hayes@powerhousegym.com',
                'date_of_birth' => now()->subYears(25)->toDateString(),
                'gender' => 'male',
                'emergency_contact' => 'Mia Hayes - 9876543213',
            ],
            [
                'name' => 'Ava Walker',
                'email' => 'ava.walker@powerhousegym.com',
                'date_of_birth' => now()->subYears(32)->toDateString(),
                'gender' => 'female',
                'emergency_contact' => 'Olivia Walker - 9876543214',
            ],
            [
                'name' => 'Noah Reed',
                'email' => 'noah.reed@powerhousegym.com',
                'date_of_birth' => now()->subYears(27)->toDateString(),
                'gender' => 'male',
                'emergency_contact' => 'Grace Reed - 9876543215',
            ],
        ];

        foreach ($members as $memberData) {
            $user = User::updateOrCreate(
                ['email' => $memberData['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $memberData['name'],
                    'password' => 'password',
                ]
            );

            Member::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                [
                    'date_of_birth' => $memberData['date_of_birth'],
                    'gender' => $memberData['gender'],
                    'emergency_contact' => $memberData['emergency_contact'],
                    'membership_status' => 'active',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            $role = Role::where('tenant_id', $tenant->id)
                ->where('name', 'Receptionist')
                ->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['tenant_id' => $tenant->id],
                ]);
            }
        }
    }
}

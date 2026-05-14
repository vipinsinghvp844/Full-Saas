<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $plans = MembershipPlan::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();

        if ($plans->isEmpty()) {
            $plans = collect([
                [
                    'name' => 'Basic Monthly',
                    'price' => 1500.00,
                    'duration_days' => 30,
                    'features' => ['Gym floor access', 'Locker access'],
                ],
                [
                    'name' => 'Premium Monthly',
                    'price' => 2500.00,
                    'duration_days' => 30,
                    'features' => ['Gym floor access', 'Group classes', 'Trainer check-in'],
                ],
                [
                    'name' => 'Annual Elite',
                    'price' => 24000.00,
                    'duration_days' => 365,
                    'features' => ['Unlimited gym access', 'Group classes', 'Quarterly body assessment'],
                ],
            ])->map(function ($planData) use ($tenant) {
                return MembershipPlan::query()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $planData['name'],
                    'price' => $planData['price'],
                    'duration_days' => $planData['duration_days'],
                    'features' => $planData['features'],
                ]);
            });
        }

        $trainers = Trainer::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->get();

        $members = [
            [
                'name' => 'Liam Brooks',
                'email' => 'liam.brooks@powerhousegym.com',
                'date_of_birth' => now()->subYears(30)->toDateString(),
                'gender' => 'male',
                'phone' => '9876543001',
                'address' => '21 Orchard Street',
                'emergency_contact' => 'Jane Brooks - 9876543002',
                'joining_date' => now()->subMonths(7)->toDateString(),
                'status' => 'active',
            ],
            [
                'name' => 'Sophia Martinez',
                'email' => 'sophia.martinez@powerhousegym.com',
                'date_of_birth' => now()->subYears(28)->toDateString(),
                'gender' => 'female',
                'phone' => '9876543003',
                'address' => '44 Lakeside Road',
                'emergency_contact' => 'Carlos Martinez - 9876543004',
                'joining_date' => now()->subMonths(6)->toDateString(),
                'status' => 'active',
            ],
            [
                'name' => 'Ethan Hayes',
                'email' => 'ethan.hayes@powerhousegym.com',
                'date_of_birth' => now()->subYears(25)->toDateString(),
                'gender' => 'male',
                'phone' => '9876543005',
                'address' => '12 Cedar Avenue',
                'emergency_contact' => 'Mia Hayes - 9876543006',
                'joining_date' => now()->subMonths(5)->toDateString(),
                'status' => 'active',
            ],
            [
                'name' => 'Olivia Walker',
                'email' => 'olivia.walker@powerhousegym.com',
                'date_of_birth' => now()->subYears(32)->toDateString(),
                'gender' => 'female',
                'phone' => '9876543007',
                'address' => '89 Maple Court',
                'emergency_contact' => 'James Walker - 9876543008',
                'joining_date' => now()->subMonths(8)->toDateString(),
                'status' => 'active',
            ],
            [
                'name' => 'Noah Reed',
                'email' => 'noah.reed@powerhousegym.com',
                'date_of_birth' => now()->subYears(27)->toDateString(),
                'gender' => 'male',
                'phone' => '9876543009',
                'address' => '5 Granite Lane',
                'emergency_contact' => 'Grace Reed - 9876543010',
                'joining_date' => now()->subMonths(4)->toDateString(),
                'status' => 'active',
            ],
        ];

        foreach ($members as $index => $memberData) {
            $plan = $plans[$index % $plans->count()];
            $trainer = $trainers->isEmpty() ? null : $trainers[$index % $trainers->count()];
            $membershipStartDate = now()->subDays(10 + $index)->toDateString();

            $user = User::query()->updateOrCreate(
                ['email' => $memberData['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $memberData['name'],
                    'password' => Hash::make('password'),
                ]
            );

            $member = Member::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                [
                    'assigned_trainer_id' => $trainer?->id,
                    'phone' => $memberData['phone'],
                    'gender' => $memberData['gender'],
                    'date_of_birth' => $memberData['date_of_birth'],
                    'address' => $memberData['address'],
                    'emergency_contact' => $memberData['emergency_contact'],
                    'joining_date' => $memberData['joining_date'],
                    'status' => $memberData['status'],
                    'created_by' => null,
                    'updated_by' => null,
                ]
            );

            MemberMembership::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'member_id' => $member->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                ],
                [
                    'start_date' => $membershipStartDate,
                    'end_date' => now()->parse($membershipStartDate)->addDays((int) $plan->duration_days)->toDateString(),
                    'payment_status' => 'paid',
                    'final_amount' => $plan->price,
                ]
            );
        }
    }
}

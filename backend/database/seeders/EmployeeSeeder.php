<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
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

        $employees = [
            [
                'name' => 'Olivia Manager',
                'email' => 'olivia.manager@powerhousegym.com',
                'position' => 'Manager',
                'salary' => 45000.00,
                'role' => 'Manager',
            ],
            [
                'name' => 'Mia Receptionist',
                'email' => 'mia.receptionist@powerhousegym.com',
                'position' => 'Receptionist',
                'salary' => 32000.00,
                'role' => 'Receptionist',
            ],
            [
                'name' => 'Ethan Accountant',
                'email' => 'ethan.accountant@powerhousegym.com',
                'position' => 'Accountant',
                'salary' => 42000.00,
                'role' => 'Accountant',
            ],
        ];

        foreach ($employees as $employeeData) {
            $user = User::updateOrCreate(
                ['email' => $employeeData['email']],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $employeeData['name'],
                    'password' => 'password',
                ]
            );

            $employee = Employee::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                ],
                [
                    'branch_id' => $branch->id,
                    'position' => $employeeData['position'],
                    'hire_date' => now()->subMonths(3)->toDateString(),
                    'salary' => $employeeData['salary'],
                    'status' => 'active',
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            $role = Role::where('tenant_id', $tenant->id)
                ->where('name', $employeeData['role'])
                ->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([
                    $role->id => ['tenant_id' => $tenant->id],
                ]);
            }

            if ($employeeData['role'] === 'Manager') {
                $branch->manager_id = $employee->id;
                $branch->save();
            }
        }
    }
}

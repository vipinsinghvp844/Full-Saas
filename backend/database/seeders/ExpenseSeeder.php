<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
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

        $categories = [
            ['name' => 'Rent', 'description' => 'Monthly facility rental payment.'],
            ['name' => 'Utilities', 'description' => 'Electricity, water, and gas bills.'],
            ['name' => 'Maintenance', 'description' => 'Equipment and facility maintenance costs.'],
        ];

        $categoryIds = [];

        foreach ($categories as $categoryData) {
            $category = ExpenseCategory::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $categoryData['name'],
                ],
                $categoryData
            );

            $categoryIds[$categoryData['name']] = $category->id;
        }

        $expenses = [
            [
                'expense_category_name' => 'Rent',
                'amount' => 2500.00,
                'expense_date' => now()->subDays(10)->toDateString(),
                'description' => 'Monthly gym rental fee.',
            ],
            [
                'expense_category_name' => 'Utilities',
                'amount' => 650.00,
                'expense_date' => now()->subDays(7)->toDateString(),
                'description' => 'Utility bills for electricity and water.',
            ],
            [
                'expense_category_name' => 'Maintenance',
                'amount' => 420.00,
                'expense_date' => now()->subDays(5)->toDateString(),
                'description' => 'Equipment maintenance and repairs.',
            ],
        ];

        foreach ($expenses as $expenseData) {
            Expense::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'expense_category_id' => $categoryIds[$expenseData['expense_category_name']] ?? null,
                    'expense_date' => $expenseData['expense_date'],
                ],
                [
                    'amount' => $expenseData['amount'],
                    'description' => $expenseData['description'],
                ]
            );
        }
    }
}

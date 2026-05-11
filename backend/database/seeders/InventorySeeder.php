<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
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

        $products = [
            [
                'name' => 'Whey Protein',
                'category' => 'Supplements',
                'description' => 'Premium whey protein powder for recovery.',
                'price' => 39.99,
                'stock_quantity' => 150,
                'min_stock' => 10,
            ],
            [
                'name' => 'Yoga Mat',
                'category' => 'Equipment',
                'description' => 'Non-slip yoga mat for group classes.',
                'price' => 24.99,
                'stock_quantity' => 40,
                'min_stock' => 5,
            ],
            [
                'name' => 'Dumbbell Set',
                'category' => 'Equipment',
                'description' => 'Adjustable dumbbell set for strength training.',
                'price' => 120.00,
                'stock_quantity' => 20,
                'min_stock' => 2,
            ],
            [
                'name' => 'Protein Bar',
                'category' => 'Supplements',
                'description' => 'High-protein nutrition bar for on-the-go energy.',
                'price' => 2.50,
                'stock_quantity' => 200,
                'min_stock' => 20,
            ],
            [
                'name' => 'Resistance Bands',
                'category' => 'Equipment',
                'description' => 'Set of resistance bands for stretching and strength.',
                'price' => 19.99,
                'stock_quantity' => 60,
                'min_stock' => 5,
            ],
        ];

        foreach ($products as $productData) {
            Product::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $productData['name'],
                ],
                $productData
            );
        }
    }
}

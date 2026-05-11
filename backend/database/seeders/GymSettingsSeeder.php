<?php

namespace Database\Seeders;

use App\Models\GymSetting;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class GymSettingsSeeder extends Seeder
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

        $settings = [
            ['key' => 'currency', 'value' => 'USD'],
            ['key' => 'timezone', 'value' => 'America/New_York'],
            ['key' => 'contact_email', 'value' => 'support@powerhousegym.com'],
            ['key' => 'contact_phone', 'value' => '9876543210'],
        ];

        foreach ($settings as $setting) {
            GymSetting::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'key' => $setting['key'],
                ],
                [
                    'value' => $setting['value'],
                ]
            );
        }
    }
}

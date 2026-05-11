<?php

namespace App\Services\SuperAdmin;

use App\Models\Coupon;
use App\Models\PlatformPlan;
use App\Models\Tenant;

class PlatformSettingsService
{
    public function summary(): array
    {
        return [
            'platform' => [
                'name' => config('app.name'),
                'environment' => config('app.env'),
                'api_base_url' => config('app.url'),
                'auth_guard' => config('auth.defaults.guard'),
            ],
            'defaults' => [
                'active_plans' => PlatformPlan::where('status', 'active')->count(),
                'active_coupons' => Coupon::where('status', 'active')->count(),
                'supported_plan_types' => ['monthly', 'quarterly', 'yearly'],
                'registered_countries' => Tenant::query()->whereNotNull('country')->distinct('country')->count('country'),
            ],
        ];
    }
}

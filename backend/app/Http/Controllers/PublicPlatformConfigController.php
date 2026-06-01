<?php

namespace App\Http\Controllers;

use App\Services\SuperAdmin\PlatformSettingsService;
use Illuminate\Http\Request;

class PublicPlatformConfigController extends ApiController
{
    public function __construct(protected PlatformSettingsService $settingsService)
    {
    }

    /**
     * Returns only the safe, public-facing platform configuration.
     * No sensitive data (payment keys, SMTP passwords, etc.) is returned here.
     */
    public function index(Request $request)
    {
        $settings = $this->settingsService->getAllSettings();

        return $this->jsonResponse([
            'data' => [
                'features' => $settings['features'] ?? [
                    'enable_classes'    => true,
                    'enable_trainers'   => true,
                    'enable_store'      => true,
                    'enable_diet_plans' => true,
                ],
                'system' => [
                    'maintenance_mode' => (bool) ($settings['system']['maintenance_mode'] ?? false),
                ],
                'platform' => [
                    'name' => $settings['platform']['name'] ?? 'SaaS Platform',
                    'logo' => $settings['platform']['logo'] ?? '',
                ],
            ],
        ], 200, $request);
    }
}

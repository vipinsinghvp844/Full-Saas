<?php

namespace App\Http\Controllers;

use App\Services\SuperAdmin\PlatformSettingsService;
use Illuminate\Http\Request;

class PublicCmsController extends ApiController
{
    public function __construct(protected PlatformSettingsService $platformSettingsService)
    {
    }

    public function index(Request $request)
    {
        $settings = $this->platformSettingsService->getAllSettings();
        
        $cms = $settings['cms'] ?? [];
        $platform = $settings['platform'] ?? [];

        return $this->jsonResponse([
            'cms' => $cms,
            'platform' => [
                'name' => $platform['name'] ?? 'Gym SaaS',
                'logo' => $platform['logo'] ?? '',
                'support_email' => $platform['support_email'] ?? '',
            ]
        ]);
    }
}

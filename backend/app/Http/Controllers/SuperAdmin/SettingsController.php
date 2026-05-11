<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Http\Resources\SuperAdmin\SettingsSummaryResource;
use App\Services\SuperAdmin\PlatformSettingsService;
use Illuminate\Http\Request;

class SettingsController extends ApiController
{
    public function __construct(protected PlatformSettingsService $platformSettingsService)
    {
    }

    public function __invoke(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->resourceData(new SettingsSummaryResource($this->platformSettingsService->summary())),
        ], 200, $request);
    }
}

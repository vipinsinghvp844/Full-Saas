<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Http\Resources\SuperAdmin\DashboardResource;
use App\Services\SuperAdmin\DashboardService;
use Illuminate\Http\Request;

class SuperAdminDashboardController extends ApiController
{
    public function __construct(protected DashboardService $dashboardService)
    {
    }

    public function __invoke(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->resourceData(new DashboardResource($this->dashboardService->summary())),
        ], 200, $request);
    }
}

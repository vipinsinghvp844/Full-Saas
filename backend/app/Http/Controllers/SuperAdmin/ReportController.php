<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Http\Resources\SuperAdmin\ReportSectionResource;
use App\Services\SuperAdmin\ReportService;
use Illuminate\Http\Request;

class ReportController extends ApiController
{
    public function __construct(protected ReportService $reportService)
    {
    }

    public function overview(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->resourceData(new ReportSectionResource($this->reportService->overview())),
        ], 200, $request);
    }

    public function revenue(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->resourceData(new ReportSectionResource($this->reportService->revenueReport())),
        ], 200, $request);
    }

    public function gymGrowth(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->resourceData(new ReportSectionResource($this->reportService->gymGrowthReport())),
        ], 200, $request);
    }

    public function subscriptions(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->resourceData(new ReportSectionResource($this->reportService->subscriptionReport())),
        ], 200, $request);
    }
}

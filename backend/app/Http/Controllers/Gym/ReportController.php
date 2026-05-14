<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Services\Gym\ReportService;
use Illuminate\Http\Request;

class ReportController extends ApiController
{
    public function __construct(protected ReportService $reportService)
    {
    }

    public function overview(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->reportService->getOverview(
            $tenantId, 
            $request->query('start_date'), 
            $request->query('end_date')
        );

        return $this->jsonResponse(['data' => $data], 200, $request);
    }

    public function revenue(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->reportService->getRevenueReport(
            $tenantId, 
            $request->query('start_date'), 
            $request->query('end_date')
        );

        return $this->jsonResponse(['data' => $data], 200, $request);
    }

    public function memberships(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->reportService->getMembershipReport(
            $tenantId, 
            $request->query('start_date'), 
            $request->query('end_date')
        );

        return $this->jsonResponse(['data' => $data], 200, $request);
    }

    public function attendance(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->reportService->getAttendanceReport(
            $tenantId, 
            $request->query('start_date'), 
            $request->query('end_date')
        );

        return $this->jsonResponse(['data' => $data], 200, $request);
    }

    public function trainers(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $data = $this->reportService->getTrainerPerformance($tenantId);

        return $this->jsonResponse(['data' => $data], 200, $request);
    }
}

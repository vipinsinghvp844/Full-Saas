<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Services\SuperAdmin\ReportService;
use Illuminate\Http\Request;

class ReportController extends ApiController
{
    public function __construct(protected ReportService $reportService)
    {
    }

    protected function parseFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
            'plan_id'   => ['nullable', 'integer'],
            'status'    => ['nullable', 'string'],
        ]);
    }

    public function overview(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->reportService->overview($this->parseFilters($request)),
        ], 200, $request);
    }

    public function revenue(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->reportService->revenueReport($this->parseFilters($request)),
        ], 200, $request);
    }

    public function gymGrowth(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->reportService->gymGrowthReport($this->parseFilters($request)),
        ], 200, $request);
    }

    public function subscriptions(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->reportService->subscriptionReport($this->parseFilters($request)),
        ], 200, $request);
    }

    public function coupons(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->reportService->couponReport($this->parseFilters($request)),
        ], 200, $request);
    }

    public function payments(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->reportService->paymentReport($this->parseFilters($request)),
        ], 200, $request);
    }

    public function growth(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->reportService->growthReport($this->parseFilters($request)),
        ], 200, $request);
    }
}

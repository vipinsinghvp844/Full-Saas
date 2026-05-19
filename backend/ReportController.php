<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminReportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private SuperAdminReportService $reportService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'plan_id'    => 'nullable|integer|exists:plans,id',
            'status'     => 'nullable|string|in:active,inactive,trialing,cancelled',
        ]);

        $metrics = $this->reportService->getDashboardMetrics($filters);

        return response()->json(['data' => $metrics]);
    }
}
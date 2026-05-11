<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Http\Requests\SuperAdmin\StorePlanRequest;
use App\Http\Requests\SuperAdmin\UpdatePlanRequest;
use App\Http\Resources\SuperAdmin\PlanResource;
use App\Models\PlatformPlan;
use App\Repositories\SuperAdmin\PlanRepository;
use App\Services\SuperAdmin\PlanService;
use Illuminate\Http\Request;

class PlatformPlanController extends ApiController
{
    public function __construct(
        protected PlanRepository $planRepository,
        protected PlanService $planService
    ) {
    }

    public function index(Request $request)
    {
        $payload = $this->paginatedData($this->planRepository->paginate($request->all()), PlanResource::class);
        $payload['filters'] = [
            'plan_types' => ['monthly', 'quarterly', 'yearly'],
            'statuses' => ['active', 'inactive'],
        ];

        return $this->jsonResponse($payload, 200, $request);
    }

    public function store(StorePlanRequest $request)
    {
        $plan = $this->planService->create($request->validated(), $request->user());

        return $this->jsonResponse([
            'message' => 'Plan created successfully.',
            'data' => $this->resourceData(new PlanResource($plan)),
        ], 201, $request);
    }

    public function show(Request $request, PlatformPlan $platformPlan)
    {
        $platformPlan->loadCount('subscriptions');

        return $this->jsonResponse([
            'data' => $this->resourceData(new PlanResource($platformPlan)),
        ], 200, $request);
    }

    public function update(UpdatePlanRequest $request, PlatformPlan $platformPlan)
    {
        $updated = $this->planService->update($platformPlan, $request->validated(), $request->user());
        $updated->loadCount('subscriptions');

        return $this->jsonResponse([
            'message' => 'Plan updated successfully.',
            'data' => $this->resourceData(new PlanResource($updated)),
        ], 200, $request);
    }

    public function destroy(Request $request, PlatformPlan $platformPlan)
    {
        $this->planService->delete($platformPlan, $request->user());

        return $this->jsonResponse([
            'message' => 'Plan deleted successfully.',
        ], 200, $request);
    }

    public function activate(Request $request, PlatformPlan $platformPlan)
    {
        $updated = $this->planService->setStatus($platformPlan, 'active', $request->user());
        $updated->loadCount('subscriptions');

        return $this->jsonResponse([
            'message' => 'Plan activated successfully.',
            'data' => $this->resourceData(new PlanResource($updated)),
        ], 200, $request);
    }

    public function deactivate(Request $request, PlatformPlan $platformPlan)
    {
        $updated = $this->planService->setStatus($platformPlan, 'inactive', $request->user());
        $updated->loadCount('subscriptions');

        return $this->jsonResponse([
            'message' => 'Plan deactivated successfully.',
            'data' => $this->resourceData(new PlanResource($updated)),
        ], 200, $request);
    }
}

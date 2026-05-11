<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Http\Requests\SuperAdmin\CancelSubscriptionRequest;
use App\Http\Requests\SuperAdmin\ChangeSubscriptionPlanRequest;
use App\Http\Requests\SuperAdmin\RenewSubscriptionRequest;
use App\Http\Requests\SuperAdmin\StoreSubscriptionRequest;
use App\Http\Resources\SuperAdmin\PlanResource;
use App\Http\Resources\SuperAdmin\SubscriptionResource;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Repositories\SuperAdmin\SubscriptionRepository;
use App\Services\SuperAdmin\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends ApiController
{
    public function __construct(
        protected SubscriptionRepository $subscriptionRepository,
        protected SubscriptionService $subscriptionService
    ) {
    }

    public function index(Request $request)
    {
        $payload = $this->paginatedData($this->subscriptionRepository->paginate($request->all()), SubscriptionResource::class);
        $payload['filters'] = [
            'statuses' => ['active', 'expired', 'cancelled'],
            'plans' => PlanResource::collection(PlatformPlan::query()->orderBy('name')->get())->resolve(),
            'gyms' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ];

        return $this->jsonResponse($payload, 200, $request);
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $subscription = $this->subscriptionService->assignPlan($request->validated(), $request->user());
        $subscription->load(['tenant.owner', 'plan', 'coupon']);

        return $this->jsonResponse([
            'message' => 'Subscription assigned successfully.',
            'data' => $this->resourceData(new SubscriptionResource($subscription)),
        ], 201, $request);
    }

    public function show(Request $request, TenantSubscription $tenantSubscription)
    {
        $tenantSubscription->load(['tenant.owner', 'plan', 'coupon']);

        return $this->jsonResponse([
            'data' => $this->resourceData(new SubscriptionResource($tenantSubscription)),
        ], 200, $request);
    }

    public function renew(RenewSubscriptionRequest $request, TenantSubscription $tenantSubscription)
    {
        $updated = $this->subscriptionService->renew($tenantSubscription, $request->validated(), $request->user());
        $updated->load(['tenant.owner', 'plan', 'coupon']);

        return $this->jsonResponse([
            'message' => 'Subscription renewed successfully.',
            'data' => $this->resourceData(new SubscriptionResource($updated)),
        ], 200, $request);
    }

    public function cancel(CancelSubscriptionRequest $request, TenantSubscription $tenantSubscription)
    {
        $updated = $this->subscriptionService->cancel($tenantSubscription, $request->validated(), $request->user());
        $updated->load(['tenant.owner', 'plan', 'coupon']);

        return $this->jsonResponse([
            'message' => 'Subscription cancelled successfully.',
            'data' => $this->resourceData(new SubscriptionResource($updated)),
        ], 200, $request);
    }

    public function changePlan(ChangeSubscriptionPlanRequest $request, TenantSubscription $tenantSubscription)
    {
        $updated = $this->subscriptionService->changePlan($tenantSubscription, $request->validated(), $request->user());
        $updated->load(['tenant.owner', 'plan', 'coupon']);

        return $this->jsonResponse([
            'message' => 'Subscription updated successfully.',
            'data' => $this->resourceData(new SubscriptionResource($updated)),
        ], 200, $request);
    }
}

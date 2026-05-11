<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Http\Requests\SuperAdmin\StoreGymRequest;
use App\Http\Requests\SuperAdmin\UpdateGymRequest;
use App\Http\Resources\SuperAdmin\GymResource;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Repositories\SuperAdmin\GymRepository;
use App\Services\SuperAdmin\GymService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class GymController extends ApiController
{
    public function __construct(
        protected GymRepository $gymRepository,
        protected GymService $gymService
    ) {
    }

    public function index(Request $request)
    {
        $paginator = $this->gymRepository->paginate($request->all());
        $payload = $this->paginatedData($paginator, GymResource::class);
        $payload['filters'] = [
            'plans' => PlatformPlan::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'countries' => Schema::hasColumn('tenants', 'country')
                ? Tenant::query()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country')
                : collect(),
        ];

        return $this->jsonResponse($payload, 200, $request);
    }

    public function store(StoreGymRequest $request)
    {
        [$tenant, $temporaryPassword] = $this->gymService->create([
            ...$request->validated(),
            'logo' => $request->file('logo'),
        ], $request->user());

        return $this->jsonResponse([
            'message' => 'Gym created successfully.',
            'data' => $this->resourceData(new GymResource($this->gymRepository->findForShow($tenant))),
            'meta' => [
                'temporary_password' => $temporaryPassword,
            ],
        ], 201, $request);
    }

    public function show(Request $request, Tenant $tenant)
    {
        return $this->jsonResponse([
            'data' => $this->resourceData(new GymResource($this->gymRepository->findForShow($tenant))),
        ], 200, $request);
    }

    public function update(UpdateGymRequest $request, Tenant $tenant)
    {
        $updated = $this->gymService->update($tenant, [
            ...$request->validated(),
            'logo' => $request->file('logo'),
        ], $request->user());

        return $this->jsonResponse([
            'message' => 'Gym updated successfully.',
            'data' => $this->resourceData(new GymResource($this->gymRepository->findForShow($updated))),
        ], 200, $request);
    }

    public function destroy(Request $request, Tenant $tenant)
    {
        $this->gymService->delete($tenant, $request->user());

        return $this->jsonResponse([
            'message' => 'Gym deleted successfully.',
        ], 200, $request);
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $updated = $this->gymService->suspend($tenant, $request->user());

        return $this->jsonResponse([
            'message' => 'Gym suspended successfully.',
            'data' => $this->resourceData(new GymResource($updated->load(['owner', 'activeSubscription.plan'])->loadCount(['members', 'trainers']))),
        ], 200, $request);
    }

    public function activate(Request $request, Tenant $tenant)
    {
        $updated = $this->gymService->activate($tenant, $request->user());

        return $this->jsonResponse([
            'message' => 'Gym activated successfully.',
            'data' => $this->resourceData(new GymResource($updated->load(['owner', 'activeSubscription.plan'])->loadCount(['members', 'trainers']))),
        ], 200, $request);
    }
}

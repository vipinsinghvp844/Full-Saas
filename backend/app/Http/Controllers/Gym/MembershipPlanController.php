<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Http\Resources\Gym\MembershipPlanResource;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MembershipPlanController extends ApiController
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $paginator = MembershipPlan::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->paginate(15);

        return $this->paginatedData($paginator, MembershipPlanResource::class);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(['errors' => $validator->errors()], 422, $request);
        }

        $validated = $validator->validated();
        $validated['tenant_id'] = $tenantId;

        $plan = MembershipPlan::create($validated);

        return $this->jsonResponse([
            'message' => 'Membership plan created successfully',
            'data' => new MembershipPlanResource($plan)
        ], 201, $request);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;

        $plan = MembershipPlan::where('tenant_id', $tenantId)->find($id);

        if (!$plan) {
            return $this->jsonResponse(['message' => 'Membership plan not found'], 404, $request);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'duration_days' => 'sometimes|required|integer|min:1',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->jsonResponse(['errors' => $validator->errors()], 422, $request);
        }

        $plan->update($validator->validated());

        return $this->jsonResponse([
            'message' => 'Membership plan updated successfully',
            'data' => new MembershipPlanResource($plan)
        ], 200, $request);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;

        $plan = MembershipPlan::where('tenant_id', $tenantId)->find($id);

        if (!$plan) {
            return $this->jsonResponse(['message' => 'Membership plan not found'], 404, $request);
        }

        $plan->delete();

        return $this->jsonResponse([
            'message' => 'Membership plan deleted successfully'
        ], 200, $request);
    }
}

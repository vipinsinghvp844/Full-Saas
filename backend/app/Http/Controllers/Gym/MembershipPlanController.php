<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Http\Resources\Gym\MembershipPlanResource;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;

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
}

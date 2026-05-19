<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Http\Requests\SuperAdmin\StoreCouponRequest;
use App\Http\Requests\SuperAdmin\UpdateCouponRequest;
use App\Http\Resources\SuperAdmin\CouponResource;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Repositories\SuperAdmin\CouponRepository;
use App\Services\SuperAdmin\CouponService;
use Illuminate\Http\Request;

class CouponController extends ApiController
{
    public function __construct(
        protected CouponRepository $couponRepository,
        protected CouponService $couponService
    ) {
    }

    public function index(Request $request)
    {
        $payload = $this->paginatedData($this->couponRepository->paginate($request->all()), CouponResource::class);
        $payload['filters'] = [
            'gyms' => Tenant::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => ['active', 'inactive'],
            'discount_types' => ['percentage', 'fixed'],
        ];

        return $this->jsonResponse($payload, 200, $request);
    }

    public function store(StoreCouponRequest $request)
    {
        $coupon = $this->couponService->create($request->validated(), $request->user());
        $coupon->load('tenant:id,name');

        return $this->jsonResponse([
            'message' => 'Coupon created successfully.',
            'data' => $this->resourceData(new CouponResource($coupon)),
        ], 201, $request);
    }

    public function show(Request $request, Coupon $coupon)
    {
        $coupon->load('tenant:id,name');

        return $this->jsonResponse([
            'data' => $this->resourceData(new CouponResource($coupon)),
        ], 200, $request);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon)
    {
        $updated = $this->couponService->update($coupon, $request->validated(), $request->user());
        $updated->load('tenant:id,name');

        return $this->jsonResponse([
            'message' => 'Coupon updated successfully.',
            'data' => $this->resourceData(new CouponResource($updated)),
        ], 200, $request);
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        $this->couponService->delete($coupon, $request->user());

        return $this->jsonResponse([
            'message' => 'Coupon deleted successfully.',
        ], 200, $request);
    }

    public function activate(Request $request, Coupon $coupon)
    {
        $updated = $this->couponService->activate($coupon, $request->user());

        return $this->jsonResponse([
            'message' => 'Coupon activated successfully.',
            'data' => $this->resourceData(new CouponResource($updated)),
        ], 200, $request);
    }

    public function deactivate(Request $request, Coupon $coupon)
    {
        $updated = $this->couponService->deactivate($coupon, $request->user());

        return $this->jsonResponse([
            'message' => 'Coupon deactivated successfully.',
            'data' => $this->resourceData(new CouponResource($updated)),
        ], 200, $request);
    }
}

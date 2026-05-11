<?php

namespace App\Services\SuperAdmin;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function __construct(protected ActivityLogService $activityLogService)
    {
    }

    public function create(array $data, User $actor): Coupon
    {
        return DB::transaction(function () use ($data, $actor) {
            $coupon = Coupon::create([
                'tenant_id' => $data['tenant_id'],
                'code' => strtoupper($data['code']),
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'valid_from' => $data['valid_from'] ?? now()->toDateString(),
                'valid_to' => $data['valid_to'],
                'usage_limit' => $data['usage_limit'] ?? null,
                'used_count' => 0,
                'status' => $data['status'] ?? 'active',
            ]);

            $this->activityLogService->record(
                $actor,
                'coupon.created',
                $coupon,
                "Created coupon {$coupon->code}",
                [],
                $coupon->toArray(),
                $coupon->tenant_id
            );

            return $coupon;
        });
    }

    public function update(Coupon $coupon, array $data, User $actor): Coupon
    {
        return DB::transaction(function () use ($coupon, $data, $actor) {
            $oldValues = $coupon->toArray();

            $coupon->update([
                'tenant_id' => $data['tenant_id'],
                'code' => strtoupper($data['code']),
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'valid_from' => $data['valid_from'] ?? $coupon->valid_from?->toDateString() ?? now()->toDateString(),
                'valid_to' => $data['valid_to'],
                'usage_limit' => $data['usage_limit'] ?? null,
                'status' => $data['status'] ?? $coupon->status,
            ]);

            $this->activityLogService->record(
                $actor,
                'coupon.updated',
                $coupon,
                "Updated coupon {$coupon->code}",
                $oldValues,
                $coupon->fresh()->toArray(),
                $coupon->tenant_id
            );

            return $coupon->fresh();
        });
    }

    public function delete(Coupon $coupon, User $actor): void
    {
        $snapshot = $coupon->toArray();
        $code = $coupon->code;
        $tenantId = $coupon->tenant_id;
        $coupon->delete();

        $this->activityLogService->record(
            $actor,
            'coupon.deleted',
            null,
            "Deleted coupon {$code}",
            $snapshot,
            [],
            $tenantId
        );
    }
}

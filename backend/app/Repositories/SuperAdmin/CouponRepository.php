<?php

namespace App\Repositories\SuperAdmin;

use App\Models\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CouponRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sortMap = [
            'code' => 'code',
            'valid_to' => 'valid_to',
            'created_at' => 'created_at',
            'used_count' => 'used_count',
        ];

        $sortBy = $sortMap[$filters['sort_by'] ?? 'created_at'] ?? 'created_at';
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 5), 50);
        $search = trim((string) ($filters['search'] ?? ''));

        return Coupon::query()
            ->with('tenant:id,name,status')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('code', 'like', "%{$search}%")
                        ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(! empty($filters['tenant_id']), fn ($query) => $query->where('tenant_id', $filters['tenant_id']))
            ->when(! empty($filters['discount_type']), fn ($query) => $query->where('discount_type', $filters['discount_type']))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }
}

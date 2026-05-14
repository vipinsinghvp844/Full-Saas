<?php

namespace App\Repositories\SuperAdmin;

use App\Models\TenantSubscription;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SubscriptionRepository
{
    public function syncExpiredStatuses(): void
    {
        TenantSubscription::query()
            ->whereIn('status', ['active', 'trial', 'paused'])
            ->whereDate('end_date', '<', Carbon::today()->toDateString())
            ->update(['status' => 'expired']);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $this->syncExpiredStatuses();

        $sortMap = [
            'start_date' => 'start_date',
            'end_date' => 'end_date',
            'created_at' => 'created_at',
            'final_amount' => 'final_amount',
        ];

        $sortBy = $sortMap[$filters['sort_by'] ?? 'created_at'] ?? 'created_at';
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 5), 50);
        $search = trim((string) ($filters['search'] ?? ''));

        return TenantSubscription::query()
            ->with(['tenant.owner:id,name,email', 'plan:id,name,plan_type,price', 'coupon:id,code,discount_type,discount_value'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->whereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('tenant.owner', fn ($ownerQuery) => $ownerQuery->where('email', 'like', "%{$search}%"))
                        ->orWhereHas('plan', fn ($planQuery) => $planQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['tenant_id']), fn ($query) => $query->where('tenant_id', $filters['tenant_id']))
            ->when(! empty($filters['plan_id']), fn ($query) => $query->where('plan_id', $filters['plan_id']))
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeForTenant(int $tenantId): ?TenantSubscription
    {
        $this->syncExpiredStatuses();

        return TenantSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->latest('end_date')
            ->first();
    }

    public function expiringSoon(int $days = 30): Collection
    {
        $this->syncExpiredStatuses();

        return TenantSubscription::query()
            ->with(['tenant:id,name', 'plan:id,name'])
            ->whereIn('status', ['active', 'trial', 'paused'])
            ->whereBetween('end_date', [Carbon::today()->toDateString(), Carbon::today()->addDays($days)->toDateString()])
            ->orderBy('end_date')
            ->get();
    }
}

<?php

namespace App\Repositories\SuperAdmin;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GymRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sortMap = [
            'name' => 'name',
            'status' => 'status',
            'created_at' => 'created_at',
            'members' => 'members_count',
            'trainers' => 'trainers_count',
        ];

        $sortBy = $sortMap[$filters['sort_by'] ?? 'created_at'] ?? 'created_at';
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 5), 50);
        $search = trim((string) ($filters['search'] ?? ''));

        return Tenant::query()
            ->with(['owner:id,name,email', 'activeSubscription.plan:id,name,plan_type'])
            ->withCount(['members', 'trainers', 'subscriptions'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhere('gst_number', 'like', "%{$search}%")
                        ->orWhereHas('owner', function ($ownerQuery) use ($search) {
                            $ownerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['country']), fn ($query) => $query->where('country', $filters['country']))
            ->when(! empty($filters['plan_id']), function ($query) use ($filters) {
                $query->whereHas('activeSubscription', fn ($subscriptionQuery) => $subscriptionQuery->where('plan_id', $filters['plan_id']));
            })
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForShow(Tenant $tenant): Tenant
    {
        return $tenant->load([
            'owner:id,name,email,tenant_id,created_at',
            'activeSubscription.plan:id,name,plan_type,price,duration,status',
            'subscriptions.plan:id,name,plan_type,price,duration,status',
            'invoices.payments',
        ])->loadCount(['members', 'trainers', 'branches', 'subscriptions']);
    }
}

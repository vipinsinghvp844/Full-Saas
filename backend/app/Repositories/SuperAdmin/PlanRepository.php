<?php

namespace App\Repositories\SuperAdmin;

use App\Models\PlatformPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PlanRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sortMap = [
            'name' => 'name',
            'price' => 'price',
            'duration' => 'duration',
            'created_at' => 'created_at',
            'subscriptions' => 'subscriptions_count',
        ];

        $sortBy = $sortMap[$filters['sort_by'] ?? 'created_at'] ?? 'created_at';
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 5), 50);
        $search = trim((string) ($filters['search'] ?? ''));

        return PlatformPlan::query()
            ->withCount('subscriptions')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('plan_type', 'like', "%{$search}%");
                });
            })
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeOptions(): Collection
    {
        return PlatformPlan::query()
            ->where('status', 'active')
            ->orderBy('price')
            ->get();
    }
}

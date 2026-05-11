<?php

namespace App\Repositories\SuperAdmin;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sortMap = [
            'created_at' => 'created_at',
            'amount' => 'amount',
            'status' => 'status',
        ];

        $sortBy = $sortMap[$filters['sort_by'] ?? 'created_at'] ?? 'created_at';
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 5), 50);
        $search = trim((string) ($filters['search'] ?? ''));

        return Payment::query()
            ->with(['invoice.tenant:id,name,status', 'invoice.subscription.plan:id,name,plan_type'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('transaction_id', 'like', "%{$search}%")
                        ->orWhere('payment_method', 'like', "%{$search}%")
                        ->orWhereHas('invoice.tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }
}

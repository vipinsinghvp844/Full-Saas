<?php

namespace App\Repositories\SuperAdmin;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PaymentRepository
{
    public function platformPaymentQuery(): Builder
    {
        return Payment::query()
            ->whereNull('payments.member_id')
            ->whereNull('payments.membership_id')
            ->whereHas('invoice', function ($query) {
                $query->whereNotNull('subscription_id')
                    ->whereNull('member_id')
                    ->whereNull('membership_id');
            });
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sortMap = [
            'created_at' => 'payments.created_at',
            'amount' => 'payments.amount',
            'status' => 'payments.payment_status',
            'payment_method' => 'payments.payment_method',
        ];

        $sortBy = $sortMap[$filters['sort_by'] ?? 'created_at'] ?? 'payments.created_at';
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 5), 50);
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->filteredQuery($filters)
            ->with(['invoice.tenant:id,name,status', 'invoice.subscription.plan:id,name,plan_type'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('payments.transaction_id', 'like', "%{$search}%")
                        ->orWhere('payments.payment_method', 'like', "%{$search}%")
                        ->orWhereHas('invoice.tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPlatformPayment(int $id): Payment
    {
        return $this->platformPaymentQuery()
            ->with([
                'invoice.tenant:id,name,status,email,phone',
                'invoice.subscription.plan:id,name,plan_type,price,duration',
            ])
            ->findOrFail($id);
    }

    public function summary(array $filters): array
    {
        $query = $this->filteredQuery($filters);
        $paidQuery = (clone $query)->where(function ($paid) {
            $paid->where('payments.payment_status', 'paid')
                ->orWhere(function ($legacy) {
                    $legacy->whereNull('payments.payment_status')
                        ->where('payments.status', 'completed');
                });
        });

        return [
            'total_revenue' => round((float) (clone $paidQuery)->sum('payments.amount'), 2),
            'today_revenue' => round((float) (clone $paidQuery)->whereDate('payments.created_at', Carbon::today()->toDateString())->sum('payments.amount'), 2),
            'failed_payments' => (clone $query)->where(function ($failed) {
                $failed->where('payments.payment_status', 'failed')
                    ->orWhere('payments.status', 'failed');
            })->count(),
            'successful_payments' => (clone $paidQuery)->count(),
        ];
    }

    public function filterOptions(): array
    {
        return [
            'statuses' => ['paid', 'pending', 'failed'],
            'payment_methods' => $this->platformPaymentQuery()
                ->whereNotNull('payments.payment_method')
                ->distinct()
                ->orderBy('payments.payment_method')
                ->pluck('payments.payment_method')
                ->values()
                ->all(),
            'gyms' => Tenant::query()
                ->whereHas('invoices', function ($invoiceQuery) {
                    $invoiceQuery->whereNotNull('subscription_id')
                        ->whereNull('member_id')
                        ->whereNull('membership_id')
                        ->whereHas('payments', function ($paymentQuery) {
                            $paymentQuery->whereNull('payments.member_id')
                                ->whereNull('payments.membership_id');
                        });
                })
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Tenant $tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                ])
                ->all(),
        ];
    }

    protected function filteredQuery(array $filters): Builder
    {
        return $this->platformPaymentQuery()
            ->when(! empty($filters['status']), function ($query) use ($filters) {
                $status = $filters['status'];
                $query->where(function ($statusQuery) use ($status) {
                    $statusQuery->where('payments.payment_status', $status);

                    if ($status === 'paid') {
                        $statusQuery->orWhere(function ($legacy) {
                            $legacy->whereNull('payments.payment_status')
                                ->where('payments.status', 'completed');
                        });
                    }
                });
            })
            ->when(! empty($filters['payment_method']), fn ($query) => $query->where('payments.payment_method', $filters['payment_method']))
            ->when(! empty($filters['tenant_id']), fn ($query) => $query->where('payments.tenant_id', $filters['tenant_id']))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('payments.created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('payments.created_at', '<=', $filters['date_to']));
    }
}

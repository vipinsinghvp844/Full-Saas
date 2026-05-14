<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Http\Resources\Gym\BillingInvoiceResource;
use App\Http\Resources\Gym\BillingPaymentResource;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\Payment;
use App\Services\Gym\BillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingController extends ApiController
{
    public function __construct(protected BillingService $billingService)
    {
    }

    public function dashboard(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $this->billingService->ensureTenantMembershipInvoices($tenantId);
        $this->markOverdueInvoices($tenantId);

        $paidPayments = Payment::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id')
            ->where('payment_status', 'paid');

        $pendingPayments = Payment::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id')
            ->where('payment_status', 'pending');

        $recentPayments = Payment::query()
            ->with(['member.user', 'membership.plan', 'invoice'])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id')
            ->latest()
            ->limit(5)
            ->get();

        return $this->jsonResponse([
            'data' => [
                'total_revenue' => round((float) (clone $paidPayments)->sum('final_amount'), 2),
                'today_revenue' => round((float) (clone $paidPayments)->whereDate('paid_at', now()->toDateString())->sum('final_amount'), 2),
                'pending_payments' => round((float) $pendingPayments->sum('final_amount'), 2),
                'monthly_revenue' => round((float) (clone $paidPayments)
                    ->whereYear('paid_at', now()->year)
                    ->whereMonth('paid_at', now()->month)
                    ->sum('final_amount'), 2),
                'unpaid_invoices' => Invoice::query()
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('member_id')
                    ->whereIn('status', ['unpaid', 'overdue'])
                    ->count(),
                'overdue_amount' => round((float) Invoice::query()
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('member_id')
                    ->where('status', 'overdue')
                    ->sum('final_amount'), 2),
                'recent_payments' => BillingPaymentResource::collection($recentPayments)->resolve(),
            ],
        ], 200, $request);
    }

    public function payments(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'payment_status' => ['nullable', Rule::in(['paid', 'pending', 'failed'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'online', 'UPI', 'card'])],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Payment::query()
            ->with(['member.user', 'membership.plan', 'invoice'])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id');

        if (! empty($data['start_date'])) {
            $query->whereDate('created_at', '>=', $data['start_date']);
        }

        if (! empty($data['end_date'])) {
            $query->whereDate('created_at', '<=', $data['end_date']);
        }

        if (! empty($data['payment_status'])) {
            $query->where('payment_status', $data['payment_status']);
        }

        if (! empty($data['payment_method'])) {
            $query->where('payment_method', $data['payment_method']);
        }

        if (! empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('transaction_id', 'like', "%{$search}%")
                    ->orWhereHas('member.user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('invoice', fn ($invoiceQuery) => $invoiceQuery->where('invoice_number', 'like', "%{$search}%"));
            });
        }

        $paginator = $query
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 15));

        return $this->jsonResponse($this->paginatedData($paginator, BillingPaymentResource::class), 200, $request);
    }

    public function storePayment(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'membership_id' => ['nullable', 'integer', 'exists:member_memberships,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', Rule::in(['cash', 'online', 'UPI', 'card'])],
            'payment_status' => ['required', Rule::in(['paid', 'pending', 'failed'])],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $member = Member::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $data['member_id'])
            ->firstOrFail();

        $membership = null;
        if (! empty($data['membership_id'])) {
            $membership = MemberMembership::query()
                ->where('tenant_id', $tenantId)
                ->where('member_id', $member->id)
                ->where('id', $data['membership_id'])
                ->firstOrFail();
        }

        $payment = $this->billingService->recordPayment($tenantId, $member, $membership, $data);

        return $this->jsonResponse([
            'message' => 'Payment recorded.',
            'data' => (new BillingPaymentResource($payment))->resolve(),
        ], 201, $request);
    }

    public function invoices(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $this->billingService->ensureTenantMembershipInvoices($tenantId);
        $this->markOverdueInvoices($tenantId);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['paid', 'unpaid', 'overdue'])],
            'member_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Invoice::query()
            ->with(['member.user', 'membership.plan'])
            ->withCount('payments')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id');

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['member_id'])) {
            $query->where('member_id', $data['member_id']);
        }

        $paginator = $query
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 10));

        return $this->jsonResponse($this->paginatedData($paginator, BillingInvoiceResource::class), 200, $request);
    }

    protected function markOverdueInvoices(int $tenantId): void
    {
        Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id')
            ->where('status', 'unpaid')
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => 'overdue']);
    }
}

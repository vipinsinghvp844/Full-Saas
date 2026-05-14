<?php

namespace App\Services\Gym;

use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BillingService
{
    public function ensureTenantMembershipInvoices(int $tenantId): void
    {
        MemberMembership::query()
            ->where('tenant_id', $tenantId)
            ->whereDoesntHave('invoices')
            ->with(['member', 'plan'])
            ->orderBy('id')
            ->chunkById(100, function ($memberships) {
                foreach ($memberships as $membership) {
                    $this->ensureMembershipInvoice($membership);
                }
            });
    }

    public function ensureMembershipInvoice(MemberMembership $membership): Invoice
    {
        $membership->loadMissing(['member', 'plan']);

        $totalAmount = round((float) ($membership->plan?->price ?? $membership->final_amount), 2);
        $finalAmount = round((float) $membership->final_amount, 2);
        $discount = round(max(0, $totalAmount - $finalAmount), 2);

        $invoice = Invoice::query()->firstOrNew([
            'tenant_id' => $membership->tenant_id,
            'membership_id' => $membership->id,
        ]);

        $invoice->fill([
            'member_id' => $membership->member_id,
            'subscription_id' => null,
            'amount' => $finalAmount,
            'total_amount' => $totalAmount,
            'discount' => $discount,
            'final_amount' => $finalAmount,
            'status' => $this->invoiceStatusFromMembership($membership),
            'due_date' => $membership->start_date?->toDateString() ?? now()->toDateString(),
        ]);

        $invoice->save();
        $this->ensureInvoiceNumber($invoice);

        return $invoice->fresh(['member.user', 'membership.plan', 'payments']);
    }

    public function recordPayment(int $tenantId, Member $member, ?MemberMembership $membership, array $data): Payment
    {
        return DB::transaction(function () use ($tenantId, $member, $membership, $data) {
            $amount = round((float) $data['amount'], 2);
            $discount = round((float) ($data['discount'] ?? 0), 2);

            if ($discount > $amount) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount cannot be greater than amount.',
                ]);
            }

            $finalAmount = round(max(0, $amount - $discount), 2);
            $paymentStatus = $data['payment_status'];
            $paidAt = $paymentStatus === 'paid'
                ? Carbon::parse($data['paid_at'] ?? now())
                : null;

            $invoice = $membership
                ? $this->ensureMembershipInvoice($membership)
                : $this->createStandaloneInvoice($tenantId, $member, $amount, $discount, $finalAmount, $paymentStatus);

            if ($membership) {
                $invoice->update([
                    'total_amount' => $amount,
                    'amount' => $finalAmount,
                    'discount' => $discount,
                    'final_amount' => $finalAmount,
                    'due_date' => $invoice->due_date?->toDateString() ?? now()->toDateString(),
                ]);
            }

            $payment = Payment::query()->create([
                'tenant_id' => $tenantId,
                'member_id' => $member->id,
                'membership_id' => $membership?->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'discount' => $discount,
                'final_amount' => $finalAmount,
                'payment_method' => $data['payment_method'],
                'transaction_id' => $data['transaction_id'] ?? $this->defaultTransactionId($paymentStatus, $data['payment_method']),
                'status' => $this->legacyStatus($paymentStatus),
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->refreshInvoiceStatus($invoice->fresh('payments'));

            if ($membership) {
                $this->refreshMembershipPaymentStatus($membership->fresh('payments'));
            }

            return $payment->fresh(['member.user', 'membership.plan', 'invoice']);
        });
    }

    public function refreshInvoiceStatus(Invoice $invoice): Invoice
    {
        $paidAmount = (float) $invoice->payments()
            ->where('payment_status', 'paid')
            ->sum('final_amount');
        $invoiceAmount = (float) ($invoice->final_amount ?? $invoice->amount ?? 0);
        $status = $invoiceAmount <= 0 || $paidAmount >= $invoiceAmount
            ? 'paid'
            : ($invoice->due_date?->isPast() ? 'overdue' : 'unpaid');

        $invoice->update(['status' => $status]);

        return $invoice->fresh();
    }

    protected function createStandaloneInvoice(
        int $tenantId,
        Member $member,
        float $amount,
        float $discount,
        float $finalAmount,
        string $paymentStatus
    ): Invoice {
        $invoice = Invoice::query()->create([
            'tenant_id' => $tenantId,
            'member_id' => $member->id,
            'membership_id' => null,
            'subscription_id' => null,
            'amount' => $finalAmount,
            'total_amount' => $amount,
            'discount' => $discount,
            'final_amount' => $finalAmount,
            'status' => $paymentStatus === 'paid' ? 'paid' : 'unpaid',
            'due_date' => now()->toDateString(),
        ]);

        $this->ensureInvoiceNumber($invoice);

        return $invoice->fresh();
    }

    protected function refreshMembershipPaymentStatus(MemberMembership $membership): void
    {
        $paidAmount = (float) $membership->payments()
            ->where('payment_status', 'paid')
            ->sum('final_amount');
        $failedCount = $membership->payments()
            ->where('payment_status', 'failed')
            ->count();
        $targetAmount = (float) ($membership->final_amount ?? 0);

        $status = match (true) {
            $targetAmount <= 0 || $paidAmount >= $targetAmount => 'paid',
            $failedCount > 0 && $paidAmount <= 0 => 'failed',
            default => 'pending',
        };

        $membership->update(['payment_status' => $status]);
    }

    protected function invoiceStatusFromMembership(MemberMembership $membership): string
    {
        return match ($membership->payment_status) {
            'paid' => 'paid',
            default => $membership->start_date?->isPast() ? 'overdue' : 'unpaid',
        };
    }

    protected function ensureInvoiceNumber(Invoice $invoice): void
    {
        if ($invoice->invoice_number) {
            return;
        }

        $invoice->update([
            'invoice_number' => sprintf('GYM-%s-%06d', now()->format('Y'), $invoice->id),
        ]);
    }

    protected function legacyStatus(string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'paid' => 'completed',
            'failed' => 'failed',
            default => 'pending',
        };
    }

    protected function defaultTransactionId(string $paymentStatus, string $paymentMethod): ?string
    {
        if ($paymentStatus !== 'paid' || $paymentMethod === 'cash') {
            return null;
        }

        return Str::upper(Str::random(14));
    }
}

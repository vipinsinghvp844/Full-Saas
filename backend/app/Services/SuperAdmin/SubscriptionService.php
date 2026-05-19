<?php

namespace App\Services\SuperAdmin;

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        protected ActivityLogService $activityLogService
    ) {
    }

    public function assignPlan(array $data, ?User $actor = null): TenantSubscription
    {
        return DB::transaction(function () use ($data, $actor) {
            $tenant = Tenant::findOrFail($data['tenant_id']);
            $plan = PlatformPlan::findOrFail($data['plan_id']);
            $activeSubscription = $tenant->activeSubscription;

            if ($activeSubscription && $activeSubscription->status === 'active') {
                throw ValidationException::withMessages([
                    'tenant_id' => 'This gym already has an active subscription. Use renew or change plan.',
                ]);
            }

            $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $tenant->id, true);
            $financials = $this->calculateFinancials($plan, $coupon);
            $startDate = Carbon::parse($data['start_date'] ?? now()->toDateString());
            $endDate = $startDate->copy()->addMonths($plan->duration);

            $subscription = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'coupon_id' => $coupon?->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => 'active',
                'price' => $financials['price'],
                'discount_amount' => $financials['discount_amount'],
                'final_amount' => $financials['final_amount'],
                'payment_method' => $data['payment_method'] ?? 'manual',
                'payment_status' => 'paid',
            ]);

            $this->createBillingEntries($subscription, $financials, $data['payment_method'] ?? 'manual', $data['transaction_id'] ?? null);
            $this->incrementCouponUsage($coupon);

            if ($actor) {
                $this->activityLogService->record(
                    $actor,
                    'subscription.assigned',
                    $subscription,
                    "Assigned {$plan->name} to {$tenant->name}",
                    [],
                    $subscription->toArray(),
                    $tenant->id
                );
            }

            return $subscription->fresh();
        });
    }

    public function renew(TenantSubscription $subscription, array $data, ?User $actor = null): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $data, $actor) {
            $oldValues = $subscription->toArray();
            $plan = $subscription->plan;
            $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $subscription->tenant_id, true);
            $financials = $this->calculateFinancials($plan, $coupon);
            $currentEndDate = Carbon::parse($subscription->end_date);
            $startDate = $currentEndDate->isPast() ? Carbon::today() : $currentEndDate->copy();
            $endDate = $startDate->copy()->addMonths($plan->duration);

            $subscription->update([
                'coupon_id' => $coupon?->id,
                'end_date' => $endDate->toDateString(),
                'status' => 'active',
                'price' => $financials['price'],
                'discount_amount' => $financials['discount_amount'],
                'final_amount' => $financials['final_amount'],
                'payment_method' => $data['payment_method'] ?? $subscription->payment_method,
                'payment_status' => 'paid',
                'cancelled_at' => null,
            ]);

            $this->createBillingEntries($subscription->fresh(), $financials, $data['payment_method'] ?? $subscription->payment_method, $data['transaction_id'] ?? null);
            $this->incrementCouponUsage($coupon);

            if ($actor) {
                $this->activityLogService->record(
                    $actor,
                    'subscription.renewed',
                    $subscription,
                    "Renewed subscription for {$subscription->tenant->name}",
                    $oldValues,
                    $subscription->fresh()->toArray(),
                    $subscription->tenant_id
                );
            }

            return $subscription->fresh();
        });
    }

    public function cancel(TenantSubscription $subscription, array $data, ?User $actor = null): TenantSubscription
    {
        $oldValues = $subscription->toArray();

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        if ($actor) {
            $this->activityLogService->record(
                $actor,
                'subscription.cancelled',
                $subscription,
                $data['reason'] ?? "Cancelled subscription for {$subscription->tenant->name}",
                $oldValues,
                $subscription->fresh()->toArray(),
                $subscription->tenant_id
            );
        }

        return $subscription->fresh();
    }

    public function changePlan(TenantSubscription $subscription, array $data, ?User $actor = null): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $data, $actor) {
            $oldValues = $subscription->toArray();
            $plan = PlatformPlan::findOrFail($data['plan_id']);
            $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $subscription->tenant_id, true);
            $financials = $this->calculateFinancials($plan, $coupon);
            $startDate = Carbon::today();
            $endDate = $startDate->copy()->addMonths($plan->duration);

            $subscription->update([
                'plan_id' => $plan->id,
                'coupon_id' => $coupon?->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => 'active',
                'price' => $financials['price'],
                'discount_amount' => $financials['discount_amount'],
                'final_amount' => $financials['final_amount'],
                'payment_method' => $data['payment_method'] ?? $subscription->payment_method,
                'payment_status' => 'paid',
                'cancelled_at' => null,
            ]);

            $this->createBillingEntries($subscription->fresh(), $financials, $data['payment_method'] ?? $subscription->payment_method, $data['transaction_id'] ?? null);
            $this->incrementCouponUsage($coupon);

            if ($actor) {
                $this->activityLogService->record(
                    $actor,
                    'subscription.plan_changed',
                    $subscription,
                    "Changed plan for {$subscription->tenant->name} to {$plan->name}",
                    $oldValues,
                    $subscription->fresh()->toArray(),
                    $subscription->tenant_id
                );
            }

            return $subscription->fresh();
        });
    }

    public function previewCoupon(int $tenantId, int $planId, ?string $couponCode): array
    {
        $plan = PlatformPlan::findOrFail($planId);
        $coupon = $this->resolveCoupon($couponCode, $tenantId);
        $financials = $this->calculateFinancials($plan, $coupon);

        return [
            'coupon' => $coupon ? [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->discount_type,
                'value' => (float) $coupon->discount_value,
                'max_discount' => $coupon->max_discount !== null ? (float) $coupon->max_discount : null,
                'usage_limit' => $coupon->usage_limit,
                'used_count' => $coupon->used_count,
                'valid_until' => $coupon->valid_to?->toDateString(),
            ] : null,
            'price' => $financials['price'],
            'plan_discount' => $financials['plan_discount'],
            'coupon_discount' => $financials['coupon_discount'],
            'discount_amount' => $financials['discount_amount'],
            'final_amount' => $financials['final_amount'],
        ];
    }

    protected function resolveCoupon(?string $couponCode, int $tenantId, bool $lockForUpdate = false): ?Coupon
    {
        $couponCode = strtoupper(trim((string) $couponCode));

        if ($couponCode === '') {
            return null;
        }

        $query = Coupon::query()
            ->where('code', $couponCode)
            ->where('tenant_id', $tenantId);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $coupon = $query->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The provided coupon is invalid for this gym.',
            ]);
        }

        if ($coupon->status !== 'active') {
            throw ValidationException::withMessages([
                'coupon_code' => 'The selected coupon is inactive.',
            ]);
        }

        if ($coupon->valid_from && $coupon->valid_from->isFuture()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The selected coupon is not valid yet.',
            ]);
        }

        if ($coupon->valid_to->isPast()) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The selected coupon has expired.',
            ]);
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The selected coupon has reached its usage limit.',
            ]);
        }

        return $coupon;
    }

    protected function calculateFinancials(PlatformPlan $plan, ?Coupon $coupon): array
    {
        $price = (float) $plan->price;
        $planDiscount = (float) ($plan->discount ?? 0);
        $couponDiscount = 0;

        if ($coupon) {
            $rawCouponDiscount = $coupon->discount_type === 'percentage'
                ? ($price * ((float) $coupon->discount_value / 100))
                : (float) $coupon->discount_value;

            $couponDiscount = $coupon->max_discount !== null
                ? min($rawCouponDiscount, (float) $coupon->max_discount)
                : $rawCouponDiscount;
        }

        $discountAmount = min($price, $planDiscount + $couponDiscount);

        return [
            'price' => $price,
            'plan_discount' => round(min($price, $planDiscount), 2),
            'coupon_discount' => round(min(max(0, $price - $planDiscount), $couponDiscount), 2),
            'discount_amount' => round($discountAmount, 2),
            'final_amount' => round(max(0, $price - $discountAmount), 2),
        ];
    }

    protected function createBillingEntries(TenantSubscription $subscription, array $financials, string $paymentMethod, ?string $transactionId = null): void
    {
        $price = (float) $financials['price'];
        $discount = (float) $financials['discount_amount'];
        $finalAmount = (float) $financials['final_amount'];

        $invoice = Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'amount' => $price,
            'total_amount' => $price,
            'discount' => $discount,
            'final_amount' => $finalAmount,
            'status' => 'paid',
            'due_date' => now()->toDateString(),
        ]);

        $invoice->update([
            'invoice_number' => sprintf('SAAS-%s-%06d', now()->format('Y'), $invoice->id),
        ]);

        Payment::create([
            'tenant_id' => $subscription->tenant_id,
            'invoice_id' => $invoice->id,
            'amount' => $finalAmount,
            'discount' => $discount,
            'final_amount' => $finalAmount,
            'payment_method' => $paymentMethod,
            'transaction_id' => $transactionId ?? Str::upper(Str::random(12)),
            'status' => 'completed',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'notes' => in_array($paymentMethod, ['stripe', 'razorpay'], true)
                ? "Platform subscription payment confirmed by {$paymentMethod}."
                : 'Platform subscription payment recorded manually.',
        ]);
    }

    protected function incrementCouponUsage(?Coupon $coupon): void
    {
        if ($coupon) {
            $coupon->increment('used_count');
        }
    }

    public function activateFromWebhook(int $tenantId, int $planId, string $transactionId, float $amount, string $paymentProvider, ?string $couponCode = null): ?TenantSubscription
    {
        // Idempotency check: see if we already processed this transaction
        $existingPayment = Payment::whereNull('member_id')
            ->whereNull('membership_id')
            ->where('transaction_id', $transactionId)
            ->first();

        if ($existingPayment) {
            return null; // Already processed
        }

        $tenant = Tenant::findOrFail($tenantId);
        $activeSubscription = $tenant->activeSubscription;
        $preview = $this->previewCoupon($tenantId, $planId, $couponCode);

        if (abs((float) $preview['final_amount'] - $amount) > 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'The paid amount does not match the coupon-adjusted plan amount.',
            ]);
        }

        $data = [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'payment_method' => $paymentProvider,
            'transaction_id' => $transactionId,
            'coupon_code' => $couponCode,
        ];

        // If they have an active subscription, just renew/change plan
        if ($activeSubscription && in_array($activeSubscription->status, ['active', 'trial'])) {
            if ($activeSubscription->plan_id == $planId) {
                return $this->renew($activeSubscription, $data);
            } else {
                return $this->changePlan($activeSubscription, $data);
            }
        }

        // If they are expired or have no sub, assign a new one
        if ($activeSubscription) {
            // Cancel old one just to be clean
            $activeSubscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        return $this->assignPlan($data);
    }
}

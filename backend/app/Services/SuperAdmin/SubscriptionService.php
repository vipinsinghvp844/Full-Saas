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

    public function assignPlan(array $data, User $actor): TenantSubscription
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

            $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $tenant->id);
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
            ]);

            $this->createBillingEntries($subscription, $financials['final_amount'], $data['payment_method'] ?? 'manual');
            $this->incrementCouponUsage($coupon);

            $this->activityLogService->record(
                $actor,
                'subscription.assigned',
                $subscription,
                "Assigned {$plan->name} to {$tenant->name}",
                [],
                $subscription->toArray(),
                $tenant->id
            );

            return $subscription->fresh();
        });
    }

    public function renew(TenantSubscription $subscription, array $data, User $actor): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $data, $actor) {
            $oldValues = $subscription->toArray();
            $plan = $subscription->plan;
            $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $subscription->tenant_id);
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
                'cancelled_at' => null,
            ]);

            $this->createBillingEntries($subscription->fresh(), $financials['final_amount'], $data['payment_method'] ?? $subscription->payment_method);
            $this->incrementCouponUsage($coupon);

            $this->activityLogService->record(
                $actor,
                'subscription.renewed',
                $subscription,
                "Renewed subscription for {$subscription->tenant->name}",
                $oldValues,
                $subscription->fresh()->toArray(),
                $subscription->tenant_id
            );

            return $subscription->fresh();
        });
    }

    public function cancel(TenantSubscription $subscription, array $data, User $actor): TenantSubscription
    {
        $oldValues = $subscription->toArray();

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        $this->activityLogService->record(
            $actor,
            'subscription.cancelled',
            $subscription,
            $data['reason'] ?? "Cancelled subscription for {$subscription->tenant->name}",
            $oldValues,
            $subscription->fresh()->toArray(),
            $subscription->tenant_id
        );

        return $subscription->fresh();
    }

    public function changePlan(TenantSubscription $subscription, array $data, User $actor): TenantSubscription
    {
        return DB::transaction(function () use ($subscription, $data, $actor) {
            $oldValues = $subscription->toArray();
            $plan = PlatformPlan::findOrFail($data['plan_id']);
            $coupon = $this->resolveCoupon($data['coupon_code'] ?? null, $subscription->tenant_id);
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
                'cancelled_at' => null,
            ]);

            $this->createBillingEntries($subscription->fresh(), $financials['final_amount'], $data['payment_method'] ?? $subscription->payment_method);
            $this->incrementCouponUsage($coupon);

            $this->activityLogService->record(
                $actor,
                'subscription.plan_changed',
                $subscription,
                "Changed plan for {$subscription->tenant->name} to {$plan->name}",
                $oldValues,
                $subscription->fresh()->toArray(),
                $subscription->tenant_id
            );

            return $subscription->fresh();
        });
    }

    protected function resolveCoupon(?string $couponCode, int $tenantId): ?Coupon
    {
        if (! $couponCode) {
            return null;
        }

        $coupon = Coupon::query()
            ->where('code', $couponCode)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'coupon_code' => 'The provided coupon is invalid for this gym.',
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
            $couponDiscount = $coupon->discount_type === 'percentage'
                ? ($price * ((float) $coupon->discount_value / 100))
                : (float) $coupon->discount_value;
        }

        $discountAmount = min($price, $planDiscount + $couponDiscount);

        return [
            'price' => $price,
            'discount_amount' => round($discountAmount, 2),
            'final_amount' => round(max(0, $price - $discountAmount), 2),
        ];
    }

    protected function createBillingEntries(TenantSubscription $subscription, float $amount, string $paymentMethod): void
    {
        $invoice = Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'total_amount' => $amount,
            'discount' => 0,
            'final_amount' => $amount,
            'status' => 'paid',
            'due_date' => now()->toDateString(),
        ]);

        $invoice->update([
            'invoice_number' => sprintf('SAAS-%s-%06d', now()->format('Y'), $invoice->id),
        ]);

        Payment::create([
            'tenant_id' => $subscription->tenant_id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'discount' => 0,
            'final_amount' => $amount,
            'payment_method' => $paymentMethod,
            'transaction_id' => Str::upper(Str::random(12)),
            'status' => 'completed',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    protected function incrementCouponUsage(?Coupon $coupon): void
    {
        if ($coupon) {
            $coupon->increment('used_count');
        }
    }
}

<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Models\PlatformPlan;
use App\Models\TenantSubscription;
use App\Services\SuperAdmin\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use App\Services\SuperAdmin\PlatformSettingsService;

class PlatformSubscriptionController extends ApiController
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PlatformSettingsService $settingsService
    ) {
    }

    public function plans(Request $request)
    {
        $plans = PlatformPlan::where('status', 'active')
            ->orderBy('price')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price' => (float) $plan->price,
                    'discount' => (float) $plan->discount,
                    'final_price' => max(0, (float) $plan->price - (float) $plan->discount),
                    'duration' => $plan->duration,
                    'max_members' => $plan->max_members,
                    'max_trainers' => $plan->max_trainers,
                    'max_branches' => $plan->max_branches,
                    'features' => $plan->features,
                ];
            });

        return $this->jsonResponse(['data' => $plans]);
    }

    public function current(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $subscription = TenantSubscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->latest()
            ->first();

        if (!$subscription) {
            return $this->jsonResponse(['data' => null]);
        }

        return $this->jsonResponse([
            'data' => [
                'id' => $subscription->id,
                'plan' => $subscription->plan ? $subscription->plan->name : null,
                'status' => $subscription->status,
                'start_date' => $subscription->start_date ? $subscription->start_date->toDateString() : null,
                'end_date' => $subscription->end_date ? $subscription->end_date->toDateString() : null,
                'grace_period_ends_at' => $subscription->grace_period_ends_at,
                'is_active' => in_array($subscription->status, ['active', 'trial']) && $subscription->end_date >= now()->startOfDay(),
            ]
        ]);
    }

    public function subscribe(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $tenant = $request->user()->tenant;

        $data = $request->validate([
            'plan_id' => ['required', 'exists:platform_plans,id'],
            'payment_provider' => ['required', Rule::in(['stripe', 'razorpay'])],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'success_url' => ['required', 'url'],
            'cancel_url' => ['required', 'url'],
        ]);

        $plan = PlatformPlan::findOrFail($data['plan_id']);
        $couponCode = isset($data['coupon_code']) ? strtoupper(trim($data['coupon_code'])) : null;
        $preview = $this->subscriptionService->previewCoupon((int) $tenantId, (int) $plan->id, $couponCode);
        $finalPrice = (float) $preview['final_amount'];

        // If price is 0 (e.g. Free Tier or full discount), activate immediately
        if ($finalPrice <= 0) {
            $this->subscriptionService->activateFromWebhook(
                (int) $tenantId,
                (int) $plan->id,
                'free_' . $tenantId . '_' . $plan->id . '_' . Str::uuid(),
                0,
                'free',
                $couponCode
            );

            return $this->jsonResponse([
                'payment_required' => false,
                'message' => 'Subscription activated successfully.',
            ]);
        }

        $settings = $this->settingsService->getAllSettings();

        if ($data['payment_provider'] === 'stripe') {
            $stripeSecret = $settings['payment']['stripe_secret'] ?? null;
            if (empty($stripeSecret) || $stripeSecret === 'sk_test_placeholder') {
                return $this->jsonResponse(['error' => 'Stripe is not configured.'], 400);
            }

            $stripe = new \Stripe\StripeClient($stripeSecret);
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($settings['billing']['currency'] ?? 'usd'),
                        'product_data' => [
                            'name' => $plan->name,
                            'description' => $couponCode
                                ? "Subscription to {$plan->name} with coupon {$couponCode}"
                                : "Subscription to {$plan->name}",
                        ],
                        'unit_amount' => (int) ($finalPrice * 100), // Stripe expects cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $this->appendStripeSessionPlaceholder($data['success_url']),
                'cancel_url' => $data['cancel_url'],
                'client_reference_id' => $tenantId,
                'metadata' => array_filter([
                    'tenant_id' => $tenantId,
                    'plan_id' => $plan->id,
                    'coupon_code' => $couponCode,
                    'coupon_discount' => $preview['coupon_discount'],
                    'final_amount' => $preview['final_amount'],
                ], fn ($value) => $value !== null && $value !== ''),
            ]);

            return $this->jsonResponse([
                'payment_required' => true,
                'provider' => 'stripe',
                'checkout_url' => $session->url,
                'pricing' => $preview,
            ]);
        }

        if ($data['payment_provider'] === 'razorpay') {
            $razorpayKey = $settings['payment']['razorpay_key'] ?? null;
            $razorpaySecret = $settings['payment']['razorpay_secret'] ?? null;
            
            if (empty($razorpayKey) || empty($razorpaySecret) || $razorpayKey === 'rzp_test_placeholder') {
                return $this->jsonResponse(['error' => 'Razorpay is not configured.'], 400);
            }

            $api = new \Razorpay\Api\Api($razorpayKey, $razorpaySecret);
            $order = $api->order->create([
                'receipt' => 'rcpt_' . $tenantId . '_' . time(),
                'amount' => (int) ($finalPrice * 100), // Razorpay expects paise
                'currency' => 'INR',
                'notes' => array_filter([
                    'tenant_id' => $tenantId,
                    'plan_id' => $plan->id,
                    'coupon_code' => $couponCode,
                    'coupon_discount' => $preview['coupon_discount'],
                    'final_amount' => $preview['final_amount'],
                ], fn ($value) => $value !== null && $value !== '')
            ]);

            return $this->jsonResponse([
                'payment_required' => true,
                'provider' => 'razorpay',
                'order_id' => $order['id'],
                'amount' => $order['amount'],
                'currency' => $order['currency'],
                'key' => $razorpayKey,
                'name' => 'Gym SaaS Platform',
                'description' => "Subscription to " . $plan->name,
                'pricing' => $preview,
            ]);
        }
    }

    public function validateCoupon(Request $request)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:platform_plans,id'],
            'coupon_code' => ['required', 'string', 'max:100'],
        ]);

        return $this->jsonResponse([
            'data' => $this->subscriptionService->previewCoupon(
                (int) $request->user()->tenant_id,
                (int) $data['plan_id'],
                $data['coupon_code']
            ),
        ], 200, $request);
    }

    public function confirmStripeCheckout(Request $request)
    {
        $data = $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        $settings = $this->settingsService->getAllSettings();
        $stripeSecret = $settings['payment']['stripe_secret'] ?? null;
        if (empty($stripeSecret) || $stripeSecret === 'sk_test_placeholder') {
            return $this->jsonResponse(['message' => 'Stripe is not configured.'], 400);
        }

        try {
            $stripe = new \Stripe\StripeClient($stripeSecret);
            $session = $stripe->checkout->sessions->retrieve($data['session_id']);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['message' => 'Unable to verify Stripe checkout session.'], 422);
        }

        if (($session->status ?? null) !== 'complete' || ($session->payment_status ?? null) !== 'paid') {
            return $this->jsonResponse(['message' => 'Payment is not complete yet.'], 422);
        }

        $metadata = $session->metadata ? $session->metadata->toArray() : [];
        $tenantId = (int) ($session->client_reference_id ?? ($metadata['tenant_id'] ?? 0));
        $planId = (int) ($metadata['plan_id'] ?? 0);
        $couponCode = $metadata['coupon_code'] ?? null;

        if (!$tenantId || !$planId) {
            return $this->jsonResponse(['message' => 'Checkout session is missing subscription metadata.'], 422);
        }

        if ($tenantId !== (int) $request->user()->tenant_id) {
            return $this->jsonResponse(['message' => 'Checkout session does not belong to this gym.'], 403);
        }

        $transactionId = (string) ($session->payment_intent ?? $session->id);
        $amount = ((float) ($session->amount_total ?? 0)) / 100;

        $this->subscriptionService->activateFromWebhook($tenantId, $planId, $transactionId, $amount, 'stripe', $couponCode);

        return $this->current($request);
    }

    public function confirmRazorpayPayment(Request $request)
    {
        $data = $request->validate([
            'payment_id' => ['required', 'string'],
            'order_id' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        $settings = $this->settingsService->getAllSettings();
        $razorpayKey = $settings['payment']['razorpay_key'] ?? null;
        $razorpaySecret = $settings['payment']['razorpay_secret'] ?? null;

        if (empty($razorpayKey) || empty($razorpaySecret) || $razorpayKey === 'rzp_test_placeholder') {
            return $this->jsonResponse(['message' => 'Razorpay is not configured.'], 400);
        }

        $expectedSignature = hash_hmac('sha256', $data['order_id'] . '|' . $data['payment_id'], $razorpaySecret);
        if (!hash_equals($expectedSignature, $data['signature'])) {
            return $this->jsonResponse(['message' => 'Invalid Razorpay payment signature.'], 422);
        }

        try {
            $api = new \Razorpay\Api\Api($razorpayKey, $razorpaySecret);
            $payment = $api->payment->fetch($data['payment_id']);
            $order = $api->order->fetch($data['order_id']);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['message' => 'Unable to verify Razorpay payment.'], 422);
        }

        if (($payment['status'] ?? null) !== 'captured') {
            return $this->jsonResponse(['message' => 'Payment is not captured yet.'], 422);
        }

        $notes = array_filter([
            'tenant_id' => $payment['notes']['tenant_id'] ?? $order['notes']['tenant_id'] ?? null,
            'plan_id' => $payment['notes']['plan_id'] ?? $order['notes']['plan_id'] ?? null,
            'coupon_code' => $payment['notes']['coupon_code'] ?? $order['notes']['coupon_code'] ?? null,
        ]);

        $tenantId = (int) ($notes['tenant_id'] ?? 0);
        $planId = (int) ($notes['plan_id'] ?? 0);
        $couponCode = $notes['coupon_code'] ?? null;

        if (!$tenantId || !$planId) {
            return $this->jsonResponse(['message' => 'Payment is missing subscription metadata.'], 422);
        }

        if ($tenantId !== (int) $request->user()->tenant_id) {
            return $this->jsonResponse(['message' => 'Payment does not belong to this gym.'], 403);
        }

        $amount = ((float) ($payment['amount'] ?? 0)) / 100;

        $this->subscriptionService->activateFromWebhook($tenantId, $planId, $data['payment_id'], $amount, 'razorpay', $couponCode);

        return $this->current($request);
    }

    protected function appendStripeSessionPlaceholder(string $successUrl): string
    {
        $separator = str_contains($successUrl, '?') ? '&' : '?';

        return $successUrl . $separator . 'payment_success=stripe&session_id={CHECKOUT_SESSION_ID}';
    }
}

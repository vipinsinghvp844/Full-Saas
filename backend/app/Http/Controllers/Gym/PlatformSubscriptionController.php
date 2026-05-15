<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Models\PlatformPlan;
use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformSubscriptionController extends ApiController
{
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
            'success_url' => ['required', 'url'],
            'cancel_url' => ['required', 'url'],
        ]);

        $plan = PlatformPlan::findOrFail($data['plan_id']);
        $finalPrice = max(0, (float) $plan->price - (float) $plan->discount);

        // If price is 0 (e.g. Free Tier or full discount), activate immediately
        if ($finalPrice <= 0) {
            return $this->jsonResponse([
                'payment_required' => false,
                'message' => 'Subscription activated successfully.',
            ]);
        }

        if ($data['payment_provider'] === 'stripe') {
            $stripeSecret = env('STRIPE_SECRET');
            if (empty($stripeSecret) || $stripeSecret === 'sk_test_placeholder') {
                return $this->jsonResponse(['error' => 'Stripe is not configured.'], 400);
            }

            $stripe = new \Stripe\StripeClient($stripeSecret);
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd', // Or INR, based on your platform config
                        'product_data' => [
                            'name' => $plan->name,
                            'description' => "Subscription to " . $plan->name,
                        ],
                        'unit_amount' => (int) ($finalPrice * 100), // Stripe expects cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $data['success_url'],
                'cancel_url' => $data['cancel_url'],
                'client_reference_id' => $tenantId,
                'metadata' => [
                    'tenant_id' => $tenantId,
                    'plan_id' => $plan->id,
                ],
            ]);

            return $this->jsonResponse([
                'payment_required' => true,
                'provider' => 'stripe',
                'checkout_url' => $session->url,
            ]);
        }

        if ($data['payment_provider'] === 'razorpay') {
            $razorpayKey = env('RAZORPAY_KEY');
            $razorpaySecret = env('RAZORPAY_SECRET');
            
            if (empty($razorpayKey) || empty($razorpaySecret) || $razorpayKey === 'rzp_test_placeholder') {
                return $this->jsonResponse(['error' => 'Razorpay is not configured.'], 400);
            }

            $api = new \Razorpay\Api\Api($razorpayKey, $razorpaySecret);
            $order = $api->order->create([
                'receipt' => 'rcpt_' . $tenantId . '_' . time(),
                'amount' => (int) ($finalPrice * 100), // Razorpay expects paise
                'currency' => 'INR',
                'notes' => [
                    'tenant_id' => $tenantId,
                    'plan_id' => $plan->id,
                ]
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
            ]);
        }
    }
}

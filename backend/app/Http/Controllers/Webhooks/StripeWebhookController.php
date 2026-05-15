<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\ApiController;
use App\Services\SuperAdmin\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends ApiController
{
    public function __construct(protected SubscriptionService $subscriptionService)
    {
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = env('STRIPE_WEBHOOK_SECRET');

        if (empty($webhookSecret) || $webhookSecret === 'whsec_placeholder') {
            Log::warning('Stripe webhook failed: Secret not configured.');
            return response()->json(['error' => 'Webhook secret not configured'], 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook failed: Invalid payload');
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook failed: Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            
            $tenantId = $session->client_reference_id ?? $session->metadata->tenant_id ?? null;
            $planId = $session->metadata->plan_id ?? null;
            $amount = $session->amount_total / 100; // Stripe uses cents
            $transactionId = $session->payment_intent ?? $session->id;

            if ($tenantId && $planId) {
                try {
                    $this->subscriptionService->activateFromWebhook(
                        (int) $tenantId, 
                        (int) $planId, 
                        $transactionId, 
                        (float) $amount, 
                        'stripe'
                    );
                    Log::info("Stripe Webhook: Processed plan {$planId} for tenant {$tenantId}");
                } catch (\Exception $e) {
                    Log::error("Stripe Webhook activation failed: " . $e->getMessage());
                    return response()->json(['error' => 'Activation failed'], 500);
                }
            } else {
                Log::warning('Stripe Webhook: Missing tenant_id or plan_id in metadata');
            }
        }

        return response()->json(['status' => 'success']);
    }
}

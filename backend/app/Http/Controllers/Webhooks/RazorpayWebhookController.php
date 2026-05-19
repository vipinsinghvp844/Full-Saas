<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\ApiController;
use App\Services\SuperAdmin\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Services\SuperAdmin\PlatformSettingsService;

class RazorpayWebhookController extends ApiController
{
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PlatformSettingsService $settingsService
    ) {
    }

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');
        
        $settings = $this->settingsService->getAllSettings();
        $webhookSecret = $settings['payment']['razorpay_webhook'] ?? null;

        if (empty($webhookSecret) || $webhookSecret === 'webhook_placeholder') {
            Log::warning('Razorpay webhook failed: Secret not configured.');
            return response()->json(['error' => 'Webhook secret not configured'], 400);
        }

        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
        if (!hash_equals($expectedSignature, $signature)) {
            Log::error('Razorpay webhook failed: Invalid signature');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);

        if ($event['event'] === 'payment.captured') {
            $payment = $event['payload']['payment']['entity'];
            
            $tenantId = $payment['notes']['tenant_id'] ?? null;
            $planId = $payment['notes']['plan_id'] ?? null;
            $couponCode = $payment['notes']['coupon_code'] ?? null;
            $amount = $payment['amount'] / 100; // Razorpay uses paise
            $transactionId = $payment['id'];

            if ($tenantId && $planId) {
                try {
                    $this->subscriptionService->activateFromWebhook(
                        (int) $tenantId, 
                        (int) $planId, 
                        $transactionId, 
                        (float) $amount, 
                        'razorpay',
                        $couponCode
                    );
                    Log::info("Razorpay Webhook: Processed plan {$planId} for tenant {$tenantId}");
                } catch (\Exception $e) {
                    Log::error("Razorpay Webhook activation failed: " . $e->getMessage());
                    return response()->json(['error' => 'Activation failed'], 500);
                }
            } else {
                Log::warning('Razorpay Webhook: Missing tenant_id or plan_id in notes');
            }
        }

        return response()->json(['status' => 'success']);
    }
}

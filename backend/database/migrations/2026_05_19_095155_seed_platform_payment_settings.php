<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $stripeKey = env('STRIPE_KEY');
        $stripeSecret = env('STRIPE_SECRET');
        $stripeWebhook = env('STRIPE_WEBHOOK_SECRET');
        $razorpayKey = env('RAZORPAY_KEY');
        $razorpaySecret = env('RAZORPAY_SECRET');
        $razorpayWebhook = env('RAZORPAY_WEBHOOK_SECRET');

        // Check if any keys exist in env
        if ($stripeKey || $stripeSecret || $razorpayKey || $razorpaySecret) {
            $settingsService = app(\App\Services\SuperAdmin\PlatformSettingsService::class);
            $settingsService->updateSettings('payment', [
                'stripe_enabled' => !empty($stripeSecret) && $stripeSecret !== 'sk_test_placeholder',
                'razorpay_enabled' => !empty($razorpaySecret) && $razorpaySecret !== 'rzp_test_placeholder',
                'stripe_key' => $stripeKey ?? '',
                'stripe_secret' => $stripeSecret ?? '',
                'stripe_webhook' => ($stripeWebhook === 'whsec_placeholder') ? '' : ($stripeWebhook ?? ''),
                'razorpay_key' => $razorpayKey ?? '',
                'razorpay_secret' => $razorpaySecret ?? '',
                'razorpay_webhook' => ($razorpayWebhook === 'webhook_placeholder') ? '' : ($razorpayWebhook ?? ''),
                'test_mode' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

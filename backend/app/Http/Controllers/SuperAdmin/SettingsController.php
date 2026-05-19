<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ApiController;
use App\Services\SuperAdmin\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends ApiController
{
    public function __construct(protected PlatformSettingsService $platformSettingsService)
    {
    }

    public function index(Request $request)
    {
        return $this->jsonResponse([
            'data' => $this->platformSettingsService->getAllSettings(),
        ], 200, $request);
    }

    public function update(Request $request, string $group)
    {
        $rules = $this->getValidationRules($group);
        $validated = $request->validate($rules);

        $this->platformSettingsService->updateSettings($group, $validated);

        return $this->jsonResponse([
            'message' => 'Settings updated successfully',
            'data' => $this->platformSettingsService->getAllSettings()[$group] ?? [],
        ], 200, $request);
    }

    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        $file = $request->file('file');
        $path = $file->store('cms', 'public');
        
        // Generate full URL for the frontend
        $url = asset('storage/' . $path);

        return $this->jsonResponse([
            'message' => 'File uploaded successfully',
            'url' => $url,
            'path' => $path
        ]);
    }

    protected function getValidationRules(string $group): array
    {
        return match ($group) {
            'platform' => [
                'name' => ['required', 'string', 'max:255'],
                'logo' => ['nullable', 'string'],
                'support_email' => ['required', 'email', 'max:255'],
                'support_phone' => ['nullable', 'string', 'max:50'],
            ],
            'payment' => [
                'stripe_enabled' => ['required', 'boolean'],
                'razorpay_enabled' => ['required', 'boolean'],
                'stripe_key' => ['nullable', 'string', 'max:255'],
                'stripe_secret' => ['nullable', 'string', 'max:255'],
                'stripe_webhook' => ['nullable', 'string', 'max:255'],
                'razorpay_key' => ['nullable', 'string', 'max:255'],
                'razorpay_secret' => ['nullable', 'string', 'max:255'],
                'razorpay_webhook' => ['nullable', 'string', 'max:255'],
                'test_mode' => ['required', 'boolean'],
            ],
            'billing' => [
                'currency' => ['required', 'string', 'size:3'],
                'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
                'trial_days' => ['required', 'integer', 'min:0'],
                'grace_period_days' => ['required', 'integer', 'min:0'],
                'auto_suspend' => ['required', 'boolean'],
            ],
            'coupons' => [
                'enable_coupons' => ['required', 'boolean'],
                'max_discount_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
                'max_usage_per_coupon' => ['required', 'integer', 'min:1'],
            ],
            'tenant' => [
                'allow_signup' => ['required', 'boolean'],
                'auto_approve' => ['required', 'boolean'],
                'default_plan_id' => ['nullable', 'integer'],
            ],
            'security' => [
                'session_timeout_minutes' => ['required', 'integer', 'min:1'],
                'max_login_attempts' => ['required', 'integer', 'min:1'],
                'require_strong_password' => ['required', 'boolean'],
            ],
            'notifications' => [
                'email_enabled' => ['required', 'boolean'],
                'sms_enabled' => ['required', 'boolean'],
                'webhook_url' => ['nullable', 'string', 'url', 'max:500'],
            ],
            'features' => [
                'enable_classes' => ['required', 'boolean'],
                'enable_trainers' => ['required', 'boolean'],
                'enable_store' => ['required', 'boolean'],
                'enable_diet_plans' => ['required', 'boolean'],
            ],
            'system' => [
                'maintenance_mode' => ['required', 'boolean'],
                'debug_mode' => ['required', 'boolean'],
            ],
            'cms' => [
                'active_template' => ['required', 'string'],
                
                // Header
                'header_logo_type' => ['nullable', 'string', 'in:text,image'],
                'header_logo_text' => ['nullable', 'string', 'max:255'],
                'header_logo_image' => ['nullable', 'string'],
                'header_nav_links' => ['nullable', 'array'],
                'header_nav_links.*.label' => ['required_with:header_nav_links', 'string', 'max:50'],
                'header_nav_links.*.url' => ['required_with:header_nav_links', 'string', 'max:255'],

                // Hero
                'hero_title' => ['required', 'string', 'max:255'],
                'hero_subtitle' => ['required', 'string', 'max:500'],
                'hero_image' => ['nullable', 'string'],
                'hero_cta_text' => ['nullable', 'string', 'max:50'],
                'hero_cta_url' => ['nullable', 'string', 'max:255'],

                // Trusted By
                'trusted_by_text' => ['nullable', 'string', 'max:100'],
                'trusted_by_logos' => ['nullable', 'array'],
                'trusted_by_logos.*' => ['string'],

                // About
                'about_title' => ['nullable', 'string', 'max:255'],
                'about_text' => ['nullable', 'string', 'max:2000'],
                
                // Features
                'features_title' => ['nullable', 'string', 'max:255'],
                'features_subtitle' => ['nullable', 'string', 'max:500'],
                'features' => ['nullable', 'array'],
                'features.*.title' => ['required_with:features', 'string', 'max:100'],
                'features.*.description' => ['required_with:features', 'string', 'max:255'],
                'features.*.icon' => ['required_with:features', 'string', 'max:50'],

                // How it Works
                'how_it_works_title' => ['nullable', 'string', 'max:255'],
                'how_it_works_subtitle' => ['nullable', 'string', 'max:500'],
                'how_it_works' => ['nullable', 'array'],
                'how_it_works.*.title' => ['required_with:how_it_works', 'string', 'max:100'],
                'how_it_works.*.description' => ['required_with:how_it_works', 'string', 'max:255'],
                'how_it_works.*.icon' => ['required_with:how_it_works', 'string', 'max:50'],

                // Testimonials
                'testimonials_title' => ['nullable', 'string', 'max:255'],
                'testimonials_subtitle' => ['nullable', 'string', 'max:500'],
                'testimonials' => ['nullable', 'array'],
                'testimonials.*.name' => ['required_with:testimonials', 'string', 'max:100'],
                'testimonials.*.role' => ['required_with:testimonials', 'string', 'max:100'],
                'testimonials.*.content' => ['required_with:testimonials', 'string', 'max:1000'],
                'testimonials.*.avatar_url' => ['nullable', 'string'],

                // FAQs
                'faqs_title' => ['nullable', 'string', 'max:255'],
                'faqs' => ['nullable', 'array'],
                'faqs.*.question' => ['required_with:faqs', 'string', 'max:500'],
                'faqs.*.answer' => ['required_with:faqs', 'string', 'max:2000'],

                // Final CTA
                'cta_title' => ['nullable', 'string', 'max:255'],
                'cta_text' => ['nullable', 'string', 'max:500'],
                'cta_button_text' => ['nullable', 'string', 'max:50'],
                'cta_button_url' => ['nullable', 'string', 'max:255'],

                // SEO & Footer
                'seo_title' => ['required', 'string', 'max:255'],
                'seo_description' => ['required', 'string', 'max:500'],
                'footer_text' => ['nullable', 'string', 'max:500'],
            ],
            default => throw new \InvalidArgumentException("Invalid settings group: {$group}"),
        };
    }
}

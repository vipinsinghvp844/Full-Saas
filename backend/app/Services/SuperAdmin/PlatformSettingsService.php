<?php

namespace App\Services\SuperAdmin;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class PlatformSettingsService
{
    protected const CACHE_KEY = 'platform_settings_all';
    protected const CACHE_TTL = 86400; // 24 hours

    public function getDefaultSettings(): array
    {
        return [
            'platform' => [
                'name' => 'SaaS Platform',
                'logo' => '',
                'support_email' => 'support@example.com',
                'support_phone' => '',
            ],
            'payment' => [
                'stripe_enabled' => false,
                'razorpay_enabled' => false,
                'stripe_key' => '',
                'stripe_secret' => '',
                'stripe_webhook' => '',
                'razorpay_key' => '',
                'razorpay_secret' => '',
                'razorpay_webhook' => '',
                'test_mode' => true,
            ],
            'billing' => [
                'currency' => 'USD',
                'tax_rate' => 0,
                'trial_days' => 14,
                'grace_period_days' => 3,
                'auto_suspend' => true,
            ],
            'coupons' => [
                'enable_coupons' => true,
                'max_discount_percentage' => 100,
                'max_usage_per_coupon' => 1000,
            ],
            'tenant' => [
                'allow_signup' => true,
                'auto_approve' => true,
                'default_plan_id' => null,
            ],
            'security' => [
                'session_timeout_minutes' => 120,
                'max_login_attempts' => 5,
                'require_strong_password' => true,
            ],
            'notifications' => [
                'email_enabled' => true,
                'sms_enabled' => false,
                'webhook_url' => '',
            ],
            'features' => [
                'enable_classes' => true,
                'enable_trainers' => true,
                'enable_store' => true,
                'enable_diet_plans' => true,
            ],
            'system' => [
                'maintenance_mode' => false,
                'debug_mode' => false,
            ],
            'cms' => [
                'active_template' => 'animated-glass',
                
                'header_logo_type' => 'text',
                'header_logo_text' => 'Gym SaaS',
                'header_logo_image' => '',
                'header_nav_links' => [
                    ['label' => 'Features', 'url' => '#features'],
                    ['label' => 'How it Works', 'url' => '#how-it-works'],
                    ['label' => 'Testimonials', 'url' => '#testimonials'],
                    ['label' => 'FAQ', 'url' => '#faq'],
                ],

                'hero_title' => 'The Ultimate Gym SaaS Platform',
                'hero_subtitle' => 'Manage your fitness business, memberships, and billing with ease.',
                'hero_image' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?q=80&w=2000&auto=format&fit=crop',
                'hero_cta_text' => 'Start Free Trial',
                'hero_cta_url' => '/register',

                'trusted_by_text' => 'TRUSTED BY INNOVATIVE GYMS WORLDWIDE',
                'trusted_by_logos' => ['FitLife', 'IronForge', 'PeakFitness', 'CoreStudio', 'FlexGym'],

                'about_title' => 'Empowering Gym Owners',
                'about_text' => 'We built this platform to help fitness entrepreneurs scale their businesses without the headache of complex software. Everything you need, all in one place.',
                
                'features_title' => 'Everything you need to grow',
                'features_subtitle' => 'Powerful features designed specifically for fitness businesses.',
                'features' => [
                    ['title' => 'Member Management', 'description' => 'Easily track and manage all your gym members.', 'icon' => 'Users'],
                    ['title' => 'Automated Billing', 'description' => 'Collect payments seamlessly via Stripe & Razorpay.', 'icon' => 'CreditCard'],
                    ['title' => 'Class Scheduling', 'description' => 'Organize classes and let members book online.', 'icon' => 'Calendar'],
                    ['title' => 'Trainer Portal', 'description' => 'Give trainers their own dashboard to manage clients.', 'icon' => 'Activity'],
                    ['title' => 'Analytics & Reports', 'description' => 'Track MRR, churn rate, and attendance with beautiful charts.', 'icon' => 'BarChart'],
                    ['title' => 'Access Control', 'description' => 'Integrate with door scanners and turnstiles automatically.', 'icon' => 'Lock'],
                ],

                'how_it_works_title' => 'How It Works',
                'how_it_works_subtitle' => 'Get up and running in minutes, not days.',
                'how_it_works' => [
                    ['title' => '1. Create Your Space', 'description' => 'Setup your gym profile, add locations, and configure your branding.', 'icon' => 'MapPin'],
                    ['title' => '2. Setup Plans', 'description' => 'Create memberships, class packs, and drop-in pricing rules.', 'icon' => 'Tag'],
                    ['title' => '3. Invite Members', 'description' => 'Import your existing members or start accepting new signups instantly.', 'icon' => 'UserPlus'],
                ],

                'testimonials_title' => 'What our customers say',
                'testimonials_subtitle' => 'Join hundreds of gym owners already using our platform.',
                'testimonials' => [
                    ['name' => 'Sarah Johnson', 'role' => 'Owner, FitLife Gym', 'content' => 'This platform transformed how we run our gym. The automated billing alone saved us 20 hours a month!', 'avatar_url' => 'https://i.pravatar.cc/150?img=1'],
                    ['name' => 'Mike Chen', 'role' => 'Head Trainer', 'content' => 'Incredible scheduling features. My clients can book sessions easily, and I can manage my whole day from my phone.', 'avatar_url' => 'https://i.pravatar.cc/150?img=11'],
                    ['name' => 'Emma Davis', 'role' => 'Studio Manager', 'content' => 'The best software decision we ever made. The UI is beautiful and our members love the app.', 'avatar_url' => 'https://i.pravatar.cc/150?img=5'],
                ],

                'faqs_title' => 'Frequently Asked Questions',
                'faqs' => [
                    ['question' => 'Do you offer a free trial?', 'answer' => 'Yes! You can try all features completely free for 14 days. No credit card required.'],
                    ['question' => 'Can I import my existing members?', 'answer' => 'Absolutely. We offer a free CSV import tool and white-glove onboarding for premium plans.'],
                    ['question' => 'What payment gateways do you support?', 'answer' => 'We currently support Stripe, Razorpay, and direct bank transfers.'],
                    ['question' => 'Is there a limit on how many members I can add?', 'answer' => 'No, our platform scales with you. Pricing is based on active members, but there are no hard caps.'],
                ],

                'cta_title' => 'Ready to level up your gym?',
                'cta_text' => 'Join thousands of gym owners who are growing their business faster and working smarter.',
                'cta_button_text' => 'Get Started for Free',
                'cta_button_url' => '/register',

                'seo_title' => 'Gym SaaS - Fitness Management Software',
                'seo_description' => 'The best software to manage your gym, trainers, and memberships all in one place.',
                'footer_text' => 'Transforming fitness businesses worldwide.',
            ],
        ];
    }

    public function getAllSettings(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $settings = PlatformSetting::all()->keyBy('group');
            $defaults = $this->getDefaultSettings();
            $result = [];

            foreach ($defaults as $group => $defaultValues) {
                $saved = $settings->has($group) ? $settings->get($group)->payload : [];
                $result[$group] = array_merge($defaultValues, $saved);
            }

            return $result;
        });
    }

    public function updateSettings(string $group, array $payload): void
    {
        $defaults = $this->getDefaultSettings();
        
        if (!array_key_exists($group, $defaults)) {
            throw new \InvalidArgumentException("Invalid settings group: {$group}");
        }

        $setting = PlatformSetting::firstOrNew(['group' => $group, 'key' => $group]);
        
        // Merge with existing payload to not lose keys if partial update
        $existing = $setting->payload ?? [];
        $setting->payload = array_merge($existing, $payload);
        
        $setting->save();

        Cache::forget(self::CACHE_KEY);
    }
}

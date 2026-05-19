<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('website_enabled')->default(false);
            $table->string('website_template')->default('modern');
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('custom_domain')->nullable()->unique();
            $table->boolean('custom_domain_verified')->default(false);
            $table->json('opening_hours')->nullable();
            $table->json('social_links')->nullable();
            $table->string('banner_image')->nullable();
            $table->json('trainers_data')->nullable();
            $table->json('pricing_plans')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'website_enabled',
                'website_template',
                'seo_title',
                'seo_description',
                'seo_keywords',
                'custom_domain',
                'custom_domain_verified',
                'opening_hours',
                'social_links',
                'banner_image',
                'trainers_data',
                'pricing_plans'
            ]);
        });
    }
};

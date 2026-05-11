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
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_subscriptions', 'renewal_date')) {
                $table->date('renewal_date')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('tenant_subscriptions', 'next_billing_date')) {
                $table->date('next_billing_date')->nullable()->after('renewal_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_subscriptions', 'renewal_date')) {
                $table->dropColumn('renewal_date');
            }
            if (Schema::hasColumn('tenant_subscriptions', 'next_billing_date')) {
                $table->dropColumn('next_billing_date');
            }
        });
    }
};
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
            // Update status enum to include all subscription states
            $table->enum('status', ['active', 'expired', 'cancelled', 'suspended', 'paused', 'trial'])->default('active')->change();
            
            // Add lifecycle fields
            $table->date('renewal_date')->nullable()->after('end_date');
            $table->date('next_billing_date')->nullable()->after('renewal_date');
            $table->timestamp('grace_period_ends_at')->nullable()->after('next_billing_date');
            $table->timestamp('paused_at')->nullable()->after('grace_period_ends_at');
            $table->timestamp('resumed_at')->nullable()->after('paused_at');
            
            // Add indexes for performance
            $table->index(['status', 'end_date']);
            $table->index(['tenant_id', 'status', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'end_date']);
            $table->dropIndex(['tenant_id', 'status', 'end_date']);
            
            $table->dropColumn(['renewal_date', 'next_billing_date', 'grace_period_ends_at', 'paused_at', 'resumed_at']);
            
            // Revert status enum
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active')->change();
        });
    }
};

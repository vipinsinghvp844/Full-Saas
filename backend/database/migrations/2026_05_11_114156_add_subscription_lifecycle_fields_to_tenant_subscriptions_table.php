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
            if (Schema::hasColumn('tenant_subscriptions', 'status') && DB::getDriverName() !== 'pgsql') {
                $table->enum('status', ['active', 'expired', 'cancelled', 'suspended', 'paused', 'trial'])->default('active')->change();
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'renewal_date')) {
                $table->date('renewal_date')->nullable()->after('end_date');
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'next_billing_date')) {
                $table->date('next_billing_date')->nullable()->after('renewal_date');
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'grace_period_ends_at')) {
                $table->timestamp('grace_period_ends_at')->nullable()->after('next_billing_date');
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('grace_period_ends_at');
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'resumed_at')) {
                $table->timestamp('resumed_at')->nullable()->after('paused_at');
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'status')) {
                $table->index(['status', 'end_date']);
                $table->index(['tenant_id', 'status', 'end_date']);
            }
        });

        if (Schema::hasColumn('tenant_subscriptions', 'status') && DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tenant_subscriptions DROP CONSTRAINT IF EXISTS tenant_subscriptions_status_check");
            DB::statement("ALTER TABLE tenant_subscriptions ADD CONSTRAINT tenant_subscriptions_status_check CHECK (status::text = ANY (ARRAY['active'::text, 'expired'::text, 'cancelled'::text, 'suspended'::text, 'paused'::text, 'trial'::text]))");
        }
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
            
            // Revert status enum for non-pgsql
            if (DB::getDriverName() !== 'pgsql') {
                $table->enum('status', ['active', 'expired', 'cancelled'])->default('active')->change();
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tenant_subscriptions DROP CONSTRAINT IF EXISTS tenant_subscriptions_status_check");
            DB::statement("ALTER TABLE tenant_subscriptions ADD CONSTRAINT tenant_subscriptions_status_check CHECK (status::text = ANY (ARRAY['active'::text, 'expired'::text, 'cancelled'::text]))");
        }
    }
};

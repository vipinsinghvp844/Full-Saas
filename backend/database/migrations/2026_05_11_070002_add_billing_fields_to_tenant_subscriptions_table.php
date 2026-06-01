<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_subscriptions', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('plan_id')->constrained('coupons')->nullOnDelete();
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'price')) {
                $table->decimal('price', 10, 2)->default(0)->after('status');
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('price');
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'final_amount')) {
                $table->decimal('final_amount', 10, 2)->default(0)->after('discount_amount');
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'payment_method')) {
                $table->string('payment_method')->default('manual')->after('final_amount');
            }

            if (!Schema::hasColumn('tenant_subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('payment_method');
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE tenant_subscriptions
                SET price = pp.price, discount_amount = 0, final_amount = pp.price
                FROM platform_plans pp
                WHERE pp.id = tenant_subscriptions.plan_id
            ');
        } else {
            DB::statement('
                UPDATE tenant_subscriptions ts
                INNER JOIN platform_plans pp ON pp.id = ts.plan_id
                SET ts.price = pp.price, ts.discount_amount = 0, ts.final_amount = pp.price
            ');
        }
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn(['price', 'discount_amount', 'final_amount', 'payment_method', 'cancelled_at']);
        });
    }
};

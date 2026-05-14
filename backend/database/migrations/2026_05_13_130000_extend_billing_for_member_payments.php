<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('id')->unique();
            }

            if (! Schema::hasColumn('invoices', 'member_id')) {
                $table->foreignId('member_id')->nullable()->after('tenant_id')->constrained('members')->nullOnDelete();
            }

            if (! Schema::hasColumn('invoices', 'membership_id')) {
                $table->foreignId('membership_id')->nullable()->after('member_id')->constrained('member_memberships')->nullOnDelete();
            }

            if (! Schema::hasColumn('invoices', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('invoices', 'discount')) {
                $table->decimal('discount', 12, 2)->default(0)->after('total_amount');
            }

            if (! Schema::hasColumn('invoices', 'final_amount')) {
                $table->decimal('final_amount', 12, 2)->nullable()->after('discount');
            }

            $table->index(['tenant_id', 'member_id']);
            $table->index(['tenant_id', 'membership_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices MODIFY subscription_id BIGINT UNSIGNED NULL');
            DB::statement("ALTER TABLE invoices MODIFY status ENUM('pending', 'paid', 'unpaid', 'overdue', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('payments', 'member_id')) {
                $table->foreignId('member_id')->nullable()->after('tenant_id')->constrained('members')->nullOnDelete();
            }

            if (! Schema::hasColumn('payments', 'membership_id')) {
                $table->foreignId('membership_id')->nullable()->after('member_id')->constrained('member_memberships')->nullOnDelete();
            }

            if (! Schema::hasColumn('payments', 'discount')) {
                $table->decimal('discount', 12, 2)->default(0)->after('amount');
            }

            if (! Schema::hasColumn('payments', 'final_amount')) {
                $table->decimal('final_amount', 12, 2)->nullable()->after('discount');
            }

            if (! Schema::hasColumn('payments', 'payment_status')) {
                $table->enum('payment_status', ['paid', 'pending', 'failed'])->default('pending')->after('status');
            }

            if (! Schema::hasColumn('payments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('payments', 'notes')) {
                $table->text('notes')->nullable()->after('paid_at');
            }

            $table->index(['tenant_id', 'payment_status']);
            $table->index(['tenant_id', 'payment_method']);
            $table->index(['tenant_id', 'paid_at']);
            $table->index(['tenant_id', 'member_id']);
        });

        DB::table('invoices')
            ->whereNull('total_amount')
            ->update([
                'total_amount' => DB::raw('amount'),
                'final_amount' => DB::raw('amount'),
            ]);

        DB::table('invoices')
            ->whereNull('invoice_number')
            ->orderBy('id')
            ->chunkById(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    DB::table('invoices')
                        ->where('id', $invoice->id)
                        ->update(['invoice_number' => sprintf('INV-%s-%06d', now()->format('Y'), $invoice->id)]);
                }
            });

        DB::table('payments')
            ->whereNull('final_amount')
            ->update([
                'discount' => 0,
                'final_amount' => DB::raw('amount'),
                'payment_status' => DB::raw("CASE WHEN status = 'completed' THEN 'paid' WHEN status = 'failed' THEN 'failed' ELSE 'pending' END"),
                'paid_at' => DB::raw("CASE WHEN status = 'completed' THEN created_at ELSE NULL END"),
            ]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement('
                UPDATE payments
                INNER JOIN invoices ON invoices.id = payments.invoice_id
                SET payments.tenant_id = invoices.tenant_id
                WHERE payments.tenant_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'member_id')) {
            DB::table('payments')->whereNotNull('member_id')->delete();
        }

        if (Schema::hasColumn('invoices', 'member_id')) {
            DB::table('invoices')->whereNotNull('member_id')->delete();
        }

        Schema::table('payments', function (Blueprint $table) {
            foreach ([
                ['tenant_id', 'payment_status'],
                ['tenant_id', 'payment_method'],
                ['tenant_id', 'paid_at'],
                ['tenant_id', 'member_id'],
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable) {
                    //
                }
            }

            foreach (['member_id', 'membership_id', 'tenant_id'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['discount', 'final_amount', 'payment_status', 'paid_at', 'notes'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            foreach ([
                ['tenant_id', 'member_id'],
                ['tenant_id', 'membership_id'],
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (Throwable) {
                    //
                }
            }

            foreach (['member_id', 'membership_id'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['invoice_number', 'total_amount', 'discount', 'final_amount'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE invoices MODIFY subscription_id BIGINT UNSIGNED NOT NULL');
            DB::statement("ALTER TABLE invoices MODIFY status ENUM('pending', 'paid', 'overdue', 'cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'month')) {
                $table->unsignedTinyInteger('month')->after('employee_id');
            }
            if (!Schema::hasColumn('payrolls', 'year')) {
                $table->unsignedSmallInteger('year')->after('month');
            }
            if (!Schema::hasColumn('payrolls', 'base_salary')) {
                $table->decimal('base_salary', 10, 2)->default(0)->after('year');
            }
            if (!Schema::hasColumn('payrolls', 'bonuses')) {
                $table->decimal('bonuses', 10, 2)->default(0)->after('base_salary');
            }
            if (!Schema::hasColumn('payrolls', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payrolls', 'notes')) {
                $table->text('notes')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('payrolls', 'expense_id')) {
                $table->unsignedBigInteger('expense_id')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['month', 'year', 'base_salary', 'bonuses', 'paid_at', 'notes', 'expense_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'payment_method')) {
                $table->enum('payment_method', ['cash', 'bank', 'UPI'])->default('cash')->after('expense_date');
            }
            if (!Schema::hasColumn('expenses', 'recorded_by')) {
                $table->unsignedBigInteger('recorded_by')->nullable()->after('payment_method');
                $table->foreign('recorded_by')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['recorded_by']);
            $table->dropColumn(['payment_method', 'recorded_by']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->enum('plan_type', ['monthly', 'quarterly', 'yearly'])->default('monthly')->after('description');
            $table->decimal('discount', 10, 2)->default(0)->after('duration');
            $table->unsignedInteger('max_members')->nullable()->after('discount');
            $table->unsignedInteger('max_trainers')->nullable()->after('max_members');
            $table->unsignedInteger('max_branches')->nullable()->after('max_trainers');
        });

        DB::table('platform_plans')
            ->where('duration', 12)
            ->update(['plan_type' => 'yearly']);

        DB::table('platform_plans')
            ->where('duration', 3)
            ->update(['plan_type' => 'quarterly']);
    }

    public function down(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->dropColumn(['plan_type', 'discount', 'max_members', 'max_trainers', 'max_branches']);
        });
    }
};

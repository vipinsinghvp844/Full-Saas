<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend tenants with more profile fields if not present
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'website')) {
                $table->string('website')->nullable()->after('email');
            }
            if (!Schema::hasColumn('tenants', 'zip')) {
                $table->string('zip', 20)->nullable()->after('city');
            }
            if (!Schema::hasColumn('tenants', 'description')) {
                $table->text('description')->nullable()->after('zip');
            }
        });

        // Extend users with phone and active flag
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['website', 'zip', 'description']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'is_active']);
        });
    }
};

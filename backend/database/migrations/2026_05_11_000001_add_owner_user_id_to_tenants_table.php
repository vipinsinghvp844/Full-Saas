<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'owner_user_id')) {
                $table->foreignId('owner_user_id')
                    ->nullable()
                    ->after('email')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'owner_user_id')) {
                $table->dropForeign(['owner_user_id']);
                $table->dropColumn('owner_user_id');
            }
        });
    }
};

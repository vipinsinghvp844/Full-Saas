<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'logo_path')) {
                $table->string('logo_path')->nullable()->after('name');
            }

            if (!Schema::hasColumn('tenants', 'owner_user_id')) {
                $table->foreignId('owner_user_id')->nullable()->after('email')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('tenants', 'city')) {
                $table->string('city')->nullable()->after('address');
            }

            if (!Schema::hasColumn('tenants', 'state')) {
                $table->string('state')->nullable()->after('city');
            }

            if (!Schema::hasColumn('tenants', 'country')) {
                $table->string('country')->nullable()->after('state');
            }

            if (!Schema::hasColumn('tenants', 'gst_number')) {
                $table->string('gst_number')->nullable()->after('country');
            }

            if (!Schema::hasColumn('tenants', 'country') || !Schema::hasColumn('tenants', 'state')) {
                $table->index(['country', 'state']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropIndex(['country', 'state']);
            $table->dropColumn(['logo_path', 'city', 'state', 'country', 'gst_number']);
        });
    }
};

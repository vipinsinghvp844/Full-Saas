<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
            $table->foreignId('owner_user_id')->nullable()->after('email')->constrained('users')->nullOnDelete();
            $table->string('city')->nullable()->after('address');
            $table->string('state')->nullable()->after('city');
            $table->string('country')->nullable()->after('state');
            $table->string('gst_number')->nullable()->after('country');

            $table->index(['country', 'state']);
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

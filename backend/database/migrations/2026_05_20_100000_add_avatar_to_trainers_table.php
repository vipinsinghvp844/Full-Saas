<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trainers')) {
            return;
        }

        if (! Schema::hasColumn('trainers', 'avatar')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->string('avatar')->nullable()->after('bio');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trainers', 'avatar')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->dropColumn('avatar');
            });
        }
    }
};

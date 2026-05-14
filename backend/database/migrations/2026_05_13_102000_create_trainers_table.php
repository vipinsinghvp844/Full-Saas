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

        if (! Schema::hasColumn('trainers', 'employee_id')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->foreignId('employee_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('employees')
                    ->nullOnDelete();
                $table->unique('employee_id');
            });
        }

        if (! Schema::hasColumn('trainers', 'bio')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->text('bio')->nullable()->after('certifications');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('trainers')) {
            return;
        }

        if (Schema::hasColumn('trainers', 'employee_id')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->dropUnique(['employee_id']);
                $table->dropColumn('employee_id');
            });
        }

        if (Schema::hasColumn('trainers', 'bio')) {
            Schema::table('trainers', function (Blueprint $table) {
                $table->dropColumn('bio');
            });
        }
    }
};

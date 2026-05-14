<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trainers')) {
            Schema::table('trainers', function (Blueprint $table) {
                // Required by Trainer CRUD UI (stored on trainers)
                if (!Schema::hasColumn('trainers', 'phone')) {
                    $table->string('phone')->nullable()->after('experience_years');
                }

                if (!Schema::hasColumn('trainers', 'salary')) {
                    $table->decimal('salary', 12, 2)->nullable()->after('phone');
                }

                if (!Schema::hasColumn('trainers', 'shift')) {
                    $table->string('shift')->nullable()->after('salary');
                }

                if (!Schema::hasColumn('trainers', 'status')) {
                    $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('shift');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('trainers')) {
            Schema::table('trainers', function (Blueprint $table) {
                foreach (['phone', 'salary', 'shift', 'status'] as $col) {
                    if (Schema::hasColumn('trainers', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

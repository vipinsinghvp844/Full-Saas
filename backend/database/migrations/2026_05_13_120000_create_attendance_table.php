<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->timestamp('check_in_time');
            $table->timestamp('check_out_time')->nullable();
            $table->date('date');
            $table->enum('status', ['present', 'missed'])->default('present');
            $table->enum('source', ['manual', 'qr', 'biometric'])->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'member_id', 'date']);
            $table->index(['tenant_id', 'date']);
            $table->index(['tenant_id', 'trainer_id', 'date']);
            $table->index(['tenant_id', 'status', 'date']);
            $table->index(['tenant_id', 'source', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};

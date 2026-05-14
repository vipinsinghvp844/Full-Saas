<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update `classes` table
        Schema::table('classes', function (Blueprint $table) {
            $table->renameColumn('max_participants', 'capacity');
            $table->renameColumn('duration_minutes', 'duration');
            $table->enum('status', ['active', 'inactive'])->default('active');
        });

        // 2. Update `class_schedules` table
        Schema::table('class_schedules', function (Blueprint $table) {
            // Support weekly recurring schedules instead of hard dates
            $table->dropColumn('date');
            $table->string('day_of_week')->after('class_id'); // e.g., 'Monday', 'Tuesday'
        });

        // 3. Update `class_bookings` table
        Schema::table('class_bookings', function (Blueprint $table) {
            // Rename to match convention if needed, but let's keep schedule_id and just add booking_date
            // Drop old booked_at timestamp and use booking_date for the specific session instance
            $table->dropColumn('booked_at');
            $table->date('booking_date')->after('schedule_id');
            // 'status' enum already exists: ['booked', 'cancelled', 'attended']
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_bookings', function (Blueprint $table) {
            $table->dropColumn('booking_date');
            $table->timestamp('booked_at')->nullable();
        });

        Schema::table('class_schedules', function (Blueprint $table) {
            $table->dropColumn('day_of_week');
            $table->date('date')->nullable();
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->renameColumn('capacity', 'max_participants');
            $table->renameColumn('duration', 'duration_minutes');
            $table->dropColumn('status');
        });
    }
};

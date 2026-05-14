<?php

namespace App\Services\Gym;

use App\Models\Attendance;
use App\Models\ClassBooking;
use App\Models\ClassSchedule;
use App\Models\GymClass;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Exception;

class ClassService
{
    /**
     * Create a new class with its weekly schedules.
     */
    public function createClass(array $classData, array $schedules, $tenantId, $userId)
    {
        return DB::transaction(function () use ($classData, $schedules, $tenantId, $userId) {
            $classData['tenant_id'] = $tenantId;
            $classData['created_by'] = $userId;
            $classData['updated_by'] = $userId;

            $gymClass = GymClass::create($classData);

            foreach ($schedules as $schedule) {
                $gymClass->schedules()->create([
                    'tenant_id' => $tenantId,
                    'day_of_week' => $schedule['day_of_week'],
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                    'room' => $schedule['room'] ?? null,
                ]);
            }

            return $gymClass->load('schedules');
        });
    }

    /**
     * Book a member into a specific class instance.
     */
    public function bookClass($classId, $scheduleId, $memberId, $bookingDate, $tenantId)
    {
        $gymClass = GymClass::where('tenant_id', $tenantId)->findOrFail($classId);
        $schedule = ClassSchedule::where('tenant_id', $tenantId)->where('class_id', $classId)->findOrFail($scheduleId);

        // Ensure the booking date matches the schedule's day of week
        $date = Carbon::parse($bookingDate);
        if ($date->englishDayOfWeek !== $schedule->day_of_week) {
            throw new Exception("Booking date {$bookingDate} does not match the schedule day ({$schedule->day_of_week}).");
        }

        // Check for duplicates
        $existing = ClassBooking::where('tenant_id', $tenantId)
            ->where('member_id', $memberId)
            ->where('schedule_id', $scheduleId)
            ->whereDate('booking_date', $bookingDate)
            ->whereIn('status', ['booked', 'attended'])
            ->first();

        if ($existing) {
            throw new Exception("Member is already booked for this session.");
        }

        // Enforce Capacity
        $currentBookings = ClassBooking::where('class_id', $classId)
            ->whereDate('booking_date', $bookingDate)
            ->where('status', 'booked')
            ->count();

        if ($gymClass->capacity && $currentBookings >= $gymClass->capacity) {
            throw new Exception("Class capacity reached for this date.");
        }

        return ClassBooking::create([
            'tenant_id' => $tenantId,
            'class_id' => $classId,
            'member_id' => $memberId,
            'schedule_id' => $scheduleId,
            'booking_date' => $bookingDate,
            'status' => 'booked',
        ]);
    }

    /**
     * Mark booking as attended and sync with core Attendance system.
     */
    public function markAttended($bookingId, $tenantId, $userId)
    {
        return DB::transaction(function () use ($bookingId, $tenantId, $userId) {
            $booking = ClassBooking::where('tenant_id', $tenantId)
                ->with(['gymClass', 'schedule'])
                ->findOrFail($bookingId);

            if ($booking->status === 'attended') {
                return $booking; // already marked
            }

            $booking->status = 'attended';
            $booking->save();

            // Sync to core Attendance module
            // This links the member's visit to the trainer running the class
            Attendance::create([
                'tenant_id' => $tenantId,
                'member_id' => $booking->member_id,
                'trainer_id' => $booking->gymClass->trainer_id,
                'date' => $booking->booking_date,
                'check_in_time' => Carbon::parse($booking->booking_date->format('Y-m-d') . ' ' . $booking->schedule->start_time),
                'check_out_time' => Carbon::parse($booking->booking_date->format('Y-m-d') . ' ' . $booking->schedule->end_time),
                'status' => 'present',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return $booking;
        });
    }
}

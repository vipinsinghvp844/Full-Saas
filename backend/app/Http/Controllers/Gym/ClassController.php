<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Models\GymClass;
use App\Models\ClassBooking;
use App\Services\Gym\ClassService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Exception;

class ClassController extends ApiController
{
    public function __construct(protected ClassService $classService)
    {
    }

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $classes = GymClass::where('tenant_id', $tenantId)
            ->with(['trainer.user', 'schedules'])
            ->withCount(['bookings' => function ($query) {
                // Just for display purposes on the table, a total count.
                // In a real view, we'd filter by booking_date
                $query->where('status', 'booked');
            }])
            ->get();

        return $this->jsonResponse(['data' => $classes]);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
            'duration' => 'nullable|integer|min:1',
            'intensity' => 'nullable|string|in:Low,Medium,High',
            'image' => 'nullable|string|max:2048',
            'trainer_id' => [
                'nullable',
                'integer',
                Rule::exists('trainers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'schedules' => 'required|array',
            'schedules.*.day_of_week' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.start_time' => 'required|date_format:H:i',
            'schedules.*.end_time' => 'required|date_format:H:i|after:schedules.*.start_time',
        ]);

        $userId = $request->user()->id;

        $gymClass = $this->classService->createClass(
            $request->except('schedules'),
            $request->input('schedules'),
            $tenantId,
            $userId
        );

        return $this->jsonResponse(['message' => 'Class created successfully', 'data' => $gymClass]);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $userId = $request->user()->id;

        $gymClass = GymClass::where('tenant_id', $tenantId)->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1',
            'duration' => 'nullable|integer|min:1',
            'intensity' => 'nullable|string|in:Low,Medium,High',
            'image' => 'nullable|string|max:2048',
            'trainer_id' => [
                'nullable',
                'integer',
                Rule::exists('trainers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'schedules' => 'sometimes|array|min:1',
            'schedules.*.day_of_week' => 'required_with:schedules|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.start_time' => 'required_with:schedules|date_format:H:i',
            'schedules.*.end_time' => 'required_with:schedules|date_format:H:i|after:schedules.*.start_time',
        ]);

        $gymClass = $this->classService->updateClass(
            $gymClass,
            $request->except('schedules'),
            $request->has('schedules') ? $request->input('schedules') : null,
            $tenantId,
            $userId
        );

        return $this->jsonResponse(['message' => 'Class updated successfully', 'data' => $gymClass]);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;

        $gymClass = GymClass::where('tenant_id', $tenantId)
            ->with(['trainer.user', 'schedules', 'bookings.member.user'])
            ->findOrFail($id);

        return $this->jsonResponse(['data' => $gymClass]);
    }

    public function book(Request $request, $id)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|exists:class_schedules,id',
            'member_id' => 'required|exists:members,id',
            'booking_date' => 'required|date',
        ]);

        try {
            $booking = $this->classService->bookClass(
                $id,
                $validated['schedule_id'],
                $validated['member_id'],
                $validated['booking_date'],
                $request->user()->tenant_id
            );

            return $this->jsonResponse(['message' => 'Class booked successfully', 'data' => $booking]);
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    public function updateBookingStatus(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'status' => 'required|in:attended,cancelled',
        ]);

        $tenantId = $request->user()->tenant_id;

        try {
            if ($validated['status'] === 'attended') {
                $booking = $this->classService->markAttended($bookingId, $tenantId, $request->user()->id);
            } else {
                $booking = ClassBooking::where('tenant_id', $tenantId)->findOrFail($bookingId);
                $booking->update(['status' => 'cancelled']);
            }

            return $this->jsonResponse(['message' => 'Booking status updated']);
        } catch (Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $gymClass = GymClass::where('tenant_id', $tenantId)->findOrFail($id);
        
        // Deleting class will cascade delete schedules and bookings
        $gymClass->delete();

        return $this->jsonResponse(['message' => 'Class deleted successfully']);
    }
}

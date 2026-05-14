<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Http\Resources\Gym\AttendanceResource;
use App\Models\Attendance;
use App\Models\Member;
use App\Models\Trainer;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends ApiController
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'trainer_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['present', 'missed'])],
            'source' => ['nullable', Rule::in(['manual', 'qr', 'biometric'])],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $date = $data['date'] ?? now()->toDateString();
        $perPage = (int) ($data['per_page'] ?? 15);

        $query = $this->attendanceListQuery($tenantId)
            ->whereDate('attendance.date', $date);

        if (! empty($data['trainer_id'])) {
            $query->where('attendance.trainer_id', $data['trainer_id']);
        }

        if (! empty($data['member_id'])) {
            $query->where('attendance.member_id', $data['member_id']);
        }

        if (! empty($data['status'])) {
            $query->where('attendance.status', $data['status']);
        }

        if (! empty($data['source'])) {
            $query->where('attendance.source', $data['source']);
        }

        if (! empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('member_users.name', 'like', "%{$search}%")
                    ->orWhere('member_users.email', 'like', "%{$search}%")
                    ->orWhere('members.phone', 'like', "%{$search}%")
                    ->orWhere('trainer_users.name', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderByDesc('attendance.check_in_time')
            ->paginate($perPage);

        return $this->jsonResponse($this->paginatedData($paginator, AttendanceResource::class), 200, $request);
    }

    public function today(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        return $this->jsonResponse([
            'data' => $this->summaryForDate($tenantId, $data['date'] ?? now()->toDateString()),
        ], 200, $request);
    }

    public function checkIn(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'trainer_id' => ['nullable', 'integer', 'exists:trainers,id'],
            'source' => ['nullable', Rule::in(['manual', 'qr', 'biometric'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $member = Member::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $data['member_id'])
            ->with('assignedTrainer')
            ->firstOrFail();

        if ($member->status !== 'active') {
            return $this->jsonResponse([
                'message' => 'Only active members can check in.',
            ], 422, $request);
        }

        $trainerId = array_key_exists('trainer_id', $data)
            ? $this->tenantTrainerId($tenantId, $data['trainer_id'])
            : $member->assigned_trainer_id;

        $now = now();
        $date = $now->toDateString();
        $duplicate = null;

        try {
            $attendance = DB::transaction(function () use ($tenantId, $member, $trainerId, $data, $now, $date, &$duplicate) {
                $existing = Attendance::query()
                    ->where('tenant_id', $tenantId)
                    ->where('member_id', $member->id)
                    ->whereDate('date', $date)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $duplicate = $existing;
                    return null;
                }

                return Attendance::query()->create([
                    'tenant_id' => $tenantId,
                    'member_id' => $member->id,
                    'trainer_id' => $trainerId,
                    'check_in_time' => $now,
                    'date' => $date,
                    'status' => 'present',
                    'source' => $data['source'] ?? 'manual',
                    'notes' => $data['notes'] ?? null,
                ]);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                return $this->jsonResponse([
                    'message' => 'This member is already checked in today.',
                ], 409, $request);
            }

            throw $exception;
        }

        if ($duplicate) {
            return $this->jsonResponse([
                'message' => 'This member is already checked in today.',
                'data' => (new AttendanceResource($duplicate->load(['member.user', 'trainer.user'])))->resolve(),
            ], 409, $request);
        }

        return $this->jsonResponse([
            'message' => 'Check-in recorded.',
            'data' => (new AttendanceResource($attendance->load(['member.user', 'trainer.user'])))->resolve(),
        ], 201, $request);
    }

    public function checkOut(Request $request, int $attendance)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attendanceModel = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $attendance)
            ->with(['member.user', 'trainer.user'])
            ->firstOrFail();

        if ($attendanceModel->status !== 'present') {
            return $this->jsonResponse([
                'message' => 'Only present attendance records can be checked out.',
            ], 422, $request);
        }

        if ($attendanceModel->check_out_time) {
            return $this->jsonResponse([
                'message' => 'This attendance record is already checked out.',
            ], 422, $request);
        }

        $now = now();

        if ($now->lt($attendanceModel->check_in_time)) {
            return $this->jsonResponse([
                'message' => 'Check-out time cannot be before check-in time.',
            ], 422, $request);
        }

        $attendanceModel->update([
            'check_out_time' => $now,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $attendanceModel->notes,
        ]);

        return $this->jsonResponse([
            'message' => 'Check-out recorded.',
            'data' => (new AttendanceResource($attendanceModel->fresh(['member.user', 'trainer.user'])))->resolve(),
        ], 200, $request);
    }

    protected function attendanceListQuery(int $tenantId)
    {
        return Attendance::query()
            ->select('attendance.*')
            ->join('members', function (JoinClause $join) use ($tenantId) {
                $join
                    ->on('attendance.member_id', '=', 'members.id')
                    ->where('members.tenant_id', '=', $tenantId);
            })
            ->join('users as member_users', 'members.user_id', '=', 'member_users.id')
            ->leftJoin('trainers', function (JoinClause $join) use ($tenantId) {
                $join
                    ->on('attendance.trainer_id', '=', 'trainers.id')
                    ->where('trainers.tenant_id', '=', $tenantId);
            })
            ->leftJoin('users as trainer_users', 'trainers.user_id', '=', 'trainer_users.id')
            ->with(['member.user', 'trainer.user'])
            ->where('attendance.tenant_id', $tenantId);
    }

    protected function summaryForDate(int $tenantId, string $date): array
    {
        $records = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('date', $date)
            ->where('status', 'present')
            ->get(['id', 'member_id', 'check_in_time', 'check_out_time', 'source']);

        $completedVisits = $records->filter(fn (Attendance $record) => $record->check_in_time && $record->check_out_time);
        $averageDuration = $completedVisits->count() > 0
            ? (int) round($completedVisits->avg(fn (Attendance $record) => $record->check_in_time->diffInMinutes($record->check_out_time)))
            : null;

        return [
            'date' => $date,
            'as_of' => now()->toIso8601String(),
            'total_check_ins' => $records->count(),
            'active_members_today' => $records->pluck('member_id')->unique()->count(),
            'currently_inside' => $records->filter(fn (Attendance $record) => ! $record->check_out_time)->count(),
            'avg_visit_duration_minutes' => $averageDuration,
            'source_mix' => [
                'manual' => $records->where('source', 'manual')->count(),
                'qr' => $records->where('source', 'qr')->count(),
                'biometric' => $records->where('source', 'biometric')->count(),
            ],
        ];
    }

    protected function tenantTrainerId(int $tenantId, ?int $trainerId): ?int
    {
        if (! $trainerId) {
            return null;
        }

        return Trainer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $trainerId)
            ->firstOrFail()
            ->id;
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true);
    }
}

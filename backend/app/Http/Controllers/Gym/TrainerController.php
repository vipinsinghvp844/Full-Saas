<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Http\Resources\Gym\TrainerResource;
use App\Models\Member;
use App\Models\Trainer;
use App\Models\TrainerMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class TrainerController extends ApiController
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $perPage  = min(max((int) $request->input('per_page', 15), 1), 100);

        $query = Trainer::query()
            ->with('user')
            ->where('tenant_id', $tenantId);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by name or email (via related user)
        if ($request->filled('q')) {
            $search = '%' . $request->input('q') . '%';
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search);
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->paginatedData($paginator, TrainerResource::class);
    }

    public function show(Request $request, int $trainer)
    {
        $tenantId = $request->user()->tenant_id;

        $trainerModel = Trainer::query()
            ->with('user')
            ->with('trainerMembers')
            ->where('tenant_id', $tenantId)
            ->where('id', $trainer)
            ->firstOrFail();

        return $this->jsonResponse(['data' => (new TrainerResource($trainerModel))->resolve()], 200, $request);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name'        => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'specialization'   => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'certifications'   => ['nullable', 'string'],
            'avatar'           => ['nullable', 'string', 'max:2048'],
            'bio'              => ['nullable', 'string'],
            'salary'           => ['nullable', 'numeric'],
            'shift'            => ['nullable', 'string', 'max:255'],
            'status'           => ['nullable', 'in:active,inactive,suspended'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $existingTrainer = Trainer::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('user', function ($q) use ($data) {
                $q->where('email', $data['email']);
            })
            ->first();

        if ($existingTrainer) {
            return $this->jsonResponse(['message' => 'Trainer email already exists for this gym.'], 422, $request);
        }

        $user = User::create([
            'tenant_id' => $tenantId,
            'name'      => $data['full_name'],
            'email'     => $data['email'],
            'password'  => Hash::make(Str::random(24)),
        ]);

        $branch = \App\Models\Branch::where('tenant_id', $tenantId)->first();
        if (!$branch) {
            return $this->jsonResponse(['message' => 'Please create a branch first before adding trainers.'], 400, $request);
        }

        $employeeModel = \App\Models\Employee::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'role' => 'trainer',
            'branch_id' => $branch->id,
            'phone' => $data['phone'] ?? null,
            'position' => 'Trainer',
            'hire_date' => now()->toDateString(),
            'avatar' => $data['avatar'] ?? null,
            'salary' => $data['salary'] ?? null,
            'shift' => $data['shift'] ?? null,
            'status' => ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $role = \App\Models\Role::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Trainer', 'guard_name' => 'web']
        );
        $user->roles()->attach($role->id, ['tenant_id' => $tenantId]);

        $trainerModel = Trainer::query()->create([
            'tenant_id'        => $tenantId,
            'user_id'          => $user->id,
            'employee_id'      => $employeeModel->id,
            'phone'            => $data['phone'] ?? null,
            'specialization'   => $data['specialization'] ?? null,
            'experience_years' => $data['experience_years'] ?? null,
            'certifications'   => $data['certifications'] ?? null,
            'avatar'           => $data['avatar'] ?? null,
            'bio'              => $data['bio'] ?? null,
            'salary'           => $data['salary'] ?? null,
            'shift'            => $data['shift'] ?? null,
            'status'           => $data['status'] ?? 'active',
            'created_by'       => $request->user()->id,
            'updated_by'       => $request->user()->id,
        ]);

        return $this->jsonResponse(['data' => (new TrainerResource($trainerModel->load('user')))->resolve()], 201, $request);
    }

    public function update(Request $request, int $trainer)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'full_name'        => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:50'],
            'specialization'   => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'certifications'   => ['nullable', 'string'],
            'avatar'           => ['nullable', 'string', 'max:2048'],
            'bio'              => ['nullable', 'string'],
            'salary'           => ['nullable', 'numeric'],
            'shift'            => ['nullable', 'string', 'max:255'],
            'status'           => ['nullable', 'in:active,inactive,suspended'],
        ]);

        $trainerModel = Trainer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $trainer)
            ->with('user')
            ->firstOrFail();

        if (isset($data['full_name']) || isset($data['email'])) {
            $trainerModel->user->update([
                'name'  => $data['full_name'] ?? $trainerModel->user->name,
                'email' => $data['email']     ?? $trainerModel->user->email,
            ]);
        }

        $trainerModel->update([
            'phone'            => $data['phone']            ?? $trainerModel->phone,
            'specialization'   => $data['specialization']   ?? $trainerModel->specialization,
            'experience_years' => $data['experience_years'] ?? $trainerModel->experience_years,
            'certifications'   => $data['certifications']   ?? $trainerModel->certifications,
            'avatar'           => array_key_exists('avatar', $data) ? $data['avatar'] : $trainerModel->avatar,
            'bio'              => array_key_exists('bio', $data)    ? $data['bio']    : $trainerModel->bio,
            'salary'           => $data['salary']            ?? $trainerModel->salary,
            'shift'            => $data['shift']             ?? $trainerModel->shift,
            'status'           => $data['status']            ?? $trainerModel->status,
            'updated_by'       => $request->user()->id,
        ]);

        $branch = \App\Models\Branch::where('tenant_id', $tenantId)->first();
        if ($branch) {
            \App\Models\Employee::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'user_id' => $trainerModel->user_id,
                ],
                [
                    'role' => 'trainer',
                    'branch_id' => $trainerModel->employee?->branch_id ?? $branch->id,
                    'phone' => array_key_exists('phone', $data) ? $data['phone'] : $trainerModel->phone,
                    'position' => $trainerModel->employee?->position ?? 'Trainer',
                    'hire_date' => $trainerModel->employee?->hire_date ?? now()->toDateString(),
                    'avatar' => array_key_exists('avatar', $data) ? $data['avatar'] : $trainerModel->avatar,
                    'salary' => array_key_exists('salary', $data) ? $data['salary'] : $trainerModel->salary,
                    'shift' => array_key_exists('shift', $data) ? $data['shift'] : $trainerModel->shift,
                    'status' => isset($data['status']) ? ($data['status'] === 'active' ? 'active' : 'inactive') : ($trainerModel->status === 'active' ? 'active' : 'inactive'),
                    'updated_by' => $request->user()->id,
                ]
            );
        }

        return $this->jsonResponse(['data' => (new TrainerResource($trainerModel->load('user')))->resolve()], 200, $request);
    }

    public function destroy(Request $request, int $trainer)
    {
        $tenantId = $request->user()->tenant_id;

        $trainerModel = Trainer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $trainer)
            ->with(['employee', 'user'])
            ->firstOrFail();

        DB::transaction(function () use ($trainerModel, $tenantId) {
            Member::query()
                ->where('tenant_id', $tenantId)
                ->where('assigned_trainer_id', $trainerModel->id)
                ->update(['assigned_trainer_id' => null]);

            TrainerMember::query()
                ->where('tenant_id', $tenantId)
                ->where('trainer_id', $trainerModel->id)
                ->delete();

            $employee = $trainerModel->employee;
            $user = $trainerModel->user;

            $trainerModel->delete();

            if ($employee) {
                $employee->delete();
            }

            if ($user) {
                $user->roles()->detach();
                $user->delete();
            }
        });

        return $this->jsonResponse(['message' => 'Trainer deleted.'], 200, $request);
    }

    public function assignMembers(Request $request, int $trainer)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'member_ids'   => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer'],
            'assigned_date' => ['nullable', 'date'],
        ]);

        $trainerModel = Trainer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $trainer)
            ->firstOrFail();

        $memberIds = $data['member_ids'];

        // Tenant isolation for members
        $validMemberIds = Member::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $memberIds)
            ->pluck('id')
            ->all();

        foreach ($validMemberIds as $memberId) {
            Member::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $memberId)
                ->update(['assigned_trainer_id' => $trainerModel->id]);

            TrainerMember::query()->updateOrCreate(
                [
                    'tenant_id'  => $tenantId,
                    'trainer_id' => $trainerModel->id,
                    'member_id'  => $memberId,
                ],
                [
                    'assigned_date' => $data['assigned_date'] ?? now()->toDateString(),
                ]
            );
        }

        return $this->jsonResponse(['message' => 'Members assigned.'], 200, $request);
    }
}

<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Http\Resources\Gym\EmployeeResource;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EmployeeController extends ApiController
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $query = Employee::query()
            ->with(['user.roles', 'branch', 'trainer'])
            ->where('tenant_id', $tenantId);

        // Apply filters
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('position', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('role')) {
            $roleSlug = $this->roleSlug($request->role);

            $query->where(function ($roleQuery) use ($roleSlug) {
                $roleQuery
                    ->whereRaw('LOWER(role) = ?', [$roleSlug])
                    ->orWhereHas('user.roles', function ($userRoleQuery) use ($roleSlug) {
                        $userRoleQuery->whereRaw('LOWER(name) = ?', [$roleSlug]);
                    });
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate(15);

        return $this->paginatedData($paginator, EmployeeResource::class);
    }

    public function show(Request $request, int $employee)
    {
        $tenantId = $request->user()->tenant_id;

        $employeeModel = Employee::query()
            ->with(['user.roles', 'branch', 'trainer'])
            ->where('tenant_id', $tenantId)
            ->where('id', $employee)
            ->firstOrFail();

        return $this->jsonResponse(['data' => (new EmployeeResource($employeeModel))->resolve()], 200, $request);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['required', 'string', 'max:255'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'shift' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['required', 'date'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'role' => ['required', 'string', Rule::in($this->validRoleInputs())],
            'specialization' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'certifications' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive,on_leave,terminated'],
        ]);

        $roleSlug = $this->roleSlug($data['role']);
        $roleName = $this->roleName($roleSlug);

        // Verify branch belongs to tenant
        Branch::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $data['branch_id'])
            ->firstOrFail();

        // Create user account
        $user = User::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(24)),
        ]);

        // Create employee record
        $employeeModel = Employee::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'role' => $roleSlug,
            'branch_id' => $data['branch_id'],
            'phone' => $data['phone'] ?? null,
            'position' => $data['position'],
            'hire_date' => $data['hire_date'],
            'avatar' => $data['avatar'] ?? null,
            'salary' => $data['salary'] ?? null,
            'shift' => $data['shift'] ?? null,
            'status' => $data['status'],
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        // Assign role
        $role = $this->ensureRole($tenantId, $roleName);

        $user->roles()->attach($role->id, ['tenant_id' => $tenantId]);

        if ($roleSlug === 'trainer') {
            $this->syncTrainerProfile($employeeModel, $data, $tenantId, $request->user()->id);
        }

        return $this->jsonResponse(
            ['data' => (new EmployeeResource($employeeModel->load(['user.roles', 'branch', 'trainer'])))->resolve()],
            201,
            $request
        );
    }

    public function update(Request $request, int $employee)
    {
        $tenantId = $request->user()->tenant_id;

        $employeeModel = Employee::query()
            ->with(['user.roles', 'trainer'])
            ->where('tenant_id', $tenantId)
            ->where('id', $employee)
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employeeModel->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'shift' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'role' => ['nullable', 'string', Rule::in($this->validRoleInputs())],
            'specialization' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'certifications' => ['nullable', 'string'],
            'bio' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive,on_leave,terminated'],
        ]);

        $roleSlug = isset($data['role'])
            ? $this->roleSlug($data['role'])
            : ($employeeModel->role ?: $this->roleSlug($employeeModel->user->roles->first()?->name));

        // Verify branch belongs to tenant if provided
        if (isset($data['branch_id'])) {
            Branch::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $data['branch_id'])
                ->firstOrFail();
        }

        // Update user if name or email changed
        $userUpdates = [];
        if (isset($data['name'])) {
            $userUpdates['name'] = $data['name'];
        }
        if (isset($data['email'])) {
            $userUpdates['email'] = $data['email'];
        }
        if (!empty($userUpdates)) {
            $employeeModel->user->update($userUpdates);
        }

        // Update role if changed
        if (isset($data['role'])) {
            $role = $this->ensureRole($tenantId, $this->roleName($roleSlug));

            $employeeModel->user->roles()->sync([$role->id => ['tenant_id' => $tenantId]]);
        }

        // Update employee
        $employeeModel->update([
            'phone' => $data['phone'] ?? $employeeModel->phone,
            'position' => $data['position'] ?? $employeeModel->position,
            'hire_date' => $data['hire_date'] ?? $employeeModel->hire_date,
            'avatar' => array_key_exists('avatar', $data) ? $data['avatar'] : $employeeModel->avatar,
            'salary' => $data['salary'] ?? $employeeModel->salary,
            'shift' => $data['shift'] ?? $employeeModel->shift,
            'branch_id' => $data['branch_id'] ?? $employeeModel->branch_id,
            'role' => $roleSlug,
            'status' => $data['status'] ?? $employeeModel->status,
            'updated_by' => $request->user()->id,
        ]);

        if ($roleSlug === 'trainer') {
            $this->syncTrainerProfile($employeeModel->fresh(), $data, $tenantId, $request->user()->id);
        }

        return $this->jsonResponse(
            ['data' => (new EmployeeResource($employeeModel->fresh(['user.roles', 'branch', 'trainer'])))->resolve()],
            200,
            $request
        );
    }

    protected function roleMap(): array
    {
        return [
            'manager' => 'Manager',
            'trainer' => 'Trainer',
            'receptionist' => 'Receptionist',
            'accountant' => 'Accountant',
        ];
    }

    protected function validRoleInputs(): array
    {
        return array_merge(array_keys($this->roleMap()), array_values($this->roleMap()));
    }

    protected function roleSlug(?string $role): string
    {
        return strtolower(trim((string) $role));
    }

    protected function roleName(string $roleSlug): string
    {
        return $this->roleMap()[$roleSlug] ?? $roleSlug;
    }

    protected function ensureRole(int $tenantId, string $roleName): Role
    {
        return Role::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => $roleName,
                'guard_name' => 'web',
            ],
            [
                'tenant_id' => $tenantId,
                'name' => $roleName,
                'guard_name' => 'web',
            ]
        );
    }

    protected function syncTrainerProfile(Employee $employee, array $data, int $tenantId, int $actorId): void
    {
        $employee->loadMissing('trainer');

        Trainer::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $employee->user_id,
            ],
            [
                'employee_id' => $employee->id,
                'avatar' => array_key_exists('avatar', $data) ? $data['avatar'] : $employee->avatar,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $employee->phone,
                'specialization' => array_key_exists('specialization', $data) ? $data['specialization'] : $employee->trainer?->specialization,
                'experience_years' => array_key_exists('experience_years', $data) ? $data['experience_years'] : $employee->trainer?->experience_years,
                'certifications' => array_key_exists('certifications', $data) ? $data['certifications'] : $employee->trainer?->certifications,
                'bio' => array_key_exists('bio', $data) ? $data['bio'] : $employee->trainer?->bio,
                'salary' => array_key_exists('salary', $data) ? $data['salary'] : $employee->salary,
                'shift' => array_key_exists('shift', $data) ? $data['shift'] : $employee->shift,
                'status' => $this->trainerStatus($data['status'] ?? $employee->status),
                'created_by' => $employee->trainer?->created_by ?? $actorId,
                'updated_by' => $actorId,
            ]
        );
    }

    protected function trainerStatus(?string $employeeStatus): string
    {
        return $employeeStatus === 'active' ? 'active' : 'inactive';
    }

    public function destroy(Request $request, int $employee)
    {
        $tenantId = $request->user()->tenant_id;

        $employeeModel = Employee::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->where('id', $employee)
            ->firstOrFail();

        // Soft delete employee and associated user login
        $employeeModel->delete();
        $employeeModel->user->delete();

        return $this->jsonResponse(['message' => 'Employee deactivated successfully'], 200, $request);
    }
}

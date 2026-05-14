<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Http\Resources\Gym\TrainerResource;
use App\Models\Member;
use App\Models\Trainer;
use App\Models\TrainerMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class TrainerController extends ApiController
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $paginator = Trainer::query()
            ->with('user')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate($perPage);

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
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'certifications' => ['nullable', 'string'],
            'salary' => ['nullable', 'numeric'],
            'shift' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
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
            'name' => $data['full_name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(24)),
        ]);

        $trainerModel = Trainer::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'phone' => $data['phone'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'experience_years' => $data['experience_years'] ?? null,
            'certifications' => $data['certifications'] ?? null,
            'salary' => $data['salary'] ?? null,
            'shift' => $data['shift'] ?? null,
            'status' => $data['status'] ?? 'active',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->jsonResponse(['data' => (new TrainerResource($trainerModel->load('user')))->resolve()], 201, $request);
    }

    public function update(Request $request, int $trainer)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'certifications' => ['nullable', 'string'],
            'salary' => ['nullable', 'numeric'],
            'shift' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
        ]);

        $trainerModel = Trainer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $trainer)
            ->with('user')
            ->firstOrFail();

        if (isset($data['full_name']) || isset($data['email'])) {
            $trainerModel->user->update([
                'name' => $data['full_name'] ?? $trainerModel->user->name,
                'email' => $data['email'] ?? $trainerModel->user->email,
            ]);
        }

        $trainerModel->update([
            'phone' => $data['phone'] ?? $trainerModel->phone,
            'specialization' => $data['specialization'] ?? $trainerModel->specialization,
            'experience_years' => $data['experience_years'] ?? $trainerModel->experience_years,
            'certifications' => $data['certifications'] ?? $trainerModel->certifications,
            'salary' => $data['salary'] ?? $trainerModel->salary,
            'shift' => $data['shift'] ?? $trainerModel->shift,
            'status' => $data['status'] ?? $trainerModel->status,
            'updated_by' => $request->user()->id,
        ]);

        return $this->jsonResponse(['data' => (new TrainerResource($trainerModel->load('user')))->resolve()], 200, $request);
    }

    public function destroy(Request $request, int $trainer)
    {
        $tenantId = $request->user()->tenant_id;

        $trainerModel = Trainer::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $trainer)
            ->firstOrFail();

        $trainerModel->delete();

        return $this->jsonResponse(['message' => 'Trainer deleted.'], 200, $request);
    }

    public function assignMembers(Request $request, int $trainer)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
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

        // Only insert for members that belong to this tenant.
        foreach ($validMemberIds as $memberId) {
            Member::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $memberId)
                ->update(['assigned_trainer_id' => $trainerModel->id]);

            TrainerMember::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'trainer_id' => $trainerModel->id,
                    'member_id' => $memberId,
                ],
                [
                    'assigned_date' => $data['assigned_date'] ?? now()->toDateString(),
                ]
            );
        }

        return $this->jsonResponse(['message' => 'Members assigned.'], 200, $request);
    }
}

<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Http\Resources\Gym\MemberResource;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPlan;
use App\Models\Trainer;
use App\Models\User;
use App\Services\Gym\BillingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MemberController extends ApiController
{
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $query = Member::query()
            ->with(['user', 'activeMembership.plan', 'assignedTrainer.user'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('plan_id')) {
            $query->whereHas('activeMembership', function ($membershipQuery) use ($request) {
                $membershipQuery->where('plan_id', $request->integer('plan_id'));
            });
        }

        if ($request->filled('assigned_trainer_id')) {
            $query->where('assigned_trainer_id', $request->integer('assigned_trainer_id'));
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->paginatedData($paginator, MemberResource::class);
    }

    public function show(Request $request, int $member)
    {
        $tenantId = $request->user()->tenant_id;

        $memberModel = $this->memberQuery($tenantId)
            ->where('id', $member)
            ->firstOrFail();

        return $this->jsonResponse(['data' => (new MemberResource($memberModel))->resolve()], 200, $request);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:50'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'joining_date' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive,suspended'],
            'membership_plan_id' => [
                'nullable',
                'integer',
                Rule::exists('membership_plans', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'assigned_trainer_id' => [
                'nullable',
                'integer',
                Rule::exists('trainers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'payment_status' => ['nullable', 'in:pending,paid,failed,refunded'],
            'final_amount' => ['nullable', 'numeric', 'min:0'],
            'profile_picture' => ['nullable', 'string', 'max:500'],
        ]);

        $plan = $this->tenantPlan($tenantId, $data['membership_plan_id'] ?? null);
        $trainerId = $this->tenantTrainerId($tenantId, $data['assigned_trainer_id'] ?? null);

        $memberModel = DB::transaction(function () use ($data, $tenantId, $plan, $trainerId, $request) {
            $user = User::query()->create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(Str::random(24)),
            ]);

            $member = Member::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'assigned_trainer_id' => $trainerId,
                'phone' => $data['phone'],
                'gender' => $data['gender'] ?? null,
                'date_of_birth' => $data['dob'] ?? null,
                'emergency_contact' => $data['emergency_contact'] ?? null,
                'address' => $data['address'] ?? null,
                'joining_date' => $data['joining_date'],
                'status' => $data['status'],
                'profile_picture' => $data['profile_picture'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            if ($plan) {
                $this->createMembership(
                    $member,
                    $plan,
                    $data['joining_date'],
                    $data['payment_status'] ?? 'paid',
                    $data['final_amount'] ?? null
                );
            }

            return $member;
        });

        return $this->jsonResponse(
            ['data' => (new MemberResource($this->memberQuery($tenantId)->findOrFail($memberModel->id)))->resolve()],
            201,
            $request
        );
    }

    public function update(Request $request, int $member)
    {
        $tenantId = $request->user()->tenant_id;

        $memberModel = $this->memberQuery($tenantId)
            ->where('id', $member)
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($memberModel->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:male,female,other'],
            'dob' => ['nullable', 'date'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'joining_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'membership_plan_id' => [
                'nullable',
                'integer',
                Rule::exists('membership_plans', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'assigned_trainer_id' => [
                'nullable',
                'integer',
                Rule::exists('trainers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'payment_status' => ['nullable', 'in:pending,paid,failed,refunded'],
            'final_amount' => ['nullable', 'numeric', 'min:0'],
            'profile_picture' => ['nullable', 'string', 'max:500'],
        ]);

        $plan = $this->tenantPlan($tenantId, $data['membership_plan_id'] ?? null);
        $trainerId = array_key_exists('assigned_trainer_id', $data)
            ? $this->tenantTrainerId($tenantId, $data['assigned_trainer_id'])
            : $memberModel->assigned_trainer_id;

        DB::transaction(function () use ($data, $memberModel, $plan, $trainerId, $request) {
            $userUpdates = [];

            if (array_key_exists('name', $data)) {
                $userUpdates['name'] = $data['name'];
            }

            if (array_key_exists('email', $data)) {
                $userUpdates['email'] = $data['email'];
            }

            if ($userUpdates) {
                $memberModel->user->update($userUpdates);
            }

            $memberModel->update([
                'assigned_trainer_id' => $trainerId,
                'phone' => $data['phone'] ?? $memberModel->phone,
                'gender' => array_key_exists('gender', $data) ? $data['gender'] : $memberModel->gender,
                'date_of_birth' => array_key_exists('dob', $data) ? $data['dob'] : $memberModel->date_of_birth,
                'emergency_contact' => array_key_exists('emergency_contact', $data) ? $data['emergency_contact'] : $memberModel->emergency_contact,
                'address' => array_key_exists('address', $data) ? $data['address'] : $memberModel->address,
                'joining_date' => $data['joining_date'] ?? $memberModel->joining_date,
                'status' => $data['status'] ?? $memberModel->status,
                'profile_picture' => array_key_exists('profile_picture', $data) ? $data['profile_picture'] : $memberModel->profile_picture,
                'updated_by' => $request->user()->id,
            ]);

            if (array_key_exists('membership_plan_id', $data)) {
                $this->replaceActiveMembership(
                    $memberModel->fresh('activeMembership'),
                    $plan,
                    $data['joining_date'] ?? now()->toDateString(),
                    $data['payment_status'] ?? 'paid',
                    $data['final_amount'] ?? null
                );
            }
        });

        return $this->jsonResponse(
            ['data' => (new MemberResource($this->memberQuery($tenantId)->findOrFail($member)))->resolve()],
            200,
            $request
        );
    }

    public function destroy(Request $request, int $member)
    {
        $tenantId = $request->user()->tenant_id;

        $memberModel = Member::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $member)
            ->firstOrFail();

        $memberModel->delete();

        return $this->jsonResponse(['message' => 'Member deleted.'], 200, $request);
    }

    public function suspend(Request $request, int $member)
    {
        $tenantId = $request->user()->tenant_id;

        $memberModel = Member::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $member)
            ->firstOrFail();

        $memberModel->update([
            'status' => 'suspended',
            'updated_by' => $request->user()->id,
        ]);

        return $this->jsonResponse(['message' => 'Member suspended.'], 200, $request);
    }

    public function renew(Request $request, int $member)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'start_date' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'in:pending,paid,failed,refunded'],
            'final_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $memberModel = Member::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $member)
            ->firstOrFail();

        $plan = $this->tenantPlan($tenantId, $data['membership_plan_id']);

        DB::transaction(function () use ($memberModel, $plan, $data, $request) {
            $this->replaceActiveMembership(
                $memberModel->fresh('activeMembership'),
                $plan,
                $data['start_date'] ?? now()->toDateString(),
                $data['payment_status'] ?? 'paid',
                $data['final_amount'] ?? null
            );

            $memberModel->update([
                'status' => 'active',
                'updated_by' => $request->user()->id,
            ]);
        });

        return $this->jsonResponse(['message' => 'Membership renewed.'], 200, $request);
    }

    protected function memberQuery(int $tenantId)
    {
        return Member::query()
            ->with(['user', 'activeMembership.plan', 'memberships.plan', 'assignedTrainer.user'])
            ->where('tenant_id', $tenantId);
    }

    protected function tenantPlan(int $tenantId, ?int $planId): ?MembershipPlan
    {
        if (! $planId) {
            return null;
        }

        return MembershipPlan::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $planId)
            ->firstOrFail();
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

    protected function replaceActiveMembership(
        Member $member,
        ?MembershipPlan $plan,
        string $startDate,
        string $paymentStatus,
        ?float $finalAmount
    ): void {
        $currentMembership = $member->activeMembership;

        if ($currentMembership && (! $plan || (int) $currentMembership->plan_id !== (int) $plan->id)) {
            $currentMembership->update(['status' => 'cancelled']);
        }

        if (! $plan) {
            return;
        }

        if ($currentMembership && (int) $currentMembership->plan_id === (int) $plan->id) {
            $currentMembership->update([
                'payment_status' => $paymentStatus,
                'final_amount' => $finalAmount ?? $plan->price,
            ]);

            return;
        }

        $this->createMembership($member, $plan, $startDate, $paymentStatus, $finalAmount);
    }

    protected function createMembership(
        Member $member,
        MembershipPlan $plan,
        string $startDate,
        string $paymentStatus,
        ?float $finalAmount
    ): MemberMembership {
        $startsAt = Carbon::parse($startDate);

        $membership = MemberMembership::query()->create([
            'tenant_id' => $member->tenant_id,
            'member_id' => $member->id,
            'plan_id' => $plan->id,
            'start_date' => $startsAt->toDateString(),
            'end_date' => $startsAt->copy()->addDays((int) $plan->duration_days)->toDateString(),
            'status' => 'active',
            'payment_status' => $paymentStatus,
            'final_amount' => $finalAmount ?? $plan->price,
        ]);

        app(BillingService::class)->ensureMembershipInvoice($membership);

        return $membership;
    }
}

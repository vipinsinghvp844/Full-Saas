<?php

namespace App\Services\SuperAdmin;

use App\Models\PlatformPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanService
{
    public function __construct(protected ActivityLogService $activityLogService)
    {
    }

    public function create(array $data, User $actor): PlatformPlan
    {
        return DB::transaction(function () use ($data, $actor) {
            $plan = PlatformPlan::create($this->formatPayload($data));

            $this->activityLogService->record(
                $actor,
                'plan.created',
                $plan,
                "Created plan {$plan->name}",
                [],
                $plan->toArray()
            );

            return $plan;
        });
    }

    public function update(PlatformPlan $plan, array $data, User $actor): PlatformPlan
    {
        return DB::transaction(function () use ($plan, $data, $actor) {
            $oldValues = $plan->toArray();
            $plan->update($this->formatPayload($data));

            $this->activityLogService->record(
                $actor,
                'plan.updated',
                $plan,
                "Updated plan {$plan->name}",
                $oldValues,
                $plan->fresh()->toArray()
            );

            return $plan->fresh();
        });
    }

    public function setStatus(PlatformPlan $plan, string $status, User $actor): PlatformPlan
    {
        $oldValues = $plan->toArray();
        $plan->update(['status' => $status]);

        $this->activityLogService->record(
            $actor,
            "plan.{$status}",
            $plan,
            ucfirst($status)." plan {$plan->name}",
            $oldValues,
            $plan->fresh()->toArray()
        );

        return $plan->fresh();
    }

    public function delete(PlatformPlan $plan, User $actor): void
    {
        if ($plan->subscriptions()->exists()) {
            throw ValidationException::withMessages([
                'plan' => 'This plan is already linked to subscriptions and cannot be deleted.',
            ]);
        }

        $snapshot = $plan->toArray();
        $name = $plan->name;
        $plan->delete();

        $this->activityLogService->record(
            $actor,
            'plan.deleted',
            null,
            "Deleted plan {$name}",
            $snapshot,
            []
        );
    }

    protected function formatPayload(array $data): array
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'plan_type' => $data['plan_type'],
            'price' => $data['price'],
            'duration' => $data['duration'],
            'discount' => $data['discount'] ?? 0,
            'max_members' => $data['max_members'] ?? null,
            'max_trainers' => $data['max_trainers'] ?? null,
            'max_branches' => $data['max_branches'] ?? null,
            'features' => $this->normalizeFeatures($data['features'] ?? []),
            'status' => $data['status'] ?? 'active',
        ];
    }

    protected function normalizeFeatures(array|string $features): array
    {
        if (is_string($features)) {
            $features = preg_split('/\r\n|\r|\n|,/', $features) ?: [];
        }

        return collect($features)
            ->map(fn ($feature) => trim((string) $feature))
            ->filter()
            ->values()
            ->all();
    }
}

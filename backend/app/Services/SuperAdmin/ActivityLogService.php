<?php

namespace App\Services\SuperAdmin;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class ActivityLogService
{
    public function record(
        User $actor,
        string $action,
        ?Model $subject,
        string $description,
        array $oldValues = [],
        array $newValues = [],
        ?int $tenantId = null
    ): void {
        ActivityLog::create([
            'tenant_id' => $tenantId ?? $this->resolveTenantId($actor),
            'user_id' => $actor->id,
            'action' => $action,
            'model_type' => $subject ? class_basename($subject) : null,
            'model_id' => $subject?->getKey(),
            'old_values' => Arr::wrap($oldValues),
            'new_values' => Arr::wrap($newValues),
            'description' => $description,
        ]);
    }

    protected function resolveTenantId(User $actor): int
    {
        $pivotTenantId = $actor->roles->first()?->pivot?->tenant_id;

        return (int) ($pivotTenantId ?? $actor->tenant_id ?? 1);
    }
}

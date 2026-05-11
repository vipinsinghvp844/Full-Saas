<?php

namespace App\Services\SuperAdmin;

use App\Models\PlatformPlan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GymService
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected SubscriptionService $subscriptionService
    ) {
    }

    public function create(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor) {
            $temporaryPassword = $this->generateTemporaryPassword();

            $tenant = Tenant::create([
                'name' => $data['name'],
                'logo_path' => $this->storeLogo($data['logo'] ?? null),
                'slug' => $this->generateUniqueSlug($data['name']),
                'email' => $data['owner_email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? null,
                'gst_number' => $data['gst_number'] ?? null,
                'status' => $data['status'],
            ]);

            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => $temporaryPassword,
            ]);

            $tenant->update(['owner_user_id' => $owner->id]);

            $this->provisionTenantRoles($tenant);
            $this->assignGymAdminRole($tenant, $owner);

            if (! empty($data['subscription_plan_id'])) {
                $this->subscriptionService->assignPlan(
                    [
                        'tenant_id' => $tenant->id,
                        'plan_id' => $data['subscription_plan_id'],
                        'start_date' => now()->toDateString(),
                        'payment_method' => 'manual',
                    ],
                    $actor
                );
            }

            $this->activityLogService->record(
                $actor,
                'gym.created',
                $tenant,
                "Created gym {$tenant->name}",
                [],
                $tenant->fresh()->toArray(),
                $tenant->id
            );

            return [$tenant->fresh(), $temporaryPassword];
        });
    }

    public function update(Tenant $tenant, array $data, User $actor): Tenant
    {
        return DB::transaction(function () use ($tenant, $data, $actor) {
            $oldValues = $tenant->toArray();
            $owner = $tenant->owner;

            if (! $owner) {
                throw ValidationException::withMessages([
                    'owner_email' => 'Gym owner account is missing.',
                ]);
            }

            $updateData = [
                'name' => $data['name'],
                'email' => $data['owner_email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? null,
                'gst_number' => $data['gst_number'] ?? null,
                'status' => $data['status'],
            ];

            if (! empty($data['logo'])) {
                $this->deleteLogo($tenant->logo_path);
                $updateData['logo_path'] = $this->storeLogo($data['logo']);
            }

            $tenant->update($updateData);

            $owner->update([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
            ]);

            if (! empty($data['subscription_plan_id'])) {
                $activeSubscription = $tenant->activeSubscription;

                if (! $activeSubscription) {
                    $this->subscriptionService->assignPlan([
                        'tenant_id' => $tenant->id,
                        'plan_id' => $data['subscription_plan_id'],
                        'start_date' => now()->toDateString(),
                        'payment_method' => 'manual',
                    ], $actor);
                } elseif ((int) $activeSubscription->plan_id !== (int) $data['subscription_plan_id']) {
                    $this->subscriptionService->changePlan(
                        $activeSubscription,
                        [
                            'plan_id' => $data['subscription_plan_id'],
                            'payment_method' => 'manual',
                        ],
                        $actor
                    );
                }
            }

            $this->activityLogService->record(
                $actor,
                'gym.updated',
                $tenant,
                "Updated gym {$tenant->name}",
                $oldValues,
                $tenant->fresh()->toArray(),
                $tenant->id
            );

            return $tenant->fresh();
        });
    }

    public function suspend(Tenant $tenant, User $actor): Tenant
    {
        $oldValues = $tenant->toArray();
        $tenant->update(['status' => 'suspended']);

        $this->activityLogService->record(
            $actor,
            'gym.suspended',
            $tenant,
            "Suspended gym {$tenant->name}",
            $oldValues,
            $tenant->fresh()->toArray(),
            $tenant->id
        );

        return $tenant->fresh();
    }

    public function activate(Tenant $tenant, User $actor): Tenant
    {
        $oldValues = $tenant->toArray();
        $tenant->update(['status' => 'active']);

        $this->activityLogService->record(
            $actor,
            'gym.activated',
            $tenant,
            "Activated gym {$tenant->name}",
            $oldValues,
            $tenant->fresh()->toArray(),
            $tenant->id
        );

        return $tenant->fresh();
    }

    public function delete(Tenant $tenant, User $actor): void
    {
        DB::transaction(function () use ($tenant, $actor) {
            $tenantSnapshot = $tenant->toArray();
            $name = $tenant->name;
            $tenantId = $tenant->id;

            $this->deleteLogo($tenant->logo_path);
            $tenant->delete();

            $this->activityLogService->record(
                $actor,
                'gym.deleted',
                null,
                "Deleted gym {$name}",
                $tenantSnapshot,
                [],
                $tenantId
            );
        });
    }

    protected function provisionTenantRoles(Tenant $tenant): void
    {
        foreach (['Gym Admin', 'Manager', 'Trainer', 'Receptionist', 'Accountant'] as $roleName) {
            Role::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $roleName,
                    'guard_name' => 'web',
                ],
                [
                    'tenant_id' => $tenant->id,
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]
            );
        }
    }

    protected function assignGymAdminRole(Tenant $tenant, User $owner): void
    {
        $role = Role::where('tenant_id', $tenant->id)
            ->where('name', 'Gym Admin')
            ->first();

        if ($role) {
            $owner->roles()->syncWithoutDetaching([
                $role->id => ['tenant_id' => $tenant->id],
            ]);
        }
    }

    protected function storeLogo(?UploadedFile $file): ?string
    {
        if (! $file) {
            return null;
        }

        $directory = public_path('uploads/gym-logos');

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return 'uploads/gym-logos/'.$filename;
    }

    protected function deleteLogo(?string $path): void
    {
        if (! $path) {
            return;
        }

        $absolutePath = public_path($path);

        if (File::exists($absolutePath)) {
            File::delete($absolutePath);
        }
    }

    protected function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $baseSlug = $slug !== '' ? $slug : Str::lower(Str::random(8));
        $slug = $baseSlug;
        $counter = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    protected function generateTemporaryPassword(): string
    {
        return Str::password(12, true, true, true, false);
    }
}

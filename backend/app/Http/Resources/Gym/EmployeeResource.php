<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray($request): array
    {
        $roleName = $this->role;
        if (! $roleName && $this->relationLoaded('user') && $this->user?->relationLoaded('roles')) {
            $roleName = $this->user->roles->first()?->name;
        }

        $trainer = $this->relationLoaded('trainer') ? $this->trainer : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user') ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'phone' => $this->phone,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch') ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'address' => $this->branch->address,
                'phone' => $this->branch->phone,
            ] : null,
            'position' => $this->position,
            'hire_date' => optional($this->hire_date)->toDateString(),
            'salary' => $this->salary,
            'shift' => $this->shift,
            'status' => $this->status,
            'role' => $roleName ? strtolower($roleName) : null,
            'specialization' => $trainer?->specialization,
            'experience_years' => $trainer?->experience_years,
            'certifications' => $trainer?->certifications,
            'bio' => $trainer?->bio,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

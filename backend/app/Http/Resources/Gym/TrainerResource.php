<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Resources\Json\JsonResource;

class TrainerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'specialization' => $this->specialization,
            'experience_years' => $this->experience_years,
            'certifications' => $this->certifications,

            'avatar' => $this->avatar,
            'bio' => $this->bio,
            'phone' => $this->phone,
            'salary' => $this->salary,
            'shift' => $this->shift,
            'status' => $this->status,

            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

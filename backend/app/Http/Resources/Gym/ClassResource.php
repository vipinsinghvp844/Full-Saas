<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'max_participants' => $this->max_participants,
            'duration_minutes' => $this->duration_minutes,
            'trainer' => [
                'id' => $this->trainer?->id,
                'name' => $this->trainer?->user?->name,
                'email' => $this->trainer?->user?->email,
            ],
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

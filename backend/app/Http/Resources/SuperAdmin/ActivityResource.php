<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'model_type' => $this->model_type,
            'model_id' => $this->model_id,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant?->id,
                'name' => $this->tenant?->name,
                'status' => $this->tenant?->status,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}

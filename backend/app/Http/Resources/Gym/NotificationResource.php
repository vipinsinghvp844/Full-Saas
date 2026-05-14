<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'category' => $this->category,
            'channel' => $this->channel,
            'priority' => $this->priority,
            'read' => (bool) $this->read,
            'data' => $this->data,
            'notifiable_type' => $this->notifiable_type ? class_basename($this->notifiable_type) : null,
            'notifiable_id' => $this->notifiable_id,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'time_ago' => $this->created_at?->diffForHumans(),
        ];
    }
}

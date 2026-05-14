<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray($request): array
    {
        $durationMinutes = null;

        if ($this->check_in_time && $this->check_out_time) {
            $durationMinutes = (int) $this->check_in_time->diffInMinutes($this->check_out_time);
        }

        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'member' => $this->whenLoaded('member', function () {
                return [
                    'id' => $this->member?->id,
                    'name' => $this->member?->user?->name,
                    'email' => $this->member?->user?->email,
                    'phone' => $this->member?->phone,
                    'status' => $this->member?->status,
                ];
            }),
            'trainer_id' => $this->trainer_id,
            'trainer' => $this->whenLoaded('trainer', function () {
                if (! $this->trainer) {
                    return null;
                }

                return [
                    'id' => $this->trainer->id,
                    'name' => $this->trainer->user?->name,
                    'email' => $this->trainer->user?->email,
                    'specialization' => $this->trainer->specialization,
                ];
            }),
            'check_in_time' => optional($this->check_in_time)->toIso8601String(),
            'check_out_time' => optional($this->check_out_time)->toIso8601String(),
            'date' => optional($this->date)->toDateString(),
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'duration_minutes' => $durationMinutes,
            'is_inside' => $this->status === 'present' && $this->check_in_time && ! $this->check_out_time,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

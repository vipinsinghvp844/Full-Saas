<?php

namespace App\Http\Resources\Gym;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray($request): array
    {
        $activeMembership = $this->relationLoaded('activeMembership') ? $this->activeMembership : null;
        $activePlan = $activeMembership?->relationLoaded('plan') ? $activeMembership->plan : null;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'dob' => optional($this->date_of_birth)->toDateString(),
            'address' => $this->address,
            'emergency_contact' => $this->emergency_contact,
            'joining_date' => optional($this->joining_date)->toDateString(),
            'status' => $this->status,
            'membership_plan_id' => $activePlan?->id,
            'membership_plan' => $activePlan ? new MembershipPlanResource($activePlan) : null,
            'active_membership' => $activeMembership ? $this->membershipPayload($activeMembership) : null,
            'membership_history' => $this->whenLoaded('memberships', function () {
                return $this->memberships
                    ->sortByDesc('start_date')
                    ->values()
                    ->map(fn ($membership) => $this->membershipPayload($membership))
                    ->all();
            }),
            'assigned_trainer_id' => $this->assigned_trainer_id,
            'assigned_trainer' => $this->whenLoaded('assignedTrainer')
                ? new TrainerResource($this->assignedTrainer)
                : null,
            'attendance' => [],
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }

    protected function membershipPayload($membership): array
    {
        return [
            'id' => $membership->id,
            'plan_id' => $membership->plan_id,
            'plan' => $membership->relationLoaded('plan') && $membership->plan
                ? new MembershipPlanResource($membership->plan)
                : null,
            'start_date' => optional($membership->start_date)->toDateString(),
            'end_date' => optional($membership->end_date)->toDateString(),
            'status' => $membership->status,
            'payment_status' => $membership->payment_status,
            'final_amount' => $membership->final_amount,
            'created_at' => optional($membership->created_at)->toDateTimeString(),
        ];
    }
}

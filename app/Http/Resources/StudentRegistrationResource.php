<?php

namespace App\Http\Resources;

use App\Enums\StudentRegistrationStatus;
use App\Models\StudentRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StudentRegistration */
class StudentRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'academic_number' => $this->academic_number,
            'national_id' => $this->national_id,
            'department' => $this->department,
            'section_number' => $this->section_number,
            'group_number' => $this->group_number,
            'student_group' => $this->student_group?->value,
            'student_group_label' => $this->student_group?->label(),
            'group_code' => $this->groupCode(),
            'turn_number' => $this->turn_number,
            'service_date' => $this->service_date?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'is_completed' => $this->status === StudentRegistrationStatus::Completed,
            'people_ahead' => $this->people_ahead,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

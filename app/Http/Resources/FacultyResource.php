<?php

namespace App\Http\Resources;

use App\Models\Faculty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Faculty */
class FacultyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'value' => $this->slug,
            'label' => $this->name,
            'seat_number_min_digits' => $this->seat_number_min_digits,
            'seat_number_max_digits' => $this->seat_number_max_digits,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}

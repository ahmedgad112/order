<?php

namespace App\Http\Resources;

use App\Models\QueueTicket;
use App\Support\NameMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QueueTicket */
class PublicTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticketCode(),
            'full_name' => $this->full_name,
            'masked_name' => NameMasker::mask($this->full_name),
            'request_type_label' => $this->requestTypeLabel(),
            'college_label' => $this->collegeLabel(),
            'status' => $this->status->value,
            'counter_name' => $this->requestTypeCounter()
                ?? $this->whenLoaded('teller', fn () => $this->teller?->counter_name),
            'teller_name' => $this->whenLoaded('teller', fn () => $this->teller?->name),
            'called_at' => $this->called_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

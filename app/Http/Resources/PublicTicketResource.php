<?php

namespace App\Http\Resources;

use App\Support\NameMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QueueTicket */
class PublicTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'masked_name' => NameMasker::mask($this->full_name),
            'status' => $this->status->value,
            'counter_name' => $this->whenLoaded('teller', fn () => $this->teller?->counter_name),
            'called_at' => $this->called_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

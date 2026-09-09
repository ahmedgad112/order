<?php

namespace App\Http\Resources;

use App\Models\QueueTicket;
use App\Support\NameMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QueueTicket */
class IssuedTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticketCode(),
            'masked_name' => NameMasker::mask($this->full_name),
            'status' => $this->status->value,
            'public_token' => $this->public_token,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

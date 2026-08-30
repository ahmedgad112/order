<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\QueueTicket */
class AdminTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'full_name' => $this->full_name,
            'national_id' => $this->national_id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->statusLabel(),
            'counter_name' => $this->whenLoaded('teller', fn () => $this->teller?->counter_name),
            'teller_name' => $this->whenLoaded('teller', fn () => $this->teller?->name),
            'called_at' => $this->called_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            TicketStatus::Waiting => 'في الانتظار',
            TicketStatus::Serving => 'قيد الخدمة',
            TicketStatus::Completed => 'دخل',
            TicketStatus::Cancelled => 'ملغى',
        };
    }
}

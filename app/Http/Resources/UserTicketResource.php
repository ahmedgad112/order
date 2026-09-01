<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Models\QueueTicket;
use App\Support\NameMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QueueTicket */
class UserTicketResource extends JsonResource
{
    public function __construct(
        $resource,
        private readonly ?int $peopleAhead = null,
        private readonly ?int $positionInQueue = null,
    ) {
        parent::__construct($resource);
    }

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
            'status_label' => $this->statusLabel(),
            'counter_name' => $this->whenLoaded('teller', fn () => $this->teller?->counter_name),
            'teller_name' => $this->whenLoaded('teller', fn () => $this->teller?->name),
            'people_ahead' => $this->peopleAhead,
            'position_in_queue' => $this->positionInQueue,
            'called_at' => $this->called_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            TicketStatus::Waiting => 'في الانتظار',
            TicketStatus::Serving => 'يتم خدمتك الآن',
            TicketStatus::Completed => 'تمت الخدمة',
            TicketStatus::Cancelled => 'ملغاة',
            TicketStatus::Absent => 'لم يحضر — راجع الموظف',
        };
    }
}

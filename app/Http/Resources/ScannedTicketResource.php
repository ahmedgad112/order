<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Models\QueueTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QueueTicket */
class ScannedTicketResource extends JsonResource
{
    public function __construct(
        QueueTicket $resource,
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
            'full_name' => $this->full_name,
            'student_kind' => $this->studentKindValue(),
            'student_kind_label' => $this->studentKindLabel(),
            'national_id' => $this->national_id,
            'request_type' => $this->request_type?->value,
            'request_type_label' => $this->requestTypeLabel(),
            'college' => $this->college,
            'college_label' => $this->collegeLabel(),
            'department' => $this->department,
            'order_number' => $this->order_number,
            'seat_number' => $this->seat_number,
            'document_kind' => $this->document_kind?->value,
            'document_kind_label' => $this->documentKindLabel(),
            'has_document' => $this->hasDocument(),
            'document_url' => $this->hasDocument()
                ? '/teller/tickets/'.$this->id.'/document'
                : null,
            'status' => $this->status->value,
            'status_label' => $this->statusLabel(),
            'counter_name' => $this->whenLoaded('teller', fn () => $this->teller?->counter_name),
            'teller_name' => $this->whenLoaded('teller', fn () => $this->teller?->name),
            'people_ahead' => $this->peopleAhead,
            'position_in_queue' => $this->positionInQueue,
            'called_at' => $this->called_at?->toIso8601String(),
            'entered_at' => $this->entered_at?->toIso8601String(),
            'medical_checked_at' => $this->medical_checked_at?->toIso8601String(),
            'face_printed_at' => $this->face_printed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'file_delivered_at' => $this->file_delivered_at?->toIso8601String(),
            'has_entered' => $this->entered_at !== null,
            'has_medical_checked' => $this->medical_checked_at !== null,
            'has_face_printed' => $this->face_printed_at !== null,
            'file_delivered' => $this->file_delivered_at !== null,
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

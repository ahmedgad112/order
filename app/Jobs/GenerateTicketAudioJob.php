<?php

namespace App\Jobs;

use App\Enums\TicketStatus;
use App\Models\QueueTicket;
use App\Services\SpeechService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateTicketAudioJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $ticketId) {}

    public function handle(SpeechService $speech): void
    {
        $ticket = QueueTicket::query()
            ->with('teller')
            ->find($this->ticketId);

        if (! $ticket instanceof QueueTicket || $ticket->status !== TicketStatus::Serving) {
            return;
        }

        try {
            $speech->synthesizeToFile($speech->ticketAnnouncementText($ticket));
        } catch (\Throwable $exception) {
            Log::warning('Ticket TTS pre-generation failed: '.$exception->getMessage());
        }
    }
}

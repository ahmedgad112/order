<?php

namespace App\Events;

use App\Enums\ProcessStep;
use App\Http\Resources\PublicTicketResource;
use App\Models\QueueTicket;
use App\Models\StepAnnouncement;
use App\Services\SpeechService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCalledEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public QueueTicket $ticket,
        public ?ProcessStep $step = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('queue-channel')];
    }

    public function broadcastAs(): string
    {
        return 'TicketCalled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->ticket->loadMissing('teller');

        return [
            'ticket' => (new PublicTicketResource($this->ticket))->resolve(),
            'step' => $this->step?->value,
            'muted' => $this->step !== null && ! StepAnnouncement::isEnabledFor($this->step),
            'counter' => SpeechService::formatCounterName($this->ticket->resolvedCounterName()),
        ];
    }
}

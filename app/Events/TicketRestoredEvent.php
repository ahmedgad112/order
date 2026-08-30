<?php

namespace App\Events;

use App\Http\Resources\PublicTicketResource;
use App\Http\Resources\TicketResource;
use App\Models\QueueTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketRestoredEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public QueueTicket $ticket) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('queue-channel')];
    }

    public function broadcastAs(): string
    {
        return 'TicketRestored';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket' => (new PublicTicketResource($this->ticket))->resolve(),
            'ticket_detail' => (new TicketResource($this->ticket))->resolve(),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'queue_ticket_id',
    'process_service_id',
    'completed_by',
    'completed_at',
])]
class TicketServiceCompletion extends Model
{
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ProcessService::class, 'process_service_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

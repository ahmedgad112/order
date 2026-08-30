<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ticket_number',
    'full_name',
    'national_id',
    'order_number',
    'status',
    'user_id',
    'called_at',
    'completed_at',
])]
#[Hidden(['national_id', 'order_number'])]
class QueueTicket extends Model
{
    /** @use HasFactory<\Database\Factories\QueueTicketFactory> */
    use HasFactory;
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'called_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function teller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', TicketStatus::activeValues());
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Waiting);
    }

    public function scopeServing(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Serving);
    }
}

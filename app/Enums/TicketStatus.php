<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Waiting = 'waiting';
    case Serving = 'serving';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Absent = 'absent';

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Waiting->value, self::Serving->value];
    }
}

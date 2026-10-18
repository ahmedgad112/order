<?php

namespace App\Enums;

enum StudentRegistrationStatus: string
{
    case Waiting = 'waiting';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'في الانتظار',
            self::Completed => 'تم',
        };
    }
}

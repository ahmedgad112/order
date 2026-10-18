<?php

namespace App\Enums;

enum StudentRegistrationStatus: string
{
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'في الانتظار',
            self::Completed => 'تم',
            self::Absent => 'مش موجود',
        };
    }
}

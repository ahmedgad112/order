<?php

namespace App\Enums;

enum StudentRegistrationGroup: string
{
    case GroupA = 'A';
    case GroupB = 'B';

    public function label(): string
    {
        return 'مجموعة '.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

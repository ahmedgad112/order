<?php

namespace App\Enums;

enum StudentKind: string
{
    case NewStudent = 'new_student';
    case CurrentStudent = 'current_student';

    public function label(): string
    {
        return match ($this) {
            self::NewStudent => 'طالب جديد',
            self::CurrentStudent => 'طالب حالي (فرقة ثانية)',
        };
    }

    public function ticketPrefix(): string
    {
        return match ($this) {
            self::NewStudent => 'N',
            self::CurrentStudent => 'O',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function payload(): array
    {
        return array_map(
            fn (self $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ],
            self::cases(),
        );
    }
}

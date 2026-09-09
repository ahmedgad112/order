<?php

namespace App\Enums;

enum DocumentKind: string
{
    case StatusStatement = 'status_statement';
    case StudentCard = 'student_card';

    public function label(): string
    {
        return match ($this) {
            self::StatusStatement => 'إيصال دفع بيان الحالة وحسن السير والسلوك',
            self::StudentCard => 'الكارنيه',
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

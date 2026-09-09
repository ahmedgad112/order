<?php

namespace App\Enums;

enum Faculty: string
{
    case IndustryEnergy = 'industry_energy';
    case HealthSciences = 'health_sciences';

    public function label(): string
    {
        return match ($this) {
            self::IndustryEnergy => 'صناعة وطاقة',
            self::HealthSciences => 'علوم صحية',
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
            fn (self $faculty): array => [
                'value' => $faculty->value,
                'label' => $faculty->label(),
            ],
            self::cases(),
        );
    }
}

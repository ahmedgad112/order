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

    public function seatNumberMinDigits(): int
    {
        return 7;
    }

    public function seatNumberMaxDigits(): int
    {
        return match ($this) {
            self::HealthSciences => 9,
            self::IndustryEnergy => 7,
        };
    }

    /**
     * @return list<string>
     */
    public function seatNumberRules(): array
    {
        $min = $this->seatNumberMinDigits();
        $max = $this->seatNumberMaxDigits();

        if ($min === $max) {
            return ["digits:{$min}"];
        }

        return ["digits_between:{$min},{$max}"];
    }

    /**
     * @return list<string>
     */
    public static function seatNumberRulesFor(?string $college): array
    {
        return self::tryFrom((string) $college)?->seatNumberRules() ?? ['digits:7'];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string, label: string, seat_number_min_digits: int, seat_number_max_digits: int}>
     */
    public static function payload(): array
    {
        return array_map(
            fn (self $faculty): array => [
                'value' => $faculty->value,
                'label' => $faculty->label(),
                'seat_number_min_digits' => $faculty->seatNumberMinDigits(),
                'seat_number_max_digits' => $faculty->seatNumberMaxDigits(),
            ],
            self::cases(),
        );
    }
}

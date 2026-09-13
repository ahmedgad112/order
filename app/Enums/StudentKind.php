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

    public static function tryFromTicketPrefix(string $prefix): ?self
    {
        return match (strtoupper($prefix)) {
            'N' => self::NewStudent,
            'O' => self::CurrentStudent,
            default => null,
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
     * @param  list<string>|null  $enabledValues
     * @return list<array{value: string, label: string, enabled: bool}>
     */
    public static function payload(?array $enabledValues = null): array
    {
        $enabled = $enabledValues ?? self::values();

        return array_map(
            fn (self $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
                'enabled' => in_array($kind->value, $enabled, true),
            ],
            self::cases(),
        );
    }
}

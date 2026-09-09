<?php

namespace App\Enums;

enum QueueLane: string
{
    case NominationCard = 'nomination_card';
    case DirectApplication = 'direct_application';
    case Transfer = 'transfer';
    case CurrentStudent = 'current_student';

    public function label(): string
    {
        return match ($this) {
            self::NominationCard => RequestType::NominationCard->label(),
            self::DirectApplication => RequestType::DirectApplication->label(),
            self::Transfer => RequestType::Transfer->label(),
            self::CurrentStudent => StudentKind::CurrentStudent->label(),
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
     * @return list<array{value: string, label: string}>
     */
    public static function payload(?array $enabledValues = null): array
    {
        $values = $enabledValues ?? self::values();

        return array_values(array_filter(array_map(
            function (self $lane) use ($values): ?array {
                if (! in_array($lane->value, $values, true)) {
                    return null;
                }

                return [
                    'value' => $lane->value,
                    'label' => $lane->label(),
                ];
            },
            self::cases(),
        )));
    }
}

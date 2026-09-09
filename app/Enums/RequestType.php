<?php

namespace App\Enums;

enum RequestType: string
{
    case NominationCard = 'nomination_card';
    case DirectApplication = 'direct_application';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::NominationCard => 'حاصل على بطاقة ترشيح',
            self::DirectApplication => 'تقديم مباشر',
            self::Transfer => 'تحويل (مناظر / غير مناظر)',
        };
    }

    public function collegeFieldLabel(): string
    {
        return match ($this) {
            self::NominationCard => 'الكلية الواردة في بطاقة الترشيح',
            self::DirectApplication, self::Transfer => 'الكلية المراد الالتحاق بها',
        };
    }

    public function collegeMode(): string
    {
        return $this === self::NominationCard ? 'select' : 'text';
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
     * @return list<array{value: string, label: string, enabled: bool, college_mode: string, college_label: string}>
     */
    public static function payload(?array $enabledValues = null): array
    {
        $enabled = $enabledValues ?? self::values();

        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'enabled' => in_array($type->value, $enabled, true),
                'college_mode' => $type->collegeMode(),
                'college_label' => $type->collegeFieldLabel(),
            ],
            self::cases(),
        );
    }
}

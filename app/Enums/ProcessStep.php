<?php

namespace App\Enums;

enum ProcessStep: string
{
    case Entered = 'entered';
    case Paid = 'paid';
    case FileWithdrawn = 'file_withdrawn';
    case DocumentsReviewed = 'documents_reviewed';
    case MedicalChecked = 'medical_checked';
    case FacePrinted = 'face_printed';
    case FileDelivered = 'file_delivered';

    public function label(): string
    {
        return match ($this) {
            self::Entered => 'تم الدخول',
            self::Paid => 'دفع',
            self::FileWithdrawn => 'سحب ملف',
            self::DocumentsReviewed => 'مراجعة ورق',
            self::MedicalChecked => 'كشف طبي',
            self::FacePrinted => 'بصمة وجه',
            self::FileDelivered => 'تسليم ملف',
        };
    }

    /**
     * @return list<self>
     */
    public static function admissionCompletionCases(): array
    {
        return [
            self::Paid,
            self::FileWithdrawn,
            self::MedicalChecked,
            self::FacePrinted,
            self::FileDelivered,
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function assignableValues(): array
    {
        return [...self::values(), 'completed'];
    }

    /**
     * @param  list<string>|null  $selectedValues
     * @return list<array{value: string, label: string, enabled: bool}>
     */
    public static function assignablePayload(?array $selectedValues = null): array
    {
        $selected = $selectedValues ?? self::assignableValues();

        $items = array_map(
            fn (self $step): array => [
                'value' => $step->value,
                'label' => $step->label(),
                'enabled' => in_array($step->value, $selected, true),
            ],
            self::cases(),
        );

        $items[] = [
            'value' => 'completed',
            'label' => 'اكتمال',
            'enabled' => in_array('completed', $selected, true),
        ];

        return $items;
    }

    /**
     * @return list<string>
     */
    public static function admissionCompletionValues(): array
    {
        return array_map(
            fn (self $step): string => $step->value,
            self::admissionCompletionCases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function admissionCompletionPayload(): array
    {
        return array_map(
            fn (self $step): array => [
                'value' => $step->value,
                'label' => $step->label(),
            ],
            self::admissionCompletionCases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function priorCheckpointAttributes(): array
    {
        $now = now();

        return match ($this) {
            self::FileWithdrawn => [
                'paid_at' => $now,
            ],
            self::MedicalChecked => [
                'paid_at' => $now,
                'file_withdrawn_at' => $now,
            ],
            self::FacePrinted => [
                'paid_at' => $now,
                'file_withdrawn_at' => $now,
                'medical_checked_at' => $now,
            ],
            self::FileDelivered => [
                'paid_at' => $now,
                'file_withdrawn_at' => $now,
                'medical_checked_at' => $now,
                'face_printed_at' => $now,
            ],
            default => [],
        };
    }
}

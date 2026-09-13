<?php

namespace App\Enums;

enum ProcessStep: string
{
    case Entered = 'entered';
    case DocumentsReviewed = 'documents_reviewed';
    case MedicalChecked = 'medical_checked';
    case FacePrinted = 'face_printed';
    case FileDelivered = 'file_delivered';

    public function label(): string
    {
        return match ($this) {
            self::Entered => 'تم الدخول',
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
            self::MedicalChecked,
            self::FacePrinted,
            self::FileDelivered,
        ];
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
            self::MedicalChecked => [
                'entered_at' => $now,
            ],
            self::FacePrinted => [
                'entered_at' => $now,
                'medical_checked_at' => $now,
            ],
            self::FileDelivered => [
                'entered_at' => $now,
                'medical_checked_at' => $now,
                'face_printed_at' => $now,
            ],
            default => [],
        };
    }
}

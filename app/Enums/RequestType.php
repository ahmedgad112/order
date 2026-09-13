<?php

namespace App\Enums;

enum RequestType: string
{
    case NominationCard = 'nomination_card';
    case DirectApplication = 'direct_application';
    case Transfer = 'transfer';
    case DocumentCompletion = 'document_completion';

    public function label(): string
    {
        return match ($this) {
            self::NominationCard => 'حاصل على بطاقة ترشيح',
            self::DirectApplication => 'تقديم مباشر',
            self::Transfer => 'تحويل (مناظر / غير مناظر)',
            self::DocumentCompletion => 'استكمال أوراق',
        };
    }

    public function collegeFieldLabel(): string
    {
        return match ($this) {
            self::NominationCard => 'الكلية الواردة في بطاقة الترشيح',
            self::DirectApplication, self::Transfer => 'الكلية المراد الالتحاق بها',
            self::DocumentCompletion => 'الكلية',
        };
    }

    public function collegeMode(): string
    {
        return $this === self::NominationCard ? 'select' : 'text';
    }

    public function ticketPrefix(): string
    {
        return match ($this) {
            self::NominationCard => 'OT',
            self::DirectApplication => 'OD',
            self::Transfer => 'OB',
            self::DocumentCompletion => 'OF',
        };
    }

    public static function tryFromTicketPrefix(string $prefix): ?self
    {
        return match (strtoupper($prefix)) {
            'OT' => self::NominationCard,
            'OD' => self::DirectApplication,
            'OB' => self::Transfer,
            'OF' => self::DocumentCompletion,
            default => null,
        };
    }

    public function requiresCompletionService(): bool
    {
        return $this === self::DocumentCompletion;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function completionServices(): array
    {
        if (! $this->requiresCompletionService()) {
            return [];
        }

        return ProcessStep::admissionCompletionPayload();
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
     * @return list<array{value: string, label: string, enabled: bool, college_mode: string, college_label: string, requires_completion_service: bool, completion_services: list<array{value: string, label: string}>}>
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
                'requires_completion_service' => $type->requiresCompletionService(),
                'completion_services' => $type->completionServices(),
            ],
            self::cases(),
        );
    }
}

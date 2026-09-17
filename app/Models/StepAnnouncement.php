<?php

namespace App\Models;

use App\Enums\ProcessStep;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'step',
    'enabled',
    'destination',
])]
class StepAnnouncement extends Model
{
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /**
     * Default spoken destination per step: where the student flows next.
     * A null value falls back to the resolved counter name.
     *
     * @return array<string, string|null>
     */
    public static function defaultDestinations(): array
    {
        return [
            ProcessStep::Entered->value => null,
            ProcessStep::Paid->value => 'سحب الملف',
            ProcessStep::FileWithdrawn->value => 'الكشف الطبي',
            ProcessStep::DocumentsReviewed->value => 'تسليم الملف',
            ProcessStep::MedicalChecked->value => 'بصمة الوجه',
            ProcessStep::FacePrinted->value => 'تسليم الملف',
            ProcessStep::FileDelivered->value => null,
        ];
    }

    public static function forStep(ProcessStep|string $step): self
    {
        $value = $step instanceof ProcessStep ? $step->value : $step;

        return static::query()->firstOrCreate(
            ['step' => $value],
            [
                'enabled' => true,
                'destination' => static::defaultDestinations()[$value] ?? null,
            ],
        );
    }

    public static function isEnabledFor(ProcessStep|string $step): bool
    {
        return static::forStep($step)->enabled;
    }

    public static function destinationFor(ProcessStep|string $step): ?string
    {
        $destination = trim((string) (static::forStep($step)->destination ?? ''));

        return $destination === '' ? null : $destination;
    }

    /**
     * @return list<array{step: string, label: string, enabled: bool, destination: string|null}>
     */
    public static function payload(): array
    {
        $defaults = static::defaultDestinations();
        $rows = static::query()->get()->keyBy('step');

        return array_map(
            fn (ProcessStep $step): array => [
                'step' => $step->value,
                'label' => $step->label(),
                'enabled' => $rows->get($step->value)?->enabled ?? true,
                'destination' => $rows->get($step->value)?->destination ?? $defaults[$step->value] ?? null,
            ],
            ProcessStep::cases(),
        );
    }
}

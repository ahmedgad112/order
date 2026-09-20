<?php

namespace App\Models;

use App\Enums\ProcessStep;
use App\Enums\StudentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'slug',
    'label',
    'system_key',
    'flow',
    'is_system',
    'is_enabled',
    'sort_order',
])]
class ProcessService extends Model
{
    public const FLOW_ADMISSION = 'admission';

    public const FLOW_CURRENT_STUDENT = 'current_student';

    public const FLOW_BOTH = 'both';

    /** @var Collection<int, ProcessService>|null */
    private static ?Collection $catalogCache = null;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCatalog());
        static::deleted(fn () => self::flushCatalog());
    }

    public static function flushCatalog(): void
    {
        self::$catalogCache = null;
    }

    /**
     * @return Collection<int, ProcessService>
     */
    public static function catalog(): Collection
    {
        return self::$catalogCache ??= static::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function completions(): HasMany
    {
        return $this->hasMany(TicketServiceCompletion::class);
    }

    public function isCustom(): bool
    {
        return ! $this->is_system;
    }

    public function appliesTo(string $studentKind): bool
    {
        if ($this->flow === self::FLOW_BOTH) {
            return true;
        }

        if ($studentKind === StudentKind::CurrentStudent->value) {
            return $this->flow === self::FLOW_CURRENT_STUDENT;
        }

        return $this->flow === self::FLOW_ADMISSION;
    }

    /**
     * @return Collection<int, ProcessService>
     */
    public static function enabledForStudentKind(string $studentKind): Collection
    {
        return self::catalog()
            ->where('is_enabled', true)
            ->filter(fn (self $service): bool => $service->appliesTo($studentKind))
            ->values();
    }

    public static function findBySlug(?string $slug): ?self
    {
        if ($slug === null) {
            return null;
        }

        return self::catalog()->firstWhere('slug', $slug);
    }

    public static function findBySystemKey(?string $key): ?self
    {
        if ($key === null) {
            return null;
        }

        return self::catalog()->firstWhere('system_key', $key);
    }

    public static function isSystemStepEnabled(string $systemKey): bool
    {
        $service = self::findBySystemKey($systemKey);

        if ($service !== null) {
            return $service->is_enabled;
        }

        // Catalog is active and this system service was removed.
        if (self::catalog()->isNotEmpty()) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function assignableValues(): array
    {
        return self::catalog()
            ->where('is_enabled', true)
            ->pluck('slug')
            ->all();
    }

    /**
     * @param  list<string>|null  $selectedValues
     * @return list<array{value: string, label: string, enabled: bool, is_system: bool, flow: string}>
     */
    public static function assignablePayload(?array $selectedValues = null): array
    {
        $selected = $selectedValues ?? self::assignableValues();

        return self::catalog()
            ->where('is_enabled', true)
            ->map(fn (self $service): array => [
                'value' => $service->slug,
                'label' => $service->label,
                'enabled' => in_array($service->slug, $selected, true),
                'is_system' => $service->is_system,
                'flow' => $service->flow,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function adminPayload(): array
    {
        return self::catalog()
            ->map(fn (self $service): array => $service->toAdminArray())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'label' => $this->label,
            'system_key' => $this->system_key,
            'flow' => $this->flow,
            'flow_label' => $this->flowLabel(),
            'is_system' => $this->is_system,
            'is_enabled' => $this->is_enabled,
            'sort_order' => $this->sort_order,
        ];
    }

    public function flowLabel(): string
    {
        return match ($this->flow) {
            self::FLOW_ADMISSION => 'طالب جديد',
            self::FLOW_CURRENT_STUDENT => 'طالب حالي',
            default => 'الكل',
        };
    }

    public static function uniqueSlugFromLabel(string $label): string
    {
        $base = Str::slug($label);
        if ($base === '') {
            $base = 'service';
        }

        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public static function seedDefaults(): void
    {
        if (static::query()->exists()) {
            return;
        }

        $defaults = [
            ['system_key' => ProcessStep::Entered->value, 'label' => 'طلب دخول', 'flow' => self::FLOW_BOTH, 'sort_order' => 10],
            ['system_key' => ProcessStep::Paid->value, 'label' => ProcessStep::Paid->label(), 'flow' => self::FLOW_ADMISSION, 'sort_order' => 20],
            ['system_key' => ProcessStep::FileWithdrawn->value, 'label' => ProcessStep::FileWithdrawn->label(), 'flow' => self::FLOW_ADMISSION, 'sort_order' => 30],
            ['system_key' => ProcessStep::DocumentsReviewed->value, 'label' => ProcessStep::DocumentsReviewed->label(), 'flow' => self::FLOW_CURRENT_STUDENT, 'sort_order' => 40],
            ['system_key' => ProcessStep::MedicalChecked->value, 'label' => ProcessStep::MedicalChecked->label(), 'flow' => self::FLOW_ADMISSION, 'sort_order' => 50],
            ['system_key' => ProcessStep::FacePrinted->value, 'label' => ProcessStep::FacePrinted->label(), 'flow' => self::FLOW_ADMISSION, 'sort_order' => 60],
            ['system_key' => ProcessStep::FileDelivered->value, 'label' => ProcessStep::FileDelivered->label(), 'flow' => self::FLOW_BOTH, 'sort_order' => 70],
            ['system_key' => 'completed', 'label' => 'اكتمال', 'flow' => self::FLOW_BOTH, 'sort_order' => 80],
        ];

        foreach ($defaults as $row) {
            static::query()->create([
                'system_key' => $row['system_key'],
                'slug' => $row['system_key'],
                'label' => $row['label'],
                'flow' => $row['flow'],
                'is_system' => true,
                'is_enabled' => true,
                'sort_order' => $row['sort_order'],
            ]);
        }

        self::flushCatalog();
    }
}

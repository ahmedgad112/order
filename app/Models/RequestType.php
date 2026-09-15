<?php

namespace App\Models;

use App\Enums\ProcessStep;
use App\Enums\StudentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'slug',
    'label',
    'code_prefix',
    'college_mode',
    'college_label',
    'requires_completion_service',
    'completion_services',
    'enabled',
    'sort_order',
])]
class RequestType extends Model
{
    public const COLLEGE_MODE_SELECT = 'select';

    public const COLLEGE_MODE_TEXT = 'text';

    public const COLLEGE_MODES = [self::COLLEGE_MODE_SELECT, self::COLLEGE_MODE_TEXT];

    public const LANE_CURRENT_STUDENT = 'current_student';

    /** @var Collection<int, RequestType>|null */
    private static ?Collection $catalogCache = null;

    protected function casts(): array
    {
        return [
            'requires_completion_service' => 'boolean',
            'completion_services' => 'array',
            'enabled' => 'boolean',
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
     * @return Collection<int, RequestType>
     */
    public static function catalog(): Collection
    {
        return self::$catalogCache ??= static::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public static function findBySlug(?string $slug): ?self
    {
        if ($slug === null) {
            return null;
        }

        return self::catalog()->firstWhere('slug', $slug);
    }

    public static function findByPrefix(string $prefix): ?self
    {
        return self::catalog()->firstWhere('code_prefix', strtoupper($prefix));
    }

    public static function labelFor(?string $slug): ?string
    {
        return self::findBySlug($slug)?->label;
    }

    public static function prefixFor(?string $slug): ?string
    {
        return self::findBySlug($slug)?->code_prefix;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return self::catalog()->pluck('slug')->all();
    }

    /**
     * @return list<string>
     */
    public static function enabledSlugs(): array
    {
        return self::catalog()->where('enabled', true)->pluck('slug')->all();
    }

    /**
     * @return list<string>
     */
    public static function prefixes(): array
    {
        return self::catalog()->pluck('code_prefix')->all();
    }

    /**
     * Queue lanes = every request type slug + the current-student lane.
     *
     * @return list<string>
     */
    public static function laneValues(): array
    {
        return [...self::slugs(), self::LANE_CURRENT_STUDENT];
    }

    public static function laneLabel(string $lane): string
    {
        if ($lane === self::LANE_CURRENT_STUDENT) {
            return StudentKind::CurrentStudent->label();
        }

        return self::labelFor($lane) ?? $lane;
    }

    /**
     * @param  list<string>|null  $assignedValues
     * @return list<array{value: string, label: string, enabled: bool}>
     */
    public static function lanePayload(?array $assignedValues = null): array
    {
        $lanes = self::catalog()
            ->filter(fn (RequestType $type): bool => $assignedValues === null
                || in_array($type->slug, $assignedValues, true))
            ->map(fn (RequestType $type): array => [
                'value' => $type->slug,
                'label' => $type->label,
                'enabled' => true,
            ])
            ->values()
            ->all();

        if (
            $assignedValues === null
            || in_array(self::LANE_CURRENT_STUDENT, $assignedValues, true)
        ) {
            $lanes[] = [
                'value' => self::LANE_CURRENT_STUDENT,
                'label' => StudentKind::CurrentStudent->label(),
                'enabled' => true,
            ];
        }

        return $lanes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function payload(): array
    {
        return self::catalog()
            ->map(fn (RequestType $type): array => $type->toPayload())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function adminPayload(): array
    {
        return self::catalog()
            ->map(fn (RequestType $type): array => [
                ...$type->toPayload(),
                'tickets_count' => $type->ticketsCount(),
            ])
            ->all();
    }

    /**
     * @return array{value: string, label: string, enabled: bool, code_prefix: string, college_mode: string, college_label: string|null, requires_completion_service: bool, completion_services: list<array{value: string, label: string}>}
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->slug,
            'label' => $this->label,
            'enabled' => $this->enabled,
            'code_prefix' => $this->code_prefix,
            'college_mode' => $this->college_mode ?? self::COLLEGE_MODE_TEXT,
            'college_label' => $this->college_label,
            'requires_completion_service' => $this->requires_completion_service,
            'completion_services' => $this->completionServicePayload(),
        ];
    }

    /**
     * @return list<string>
     */
    public function completionServiceValues(): array
    {
        $services = $this->completion_services;

        if (! is_array($services) || $services === []) {
            return $this->requires_completion_service
                ? ProcessStep::admissionCompletionValues()
                : [];
        }

        return array_values(array_intersect(
            $services,
            ProcessStep::admissionCompletionValues(),
        ));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function completionServicePayload(): array
    {
        $allowed = $this->completionServiceValues();

        return array_values(array_filter(
            ProcessStep::admissionCompletionPayload(),
            fn (array $service): bool => in_array($service['value'], $allowed, true),
        ));
    }

    /**
     * @return array{0: StudentKind, 1: string|null}
     */
    public static function resolveLane(string $lane): array
    {
        if ($lane === self::LANE_CURRENT_STUDENT) {
            return [StudentKind::CurrentStudent, null];
        }

        $type = self::findBySlug($lane);

        if (! $type instanceof self) {
            throw ValidationException::withMessages([
                'request_type' => 'نوع الطلب غير صحيح.',
            ]);
        }

        return [StudentKind::NewStudent, $type->slug];
    }

    public function isCollegeSelectMode(): bool
    {
        return $this->college_mode === self::COLLEGE_MODE_SELECT;
    }

    public function ticketsCount(): int
    {
        return QueueTicket::query()->where('request_type', $this->slug)->count();
    }

    public static function nextSortOrder(): int
    {
        return ((int) static::query()->max('sort_order')) + 1;
    }

    /**
     * Auto-generated two-letter ticket prefix, skipping student-kind
     * prefixes (N, O) and every prefix already in use.
     */
    public static function nextAvailablePrefix(): string
    {
        $used = [
            ...self::prefixes(),
            StudentKind::NewStudent->ticketPrefix(),
            StudentKind::CurrentStudent->ticketPrefix(),
        ];

        $firstLetters = ['O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'];

        foreach ($firstLetters as $first) {
            foreach (range('A', 'Z') as $second) {
                $candidate = $first.$second;

                if (! in_array($candidate, $used, true)) {
                    return $candidate;
                }
            }
        }

        throw ValidationException::withMessages([
            'code_prefix' => 'لا توجد رموز متاحة لنوع طلب جديد.',
        ]);
    }

    public static function makeSlug(string $label): string
    {
        $base = Str::slug($label);

        if ($base === '') {
            $base = 'request-type';
        }

        $slug = $base;
        $counter = 2;

        while (
            $slug === self::LANE_CURRENT_STUDENT
            || static::query()->where('slug', $slug)->exists()
        ) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}

<?php

namespace App\Models;

use App\Support\CatalogSlug;
use Database\Factories\FacultyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug',
    'name',
    'seat_number_min_digits',
    'seat_number_max_digits',
    'sort_order',
    'is_active',
])]
class Faculty extends Model
{
    /** @use HasFactory<FacultyFactory> */
    use HasFactory;

    public const IndustryEnergy = 'industry_energy';

    public const HealthSciences = 'health_sciences';

    protected function casts(): array
    {
        return [
            'seat_number_min_digits' => 'integer',
            'seat_number_max_digits' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return static::query()->ordered()->pluck('slug')->all();
    }

    /**
     * @return list<string>
     */
    public static function activeSlugs(): array
    {
        return static::query()->active()->ordered()->pluck('slug')->all();
    }

    /**
     * @return list<array{value: string, label: string, seat_number_min_digits: int, seat_number_max_digits: int, is_active: bool}>
     */
    public static function payload(bool $activeOnly = true): array
    {
        $query = static::query()->ordered();

        if ($activeOnly) {
            $query->active();
        }

        return $query->get()->map(fn (self $faculty): array => $faculty->toPublicArray())->all();
    }

    /**
     * @param  list<string>|null  $assignedValues
     * @return list<array{value: string, label: string}>
     */
    public static function assignmentPayload(?array $assignedValues = null): array
    {
        return collect(static::payload(activeOnly: false))
            ->when(
                $assignedValues !== null,
                fn ($items) => $items->filter(
                    fn (array $item): bool => in_array($item['value'], $assignedValues, true),
                ),
            )
            ->map(fn (array $item): array => [
                'value' => $item['value'],
                'label' => $item['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{value: string, label: string, seat_number_min_digits: int, seat_number_max_digits: int, is_active: bool}
     */
    public function toPublicArray(): array
    {
        return [
            'value' => $this->slug,
            'label' => $this->name,
            'seat_number_min_digits' => $this->seat_number_min_digits,
            'seat_number_max_digits' => $this->seat_number_max_digits,
            'is_active' => $this->is_active,
        ];
    }

    /**
     * @return list<string>
     */
    public static function seatNumberRulesFor(?string $slug): array
    {
        $faculty = static::query()->where('slug', (string) $slug)->first();
        $min = $faculty?->seat_number_min_digits ?? 7;
        $max = $faculty?->seat_number_max_digits ?? $min;

        if ($min === $max) {
            return ["digits:{$min}"];
        }

        return ["digits_between:{$min},{$max}"];
    }

    public static function labelFor(?string $slug): ?string
    {
        if (blank($slug)) {
            return null;
        }

        return static::query()->where('slug', $slug)->value('name');
    }

    /**
     * @return array<string, string>
     */
    public static function labelsBySlug(): array
    {
        return static::query()->pluck('name', 'slug')->all();
    }

    public static function nextSortOrder(): int
    {
        return (int) static::query()->max('sort_order') + 1;
    }

    public static function makeSlug(string $name, ?string $provided = null, ?int $ignoreId = null): string
    {
        return CatalogSlug::unique(static::class, $name, $provided, $ignoreId);
    }

    public static function activeCount(): int
    {
        return static::query()->active()->count();
    }
}

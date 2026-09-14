<?php

namespace App\Models;

use App\Support\CatalogSlug;
use Database\Factories\CollegeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'slug',
    'name',
    'sort_order',
    'is_active',
])]
class College extends Model
{
    /** @use HasFactory<CollegeFactory> */
    use HasFactory;

    public const InformationTechnology = 'information_technology';

    public const RailwayTechnology = 'railway_technology';

    public const TextileTechnology = 'textile_technology';

    public const FoodIndustryTechnology = 'food_industry_technology';

    public const AgriculturalEquipment = 'agricultural_equipment';

    public const DentalLaboratory = 'dental_laboratory';

    public const PharmaceuticalProduction = 'pharmaceutical_production';

    public const HealthInformationManagement = 'health_information_management';

    public const HealthCareTechnology = 'health_care_technology';

    public const HealthScienceBasic = 'health_science_basic';

    protected function casts(): array
    {
        return [
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
     * @return list<array{value: string, label: string, is_active: bool}>
     */
    public static function payload(bool $activeOnly = true): array
    {
        $query = static::query()->ordered();

        if ($activeOnly) {
            $query->active();
        }

        return $query->get()->map(fn (self $college): array => $college->toPublicArray())->all();
    }

    /**
     * @return array{value: string, label: string, is_active: bool}
     */
    public function toPublicArray(): array
    {
        return [
            'value' => $this->slug,
            'label' => $this->name,
            'is_active' => $this->is_active,
        ];
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

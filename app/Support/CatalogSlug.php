<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CatalogSlug
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function unique(string $modelClass, string $name, ?string $provided = null, ?int $ignoreId = null): string
    {
        $base = self::normalize($provided);

        if ($base === '') {
            $base = self::normalize($name);
        }

        if ($base === '') {
            $base = Str::of(class_basename($modelClass))->snake().'_'.Str::lower(Str::random(6));
        }

        $slug = $base;
        $suffix = 2;

        while (
            $modelClass::query()
                ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'_'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public static function normalize(?string $value): string
    {
        $slug = Str::of((string) $value)
            ->trim()
            ->lower()
            ->replaceMatches('/\s+/', '_')
            ->toString();

        $slug = preg_replace('/[^a-z0-9_\-]/', '', $slug) ?? '';

        return trim($slug, '_-');
    }
}

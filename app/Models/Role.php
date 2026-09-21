<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable(['slug', 'name', 'rank', 'is_system', 'is_super_admin', 'serves_queue'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public const SLUG_SUPER_ADMIN = 'super_admin';

    public const SLUG_MANAGER = 'manager';

    public const SLUG_TELLER = 'teller';

    private const CACHE_KEY = 'roles.catalog';

    private const CACHE_TTL_SECONDS = 300;

    /** @var Collection<int, self>|null */
    private static ?Collection $requestCatalog = null;

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'is_system' => 'boolean',
            'is_super_admin' => 'boolean',
            'serves_queue' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'slug');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role', 'slug');
    }

    /**
     * @return Collection<int, self>
     */
    public static function catalog(): Collection
    {
        if (self::$requestCatalog instanceof Collection) {
            return self::$requestCatalog;
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            self::$requestCatalog = self::query()
                ->whereIn('id', $cached)
                ->orderByDesc('rank')
                ->orderBy('name')
                ->get();

            return self::$requestCatalog;
        }

        self::$requestCatalog = self::query()
            ->orderByDesc('rank')
            ->orderBy('name')
            ->get();

        Cache::put(
            self::CACHE_KEY,
            self::$requestCatalog->modelKeys(),
            self::CACHE_TTL_SECONDS,
        );

        return self::$requestCatalog;
    }

    public static function flushCatalog(): void
    {
        self::$requestCatalog = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function findBySlug(?string $slug): ?self
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return self::catalog()->firstWhere('slug', $slug);
    }

    public static function findBySlugOrFail(string $slug): self
    {
        $role = self::findBySlug($slug);

        if (! $role) {
            abort(404, 'الدور غير موجود.');
        }

        return $role;
    }

    /**
     * @return list<string>
     */
    public static function queueSlugs(): array
    {
        return self::catalog()
            ->where('serves_queue', true)
            ->pluck('slug')
            ->values()
            ->all();
    }

    /**
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        if ($this->is_super_admin) {
            return self::catalog()->all();
        }

        return self::catalog()
            ->filter(fn (self $role): bool => $this->canAssign($role))
            ->values()
            ->all();
    }

    public function canAssign(self $role): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        if ($role->is_super_admin || $role->rank >= $this->rank) {
            return false;
        }

        return $role->serves_queue;
    }

    public function canManage(self $target): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        if ($target->is_super_admin || $target->rank >= $this->rank) {
            return false;
        }

        return $target->serves_queue;
    }

    /**
     * @return array{value: string, label: string, rank: int, is_system: bool, is_super_admin: bool, serves_queue: bool, users_count?: int}
     */
    public function toOption(bool $withUsersCount = false): array
    {
        $payload = [
            'value' => $this->slug,
            'label' => $this->name,
            'rank' => $this->rank,
            'is_system' => $this->is_system,
            'is_super_admin' => $this->is_super_admin,
            'serves_queue' => $this->serves_queue,
        ];

        if ($withUsersCount) {
            $payload['users_count'] = $this->users()->count();
        }

        return $payload;
    }

    /**
     * @return list<array{slug: string, name: string, rank: int, is_system: bool, is_super_admin: bool, serves_queue: bool}>
     */
    public static function systemDefinitions(): array
    {
        return [
            [
                'slug' => self::SLUG_SUPER_ADMIN,
                'name' => 'سوبر أدمن',
                'rank' => 100,
                'is_system' => true,
                'is_super_admin' => true,
                'serves_queue' => false,
            ],
            [
                'slug' => self::SLUG_MANAGER,
                'name' => 'مدير',
                'rank' => 50,
                'is_system' => true,
                'is_super_admin' => false,
                'serves_queue' => false,
            ],
            [
                'slug' => self::SLUG_TELLER,
                'name' => 'موظف',
                'rank' => 10,
                'is_system' => true,
                'is_super_admin' => false,
                'serves_queue' => true,
            ],
        ];
    }

    public static function seedSystemRoles(): void
    {
        foreach (self::systemDefinitions() as $definition) {
            self::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                $definition,
            );
        }

        self::flushCatalog();
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCatalog());
        static::deleted(fn () => self::flushCatalog());
    }
}

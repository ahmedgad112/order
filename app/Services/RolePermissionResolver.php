<?php

namespace App\Services;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\RolePermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RolePermissionResolver
{
    private const CACHE_KEY = 'role_permissions.map';

    private const CACHE_TTL_SECONDS = 300;

    public function allows(UserRole $role, Permission $permission): bool
    {
        if ($role === UserRole::SuperAdmin) {
            return true;
        }

        if ($permission->isLockedOffFor($role)) {
            return false;
        }

        $map = $this->map();
        $key = $this->mapKey($role, $permission);

        if (array_key_exists($key, $map)) {
            return $map[$key];
        }

        return $permission->defaultFor($role);
    }

    /**
     * @return array<string, bool>
     */
    public function map(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $map = [];

            foreach (RolePermission::query()->get(['role', 'permission', 'allowed']) as $row) {
                $map[$this->mapKey($row->role, $row->permission)] = $row->allowed;
            }

            return $map;
        });
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<array{
     *     role: string,
     *     role_label: string,
     *     permissions: list<array{
     *         permission: string,
     *         label: string,
     *         allowed: bool,
     *         editable: bool
     *     }>
     * }>
     */
    public function matrix(): array
    {
        return array_map(
            function (UserRole $role): array {
                return [
                    'role' => $role->value,
                    'role_label' => $role->label(),
                    'permissions' => array_map(
                        fn (Permission $permission): array => [
                            'permission' => $permission->value,
                            'label' => $permission->label(),
                            'allowed' => $this->allows($role, $permission),
                            'editable' => $permission->isEditableFor($role),
                        ],
                        Permission::cases(),
                    ),
                ];
            },
            UserRole::cases(),
        );
    }

    /**
     * @param  list<array{role: string, permission: string, allowed: bool}>  $rows
     */
    public function sync(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $role = UserRole::from($row['role']);
                $permission = Permission::from($row['permission']);

                if (! $permission->isEditableFor($role)) {
                    continue;
                }

                $allowed = (bool) $row['allowed'];

                if ($permission->isLockedOffFor($role)) {
                    $allowed = false;
                }

                RolePermission::query()->updateOrCreate(
                    [
                        'role' => $role->value,
                        'permission' => $permission->value,
                    ],
                    ['allowed' => $allowed],
                );
            }
        });

        $this->forget();
    }

    /**
     * Seed missing role/permission pairs from enum defaults.
     */
    public function seedDefaults(): void
    {
        foreach (UserRole::cases() as $role) {
            foreach (Permission::cases() as $permission) {
                RolePermission::query()->firstOrCreate(
                    [
                        'role' => $role->value,
                        'permission' => $permission->value,
                    ],
                    [
                        'allowed' => $permission->isLockedOffFor($role)
                            ? false
                            : $permission->defaultFor($role),
                    ],
                );
            }
        }

        $this->forget();
    }

    private function mapKey(UserRole $role, Permission $permission): string
    {
        return $role->value.'.'.$permission->value;
    }
}

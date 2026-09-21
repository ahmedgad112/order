<?php

namespace App\Services;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RolePermissionResolver
{
    private const CACHE_KEY = 'role_permissions.map';

    private const CACHE_TTL_SECONDS = 300;

    public function allows(Role|string $role, Permission $permission): bool
    {
        $roleModel = $role instanceof Role ? $role : Role::findBySlug($role);

        if (! $roleModel) {
            return false;
        }

        if ($roleModel->is_super_admin) {
            return true;
        }

        if ($permission->isLockedOffFor($roleModel)) {
            return false;
        }

        $map = $this->map();
        $key = $this->mapKey($roleModel->slug, $permission);

        if (array_key_exists($key, $map)) {
            return $map[$key];
        }

        return $permission->defaultFor($roleModel);
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
     *     is_system: bool,
     *     is_super_admin: bool,
     *     serves_queue: bool,
     *     permissions: list<array{
     *         permission: string,
     *         label: string,
     *         group: string,
     *         group_label: string,
     *         allowed: bool,
     *         editable: bool
     *     }>
     * }>
     */
    public function matrix(): array
    {
        return Role::catalog()
            ->map(function (Role $role): array {
                return [
                    'role' => $role->slug,
                    'role_label' => $role->name,
                    'is_system' => $role->is_system,
                    'is_super_admin' => $role->is_super_admin,
                    'serves_queue' => $role->serves_queue,
                    'permissions' => array_map(
                        fn (Permission $permission): array => [
                            'permission' => $permission->value,
                            'label' => $permission->label(),
                            'group' => $permission->group(),
                            'group_label' => $permission->groupLabel(),
                            'allowed' => $this->allows($role, $permission),
                            'editable' => $permission->isEditableFor($role),
                        ],
                        Permission::cases(),
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{role: string, permission: string, allowed: bool}>  $rows
     */
    public function sync(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $role = Role::findBySlug($row['role']);

                if (! $role) {
                    continue;
                }

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
                        'role' => $role->slug,
                        'permission' => $permission->value,
                    ],
                    ['allowed' => $allowed],
                );
            }
        });

        $this->forget();
    }

    /**
     * Seed missing role/permission pairs from defaults.
     */
    public function seedDefaults(): void
    {
        Role::seedSystemRoles();

        foreach (Role::catalog() as $role) {
            foreach (Permission::cases() as $permission) {
                RolePermission::query()->firstOrCreate(
                    [
                        'role' => $role->slug,
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

    /**
     * Ensure a role has permission rows for every known permission.
     */
    public function ensureRolePermissions(Role $role): void
    {
        foreach (Permission::cases() as $permission) {
            RolePermission::query()->firstOrCreate(
                [
                    'role' => $role->slug,
                    'permission' => $permission->value,
                ],
                [
                    'allowed' => $permission->isLockedOffFor($role)
                        ? false
                        : $permission->defaultFor($role),
                ],
            );
        }

        $this->forget();
    }

    /**
     * @return array<string, bool>
     */
    public function permissionMapFor(Role|string $role): array
    {
        $map = [];

        foreach (Permission::cases() as $permission) {
            $map[$permission->value] = $this->allows($role, $permission);
        }

        return $map;
    }

    private function mapKey(string $roleSlug, Permission|string $permission): string
    {
        $permissionValue = $permission instanceof Permission ? $permission->value : $permission;

        return $roleSlug.'.'.$permissionValue;
    }
}

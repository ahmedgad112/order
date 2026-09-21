<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\RolePermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminRoleController extends Controller
{
    public function __construct(private RolePermissionResolver $resolver) {}

    public function index(): JsonResponse
    {
        $roles = Role::catalog()->map(function (Role $role): array {
            return $role->toOption(withUsersCount: true);
        })->values()->all();

        return response()->json([
            'roles' => $roles,
            'matrix' => $this->resolver->matrix(),
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $slug = $this->uniqueSlug($data['slug'] ?? $data['name']);

        $role = Role::query()->create([
            'slug' => $slug,
            'name' => $data['name'],
            'rank' => (int) ($data['rank'] ?? 20),
            'is_system' => false,
            'is_super_admin' => false,
            'serves_queue' => (bool) ($data['serves_queue'] ?? false),
        ]);

        $this->resolver->ensureRolePermissions($role);

        if (! empty($data['permissions']) && is_array($data['permissions'])) {
            $this->resolver->sync(
                collect($data['permissions'])->map(fn (array $item): array => [
                    'role' => $role->slug,
                    'permission' => $item['permission'],
                    'allowed' => (bool) $item['allowed'],
                ])->all()
            );
        }

        Role::flushCatalog();

        return response()->json([
            'message' => 'تم إنشاء الدور بنجاح.',
            'role' => $role->fresh()->toOption(withUsersCount: true),
            'matrix' => $this->resolver->matrix(),
        ], 201);
    }

    public function update(UpdateRoleRequest $request, string $role): JsonResponse
    {
        $roleModel = Role::findBySlugOrFail($role);
        $data = $request->validated();

        if ($roleModel->is_super_admin) {
            throw ValidationException::withMessages([
                'role' => 'لا يمكن تعديل دور السوبر أدمن.',
            ]);
        }

        if ($roleModel->is_system) {
            unset($data['serves_queue'], $data['rank'], $data['name']);
        }

        if (isset($data['name']) && ! $roleModel->is_system) {
            $roleModel->name = $data['name'];
        }

        if (array_key_exists('serves_queue', $data) && ! $roleModel->is_system) {
            $roleModel->serves_queue = (bool) $data['serves_queue'];
        }

        if (isset($data['rank']) && ! $roleModel->is_system) {
            $roleModel->rank = (int) $data['rank'];
        }

        $roleModel->save();
        Role::flushCatalog();

        return response()->json([
            'message' => 'تم تحديث الدور بنجاح.',
            'role' => $roleModel->fresh()->toOption(withUsersCount: true),
            'matrix' => $this->resolver->matrix(),
        ]);
    }

    public function destroy(string $role): JsonResponse
    {
        $roleModel = Role::findBySlugOrFail($role);

        if ($roleModel->is_system || $roleModel->is_super_admin) {
            throw ValidationException::withMessages([
                'role' => 'لا يمكن حذف الأدوار الأساسية للنظام.',
            ]);
        }

        $usersCount = User::query()->where('role', $roleModel->slug)->count();

        if ($usersCount > 0) {
            throw ValidationException::withMessages([
                'role' => 'لا يمكن حذف الدور لوجود مستخدمين مرتبطين به.',
            ]);
        }

        DB::transaction(function () use ($roleModel): void {
            RolePermission::query()->where('role', $roleModel->slug)->delete();
            $roleModel->delete();
        });

        $this->resolver->forget();
        Role::flushCatalog();

        return response()->json([
            'message' => 'تم حذف الدور بنجاح.',
            'matrix' => $this->resolver->matrix(),
        ]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'matrix' => $this->resolver->matrix(),
        ]);
    }

    public function syncPermissions(UpdateRolePermissionsRequest $request): JsonResponse
    {
        $this->resolver->sync($request->validated('permissions'));

        return response()->json([
            'message' => 'تم تحديث صلاحيات الأدوار بنجاح.',
            'matrix' => $this->resolver->matrix(),
        ]);
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source, '_');

        if ($base === '') {
            $base = 'role_'.Str::lower(Str::random(6));
        }

        $slug = $base;
        $suffix = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $base.'_'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}

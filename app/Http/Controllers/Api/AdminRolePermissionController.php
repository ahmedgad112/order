<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Services\RolePermissionResolver;
use Illuminate\Http\JsonResponse;

/**
 * @deprecated Prefer AdminRoleController; kept for backward-compatible method names.
 */
class AdminRolePermissionController extends Controller
{
    public function __construct(private RolePermissionResolver $resolver) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'matrix' => $this->resolver->matrix(),
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request): JsonResponse
    {
        $this->resolver->sync($request->validated('permissions'));

        return response()->json([
            'message' => 'تم تحديث صلاحيات الأدوار بنجاح.',
            'matrix' => $this->resolver->matrix(),
        ]);
    }
}

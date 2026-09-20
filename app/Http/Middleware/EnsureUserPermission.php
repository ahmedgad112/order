<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

class EnsureUserPermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        foreach ($permissions as $permissionValue) {
            try {
                $permission = Permission::from($permissionValue);
            } catch (ValueError) {
                return response()->json(['message' => 'ليس لديك صلاحية للوصول.'], 403);
            }

            if (! $user->allows($permission)) {
                return response()->json(['message' => 'ليس لديك صلاحية للوصول.'], 403);
            }
        }

        return $next($request);
    }
}

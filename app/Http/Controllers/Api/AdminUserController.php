<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();

        $query = User::query()->orderBy('name');

        if ($actor->isManager()) {
            $query->where('role', UserRole::Teller);
        }

        return response()->json([
            'users' => UserResource::collection($query->get()),
            'queue_lanes' => RequestType::lanePayload(),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = UserRole::from($data['role']);

        if (! $request->user()->canAssignRole($role)) {
            throw ValidationException::withMessages([
                'role' => 'ليس لديك صلاحية لإنشاء هذا الدور.',
            ]);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $role,
            'counter_name' => $role === UserRole::Teller ? ($data['counter_name'] ?? null) : null,
            'queue_lanes' => $role === UserRole::Teller
                ? array_values(array_unique($data['queue_lanes'] ?? RequestType::laneValues()))
                : null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'تم إنشاء المستخدم بنجاح.',
            'user' => new UserResource($user),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $admin = $request->user();
        $data = $request->validated();

        if (! $admin->canManageUser($user) && $user->id !== $admin->id) {
            throw ValidationException::withMessages([
                'user' => 'ليس لديك صلاحية لتعديل هذا المستخدم.',
            ]);
        }

        if ($user->id === $admin->id) {
            if (isset($data['is_active']) && ! $data['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => 'لا يمكنك تعطيل حسابك الخاص.',
                ]);
            }

            if (isset($data['role']) && $data['role'] !== $admin->role->value) {
                throw ValidationException::withMessages([
                    'role' => 'لا يمكنك تغيير دورك الخاص.',
                ]);
            }
        }

        if (isset($data['role'])) {
            $newRole = UserRole::from($data['role']);

            if ($user->id !== $admin->id && ! $admin->canAssignRole($newRole)) {
                throw ValidationException::withMessages([
                    'role' => 'ليس لديك صلاحية لتعيين هذا الدور.',
                ]);
            }

            if ($newRole === UserRole::Teller && empty($data['counter_name']) && empty($user->counter_name)) {
                throw ValidationException::withMessages([
                    'counter_name' => 'اسم الشباك مطلوب للموظفين.',
                ]);
            }
        }

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $nextRole = isset($data['role']) ? UserRole::from($data['role']) : $user->role;

        if (isset($data['role'])) {
            $data['role'] = $nextRole;
        }

        if ($nextRole !== UserRole::Teller) {
            $data['counter_name'] = null;
            $data['queue_lanes'] = null;
        } elseif (array_key_exists('queue_lanes', $data)) {
            $data['queue_lanes'] = array_values(array_unique($data['queue_lanes']));
        } else {
            unset($data['queue_lanes']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'تم تحديث المستخدم بنجاح.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'لا يمكنك حذف حسابك الخاص.',
            ]);
        }

        if (! $request->user()->canManageUser($user)) {
            throw ValidationException::withMessages([
                'user' => 'ليس لديك صلاحية لتعطيل هذا المستخدم.',
            ]);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'تم تعطيل المستخدم بنجاح.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}

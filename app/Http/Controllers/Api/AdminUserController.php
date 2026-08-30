<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'users' => UserResource::collection($users),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::from($data['role']),
            'counter_name' => $data['counter_name'] ?? null,
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

        if ($user->id === $admin->id) {
            if (isset($data['is_active']) && ! $data['is_active']) {
                throw ValidationException::withMessages([
                    'is_active' => 'لا يمكنك تعطيل حسابك الخاص.',
                ]);
            }

            if (isset($data['role']) && $data['role'] !== UserRole::Admin->value) {
                throw ValidationException::withMessages([
                    'role' => 'لا يمكنك تغيير دورك الخاص.',
                ]);
            }
        }

        if (isset($data['role']) && $data['role'] === UserRole::Teller->value && empty($data['counter_name']) && empty($user->counter_name)) {
            throw ValidationException::withMessages([
                'counter_name' => 'اسم الشباك مطلوب للموظفين.',
            ]);
        }

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (isset($data['role'])) {
            $data['role'] = UserRole::from($data['role']);
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

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'تم تعطيل المستخدم بنجاح.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}

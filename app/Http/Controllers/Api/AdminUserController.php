<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStoreUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\ProcessService;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    private const BULK_DEFAULT_PASSWORD = '123456789';

    private const BULK_EMAIL_DOMAIN = 'queue.local';

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
            'process_steps' => ProcessService::assignablePayload(),
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
            'process_steps' => $role === UserRole::Teller
                ? array_values(array_unique($data['process_steps'] ?? ProcessService::assignableValues()))
                : null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'تم إنشاء المستخدم بنجاح.',
            'user' => new UserResource($user),
        ], 201);
    }

    public function bulkStore(BulkStoreUsersRequest $request): JsonResponse
    {
        if (! $request->user()->canAssignRole(UserRole::Teller)) {
            throw ValidationException::withMessages([
                'role' => 'ليس لديك صلاحية لإنشاء هذا الدور.',
            ]);
        }

        $data = $request->validated();
        $baseName = trim((string) ($data['base_name'] ?? '')) !== ''
            ? trim((string) $data['base_name'])
            : 'موظف';
        $counterBase = trim((string) ($data['counter_base'] ?? '')) !== ''
            ? trim((string) $data['counter_base'])
            : 'شباك';
        $count = (int) $data['count'];
        $queueLanes = array_values(array_unique($data['queue_lanes'] ?? RequestType::laneValues()));
        $processSteps = array_values(array_unique($data['process_steps'] ?? ProcessService::assignableValues()));
        $hashedPassword = Hash::make(self::BULK_DEFAULT_PASSWORD);

        $users = DB::transaction(function () use ($count, $baseName, $counterBase, $queueLanes, $processSteps, $hashedPassword) {
            $created = [];
            $nextIndex = $this->nextBulkTellerIndex();

            while (count($created) < $count) {
                $email = 'teller'.$nextIndex.'@'.self::BULK_EMAIL_DOMAIN;

                if (User::query()->where('email', $email)->exists()) {
                    $nextIndex++;

                    continue;
                }

                $created[] = User::query()->create([
                    'name' => $baseName.' '.$nextIndex,
                    'email' => $email,
                    'password' => $hashedPassword,
                    'role' => UserRole::Teller,
                    'counter_name' => $counterBase.' '.$nextIndex,
                    'queue_lanes' => $queueLanes,
                    'process_steps' => $processSteps,
                    'is_active' => true,
                ]);

                $nextIndex++;
            }

            return $created;
        });

        return response()->json([
            'message' => 'تم إنشاء '.count($users).' مستخدم بنجاح.',
            'created' => count($users),
            'default_password' => self::BULK_DEFAULT_PASSWORD,
            'users' => UserResource::collection(collect($users)),
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
            $data['process_steps'] = null;
        } else {
            if (array_key_exists('queue_lanes', $data)) {
                $data['queue_lanes'] = array_values(array_unique($data['queue_lanes']));
            } else {
                unset($data['queue_lanes']);
            }

            if (array_key_exists('process_steps', $data)) {
                $data['process_steps'] = array_values(array_unique($data['process_steps']));
            } else {
                unset($data['process_steps']);
            }
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
                'user' => 'ليس لديك صلاحية لحذف هذا المستخدم.',
            ]);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'تم حذف المستخدم بنجاح.',
        ]);
    }

    private function nextBulkTellerIndex(): int
    {
        $maxIndex = User::query()
            ->where('email', 'like', 'teller%@'.self::BULK_EMAIL_DOMAIN)
            ->pluck('email')
            ->map(fn (string $email): int => (int) preg_replace('/\D/', '', Str::before($email, '@')))
            ->max();

        return ($maxIndex ?? 0) + 1;
    }
}

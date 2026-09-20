<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\ProcessStep;
use App\Enums\UserRole;
use App\Services\RolePermissionResolver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'counter_name', 'queue_lanes', 'process_steps', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'queue_lanes' => 'array',
            'process_steps' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isAdmin(): bool
    {
        return $this->role->canAccessAdminPanel();
    }

    public function isTeller(): bool
    {
        return $this->role === UserRole::Teller;
    }

    public function canManageUsers(): bool
    {
        return $this->allows(Permission::ManageUsers);
    }

    public function canControlSystem(): bool
    {
        return $this->allows(Permission::ControlSystem);
    }

    public function canEditTickets(): bool
    {
        return $this->allows(Permission::EditTickets);
    }

    public function canDeleteTickets(): bool
    {
        return $this->allows(Permission::DeleteTickets);
    }

    public function allows(Permission $permission): bool
    {
        return app(RolePermissionResolver::class)->allows($this->role, $permission);
    }

    public function canAssignRole(UserRole $role): bool
    {
        return $this->role->canAssign($role);
    }

    public function canManageUser(User $user): bool
    {
        return $this->role->canManage($user->role);
    }

    /**
     * @return list<string>
     */
    public function queueLaneValues(): array
    {
        if (! $this->isTeller()) {
            return RequestType::laneValues();
        }

        $stored = $this->queue_lanes;

        if (! is_array($stored)) {
            return RequestType::laneValues();
        }

        return array_values(array_intersect($stored, RequestType::laneValues()));
    }

    public function servesQueueLane(string $lane): bool
    {
        return in_array($lane, $this->queueLaneValues(), true);
    }

    public function constrainsTicketsToAssignedLanes(): bool
    {
        return $this->isTeller();
    }

    /**
     * @return list<string>
     */
    public function processStepValues(): array
    {
        $assignable = ProcessService::assignableValues();

        if ($assignable === []) {
            $assignable = ProcessStep::assignableValues();
        }

        if (! $this->isTeller()) {
            return $assignable;
        }

        $stored = $this->process_steps;

        if (! is_array($stored)) {
            return $assignable;
        }

        return array_values(array_intersect($stored, $assignable));
    }

    public function canPerformProcessStep(string $step): bool
    {
        return in_array($step, $this->processStepValues(), true);
    }

    public function constrainsProcessSteps(): bool
    {
        return $this->isTeller();
    }
}

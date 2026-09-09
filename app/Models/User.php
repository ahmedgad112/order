<?php

namespace App\Models;

use App\Enums\QueueLane;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'counter_name', 'queue_lanes', 'is_active'])]
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
        return $this->role->canManageUsers();
    }

    public function canControlSystem(): bool
    {
        return $this->role->canControlSystem();
    }

    public function canEditTickets(): bool
    {
        return $this->role->canEditTickets();
    }

    public function canDeleteTickets(): bool
    {
        return $this->role->canDeleteTickets();
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
            return QueueLane::values();
        }

        $stored = $this->queue_lanes;

        if (! is_array($stored)) {
            return QueueLane::values();
        }

        return array_values(array_intersect($stored, QueueLane::values()));
    }

    public function servesQueueLane(QueueLane|string $lane): bool
    {
        $value = $lane instanceof QueueLane ? $lane->value : $lane;

        return in_array($value, $this->queueLaneValues(), true);
    }

    public function constrainsTicketsToAssignedLanes(): bool
    {
        return $this->isTeller();
    }
}

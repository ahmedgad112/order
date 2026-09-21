<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\ProcessStep;
use App\Services\RolePermissionResolver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'counter_name', 'queue_lanes', 'process_steps', 'assigned_faculties', 'is_active'])]
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
            'queue_lanes' => 'array',
            'process_steps' => 'array',
            'assigned_faculties' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    public function roleDefinition(): Role
    {
        $role = Role::findBySlug($this->role);

        if (! $role) {
            abort(500, 'دور المستخدم غير معرّف في النظام.');
        }

        return $role;
    }

    public function roleLabel(): string
    {
        return $this->roleDefinition()->name;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleDefinition()->is_super_admin;
    }

    public function isManager(): bool
    {
        return $this->role === Role::SLUG_MANAGER;
    }

    public function isAdmin(): bool
    {
        return $this->allows(Permission::AccessAdminPanel);
    }

    public function isTeller(): bool
    {
        return $this->roleDefinition()->serves_queue;
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

    public function canRestoreTickets(): bool
    {
        return $this->allows(Permission::RestoreTickets);
    }

    public function canManageRoles(): bool
    {
        return $this->allows(Permission::ManageRoles);
    }

    public function allows(Permission $permission): bool
    {
        return app(RolePermissionResolver::class)->allows($this->role, $permission);
    }

    public function canAssignRole(Role|string $role): bool
    {
        $target = $role instanceof Role ? $role : Role::findBySlug($role);

        if (! $target) {
            return false;
        }

        return $this->roleDefinition()->canAssign($target);
    }

    public function canManageUser(User $user): bool
    {
        return $this->roleDefinition()->canManage($user->roleDefinition());
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

    /**
     * Process-step keys used to narrow the teller ticket list.
     * Returns null when the teller is assigned every available step.
     *
     * @return list<string>|null
     */
    public function constrainedTicketProcessSteps(): ?array
    {
        if (! $this->constrainsProcessSteps()) {
            return null;
        }

        $assigned = $this->processStepValues();
        $all = ProcessService::assignableValues();

        if ($all === []) {
            $all = ProcessStep::assignableValues();
        }

        if ($assigned === [] || array_diff($all, $assigned) === []) {
            return null;
        }

        $filterKeys = [];

        foreach ($assigned as $slug) {
            $service = ProcessService::findBySlug($slug);
            $key = $service?->system_key ?? $slug;

            if ($key === 'completed' || in_array($key, ProcessStep::values(), true)) {
                $filterKeys[] = $key;
            }
        }

        return array_values(array_unique($filterKeys));
    }

    /**
     * @return list<string>
     */
    public function assignedFacultyValues(): array
    {
        if (! $this->isTeller()) {
            return Faculty::slugs();
        }

        $stored = $this->assigned_faculties;

        if (! is_array($stored)) {
            return Faculty::slugs();
        }

        return array_values(array_intersect($stored, Faculty::slugs()));
    }

    public function servesFaculty(?string $facultySlug): bool
    {
        if (blank($facultySlug)) {
            return false;
        }

        return in_array($facultySlug, $this->assignedFacultyValues(), true);
    }

    public function constrainsTicketsToAssignedFaculties(): bool
    {
        return $this->isTeller();
    }
}

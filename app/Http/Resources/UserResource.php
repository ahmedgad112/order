<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'counter_name' => $this->counter_name,
            'queue_lanes' => $this->isTeller()
                ? RequestType::lanePayload($this->queueLaneValues())
                : [],
            'is_active' => $this->is_active,
            'assignable_roles' => array_map(
                fn (UserRole $role): array => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ],
                $this->role->assignableRoles(),
            ),
            'permissions' => [
                'manage_users' => $this->role->canManageUsers(),
                'control_system' => $this->role->canControlSystem(),
                'edit_tickets' => $this->role->canEditTickets(),
                'delete_tickets' => $this->role->canDeleteTickets(),
            ],
        ];
    }
}

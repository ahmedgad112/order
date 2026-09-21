<?php

namespace App\Http\Resources;

use App\Models\Faculty;
use App\Models\ProcessService;
use App\Models\RequestType;
use App\Models\User;
use App\Services\RolePermissionResolver;
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
        $role = $this->roleDefinition();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role->slug,
            'role_label' => $role->name,
            'serves_queue' => $role->serves_queue,
            'counter_name' => $this->counter_name,
            'queue_lanes' => $this->isTeller()
                ? RequestType::lanePayload($this->queueLaneValues())
                : [],
            'process_steps' => $this->isTeller()
                ? array_values(array_filter(
                    ProcessService::assignablePayload($this->processStepValues()),
                    fn (array $item): bool => $item['enabled'] === true,
                ))
                : [],
            'assigned_faculties' => $this->isTeller()
                ? Faculty::assignmentPayload($this->assignedFacultyValues())
                : [],
            'is_active' => $this->is_active,
            'assignable_roles' => array_map(
                fn ($assignable): array => [
                    'value' => $assignable->slug,
                    'label' => $assignable->name,
                    'serves_queue' => $assignable->serves_queue,
                ],
                $role->assignableRoles(),
            ),
            'permissions' => app(RolePermissionResolver::class)->permissionMapFor($role),
        ];
    }
}

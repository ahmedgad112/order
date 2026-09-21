<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RolePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_custom_role_with_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/roles', [
            'name' => 'مشرف أرشيف',
            'serves_queue' => false,
            'rank' => 25,
            'permissions' => [
                [
                    'permission' => Permission::AccessRegistrations->value,
                    'allowed' => true,
                ],
                [
                    'permission' => Permission::AccessAdminPanel->value,
                    'allowed' => true,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'تم إنشاء الدور بنجاح.')
            ->assertJsonPath('role.label', 'مشرف أرشيف')
            ->assertJsonPath('role.serves_queue', false);

        $role = Role::query()->where('name', 'مشرف أرشيف')->first();

        $this->assertNotNull($role);
        $this->assertTrue(
            app(RolePermissionResolver::class)->allows($role, Permission::AccessRegistrations)
        );
        $this->assertTrue(
            app(RolePermissionResolver::class)->allows($role, Permission::AccessAdminPanel)
        );
    }

    public function test_custom_role_user_is_blocked_from_ungranted_routes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/roles', [
            'name' => 'مراجع فقط',
            'permissions' => [
                [
                    'permission' => Permission::AccessRegistrations->value,
                    'allowed' => true,
                ],
            ],
        ])->assertCreated();

        $role = Role::query()->where('name', 'مراجع فقط')->firstOrFail();

        $reviewer = User::factory()->create([
            'role' => $role->slug,
            'counter_name' => null,
            'queue_lanes' => null,
            'process_steps' => null,
            'assigned_faculties' => null,
        ]);

        Sanctum::actingAs($reviewer);

        $this->getJson('/api/admin/tickets')->assertOk();
        $this->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/teller/queue-status')->assertForbidden();
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/admin/roles/'.Role::SLUG_TELLER)
            ->assertUnprocessable();
    }

    public function test_manager_cannot_create_roles(): void
    {
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/roles', [
            'name' => 'دور غير مسموح',
        ])->assertForbidden();
    }
}

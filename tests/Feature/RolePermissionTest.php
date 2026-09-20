<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\College;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\RolePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_match_user_role_enum_behavior(): void
    {
        $resolver = app(RolePermissionResolver::class);

        $this->assertTrue($resolver->allows(UserRole::SuperAdmin, Permission::ControlSystem));
        $this->assertTrue($resolver->allows(UserRole::SuperAdmin, Permission::DeleteTickets));
        $this->assertFalse($resolver->allows(UserRole::Manager, Permission::ControlSystem));
        $this->assertTrue($resolver->allows(UserRole::Manager, Permission::ManageUsers));
        $this->assertTrue($resolver->allows(UserRole::Manager, Permission::EditTickets));
        $this->assertFalse($resolver->allows(UserRole::Manager, Permission::DeleteTickets));
        $this->assertFalse($resolver->allows(UserRole::Teller, Permission::ManageUsers));
        $this->assertFalse($resolver->allows(UserRole::Teller, Permission::ControlSystem));
        $this->assertFalse($resolver->allows(UserRole::Teller, Permission::EditTickets));
        $this->assertFalse($resolver->allows(UserRole::Teller, Permission::DeleteTickets));
    }

    public function test_super_admin_always_allowed_even_when_database_row_is_off(): void
    {
        RolePermission::query()->create([
            'role' => UserRole::SuperAdmin->value,
            'permission' => Permission::ControlSystem->value,
            'allowed' => false,
        ]);

        app(RolePermissionResolver::class)->forget();

        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($admin->canControlSystem());
    }

    public function test_manager_gains_control_system_when_permission_enabled(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->create();

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/role-permissions', [
            'permissions' => [
                [
                    'role' => UserRole::Manager->value,
                    'permission' => Permission::ControlSystem->value,
                    'allowed' => true,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث صلاحيات الأدوار بنجاح.');

        $this->assertTrue($manager->fresh()->canControlSystem());

        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/system/open')
            ->assertOk();
    }

    public function test_manager_loses_control_system_when_permission_disabled_after_grant(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->create();

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/role-permissions', [
            'permissions' => [
                [
                    'role' => UserRole::Manager->value,
                    'permission' => Permission::ControlSystem->value,
                    'allowed' => true,
                ],
            ],
        ])->assertOk();

        $this->putJson('/api/admin/role-permissions', [
            'permissions' => [
                [
                    'role' => UserRole::Manager->value,
                    'permission' => Permission::ControlSystem->value,
                    'allowed' => false,
                ],
            ],
        ])->assertOk();

        $this->assertFalse($manager->fresh()->canControlSystem());

        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/system/open')->assertForbidden();
    }

    public function test_teller_cannot_be_granted_control_system_via_api(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/role-permissions', [
            'permissions' => [
                [
                    'role' => UserRole::Teller->value,
                    'permission' => Permission::ControlSystem->value,
                    'allowed' => true,
                ],
            ],
        ])->assertOk();

        $this->assertFalse(
            app(RolePermissionResolver::class)->allows(UserRole::Teller, Permission::ControlSystem)
        );

        $teller = User::factory()->teller()->create();
        $this->assertFalse($teller->canControlSystem());
    }

    public function test_teller_can_edit_tickets_when_permission_enabled(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 8,
            'full_name' => 'الاسم الأصلي',
            'national_id' => '29501011234567',
            'order_number' => '123456789',
        ]);

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/role-permissions', [
            'permissions' => [
                [
                    'role' => UserRole::Teller->value,
                    'permission' => Permission::EditTickets->value,
                    'allowed' => true,
                ],
            ],
        ])->assertOk();

        $this->assertTrue($teller->fresh()->canEditTickets());

        Sanctum::actingAs($teller);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => 'اسم معدّل من الموظف',
            'national_id' => '29501017654321',
            'request_type' => 'nomination_card',
            'college' => College::InformationTechnology,
            'order_number' => '987654321',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.full_name', 'اسم معدّل من الموظف');
    }

    public function test_super_admin_can_view_role_permissions_matrix(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/role-permissions')
            ->assertOk()
            ->assertJsonStructure([
                'matrix' => [
                    [
                        'role',
                        'role_label',
                        'permissions' => [
                            [
                                'permission',
                                'label',
                                'allowed',
                                'editable',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    public function test_manager_cannot_view_or_update_role_permissions(): void
    {
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->getJson('/api/admin/role-permissions')->assertForbidden();

        $this->putJson('/api/admin/role-permissions', [
            'permissions' => [
                [
                    'role' => UserRole::Manager->value,
                    'permission' => Permission::DeleteTickets->value,
                    'allowed' => true,
                ],
            ],
        ])->assertForbidden();
    }

    public function test_me_endpoint_reflects_updated_role_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->create();

        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/role-permissions', [
            'permissions' => [
                [
                    'role' => UserRole::Manager->value,
                    'permission' => Permission::DeleteTickets->value,
                    'allowed' => true,
                ],
            ],
        ])->assertOk();

        Sanctum::actingAs($manager);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.permissions.delete_tickets', true)
            ->assertJsonPath('user.permissions.control_system', false);
    }
}

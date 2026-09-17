<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_an_employee(): void
    {
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/users', [
            'name' => 'موظف جديد',
            'email' => 'staff@queue.local',
            'password' => 'password123',
            'role' => UserRole::Teller->value,
            'counter_name' => 'شباك 1',
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', UserRole::Teller->value)
            ->assertJsonPath('user.email', 'staff@queue.local')
            ->assertJsonPath('user.counter_name', 'شباك 1');

        $this->assertDatabaseHas('users', [
            'email' => 'staff@queue.local',
            'role' => UserRole::Teller->value,
            'counter_name' => 'شباك 1',
            'is_active' => true,
        ]);
    }

    public function test_manager_cannot_create_a_manager(): void
    {
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/users', [
            'name' => 'مدير آخر',
            'email' => 'other-manager@queue.local',
            'password' => 'password123',
            'role' => UserRole::Manager->value,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'ليس لديك صلاحية لإنشاء هذا الدور.');

        $this->assertDatabaseMissing('users', [
            'email' => 'other-manager@queue.local',
        ]);
    }

    public function test_manager_users_index_lists_employees_only(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->teller()->create([
            'name' => 'موظف الشباك',
        ]);
        User::factory()->superAdmin()->create();
        User::factory()->manager()->create([
            'email' => 'second-manager@queue.local',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(1, 'users')
            ->assertJsonPath('users.0.id', $employee->id)
            ->assertJsonPath('users.0.role', UserRole::Teller->value);
    }

    public function test_manager_cannot_update_a_super_admin(): void
    {
        $manager = User::factory()->manager()->create();
        $superAdmin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/users/'.$superAdmin->id, [
            'name' => 'اسم جديد',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.user.0', 'ليس لديك صلاحية لتعديل هذا المستخدم.');

        $this->assertSame($superAdmin->name, $superAdmin->fresh()->name);
    }

    public function test_manager_can_bulk_create_employees(): void
    {
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/users/bulk', [
            'base_name' => 'موظف',
            'count' => 3,
            'counter_base' => 'شباك',
        ])
            ->assertCreated()
            ->assertJsonPath('created', 3)
            ->assertJsonCount(3, 'users');

        $employee = User::query()->where('email', 'teller1@queue.local')->first();

        $this->assertNotNull($employee);
        $this->assertSame('موظف 1', $employee->name);
        $this->assertSame('شباك 1', $employee->counter_name);
        $this->assertSame(UserRole::Teller, $employee->role);
        $this->assertTrue($employee->is_active);
        $this->assertTrue(Hash::check('123456789', $employee->password));

        $this->assertDatabaseHas('users', [
            'email' => 'teller3@queue.local',
            'name' => 'موظف 3',
            'counter_name' => 'شباك 3',
        ]);
    }

    public function test_bulk_creation_continues_numbering_after_existing_employees(): void
    {
        User::factory()->teller()->create(['email' => 'teller2@queue.local']);
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/users/bulk', [
            'count' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('created', 2);

        $this->assertDatabaseHas('users', ['email' => 'teller3@queue.local']);
        $this->assertDatabaseHas('users', ['email' => 'teller4@queue.local']);
    }

    public function test_employee_cannot_bulk_create_employees(): void
    {
        $employee = User::factory()->teller()->create();
        Sanctum::actingAs($employee);

        $this->postJson('/api/admin/users/bulk', [
            'count' => 2,
        ])->assertForbidden();
    }

    public function test_employee_cannot_access_user_management(): void
    {
        $employee = User::factory()->teller()->create();
        Sanctum::actingAs($employee);

        $this->getJson('/api/admin/users')->assertForbidden();
        $this->postJson('/api/admin/users', [
            'name' => 'موظف جديد',
            'email' => 'new-staff@queue.local',
            'password' => 'password123',
            'role' => UserRole::Teller->value,
            'counter_name' => 'شباك 2',
        ])->assertForbidden();
    }
}

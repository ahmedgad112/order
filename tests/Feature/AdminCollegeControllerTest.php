<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\College;
use App\Models\QueueSystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminCollegeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_guest_creates_college(): void
    {
        $this->postJson('/api/admin/colleges', ['name' => 'تكنولوجيا جديدة'])
            ->assertUnauthorized();
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_returns_403_when_unprivileged_role_creates_college(UserRole $role): void
    {
        $user = match ($role) {
            UserRole::Teller => User::factory()->teller()->create(),
            UserRole::Manager => User::factory()->manager()->create(),
            UserRole::SuperAdmin => throw new \LogicException('Super admin can create colleges.'),
        };
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/colleges', ['name' => 'تكنولوجيا جديدة'])
            ->assertForbidden();

        $this->assertDatabaseMissing('colleges', ['name' => 'تكنولوجيا جديدة']);
    }

    public function test_super_admin_can_create_college_and_it_appears_in_public_status(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/colleges', [
            'name' => 'تكنولوجيا الطاقة المتجددة',
            'value' => 'renewable_energy',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'تم إضافة الكلية بنجاح.');

        $this->assertDatabaseHas('colleges', [
            'slug' => 'renewable_energy',
            'name' => 'تكنولوجيا الطاقة المتجددة',
            'is_active' => true,
        ]);

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonFragment([
                'value' => 'renewable_energy',
                'label' => 'تكنولوجيا الطاقة المتجددة',
            ]);
    }

    public function test_staff_can_issue_ticket_after_college_is_created(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/colleges', [
            'name' => 'تكنولوجيا الطاقة المتجددة',
            'value' => 'renewable_energy',
        ])->assertCreated();

        $this->issueTicketAsStaff()->assertCreated();
    }

    public function test_returns_422_when_college_name_is_missing(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/colleges', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'اسم الكلية مطلوب.');
    }

    public function test_super_admin_can_update_college_name(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $college = College::query()->where('slug', College::InformationTechnology)->firstOrFail();

        $this->putJson('/api/admin/colleges/'.$college->id, [
            'name' => 'تكنولوجيا المعلومات المحدثة',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث الكلية بنجاح.');

        $this->assertDatabaseHas('colleges', [
            'id' => $college->id,
            'slug' => College::InformationTechnology,
            'name' => 'تكنولوجيا المعلومات المحدثة',
        ]);
    }

    public function test_deactivated_college_is_hidden_from_public_status(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $college = College::query()->where('slug', College::RailwayTechnology)->firstOrFail();

        $this->deleteJson('/api/admin/colleges/'.$college->id)
            ->assertOk()
            ->assertJsonPath('message', 'تم إخفاء الكلية من شاشات التسجيل.');

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonMissing(['value' => College::RailwayTechnology]);
    }

    public function test_returns_422_when_deactivating_the_last_active_college(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        College::query()
            ->where('slug', '!=', College::InformationTechnology)
            ->update(['is_active' => false]);

        $last = College::query()->where('slug', College::InformationTechnology)->firstOrFail();

        $this->deleteJson('/api/admin/colleges/'.$last->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.college.0', 'يجب إبقاء كلية واحدة على الأقل ظاهرة لبطاقة الترشيح.');

        $this->assertTrue($last->fresh()->is_active);
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function unprivilegedRoles(): array
    {
        return [
            'teller' => [UserRole::Teller],
            'manager' => [UserRole::Manager],
        ];
    }
}

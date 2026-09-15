<?php

namespace Tests\Feature;

use App\Enums\StudentKind;
use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\QueueSystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminFacultyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_guest_creates_faculty(): void
    {
        $this->postJson('/api/admin/faculties', $this->facultyPayload())
            ->assertUnauthorized();
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_returns_403_when_unprivileged_role_creates_faculty(UserRole $role): void
    {
        $user = match ($role) {
            UserRole::Teller => User::factory()->teller()->create(),
            UserRole::Manager => User::factory()->manager()->create(),
            UserRole::SuperAdmin => throw new \LogicException('Super admin can create faculties.'),
        };
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/faculties', $this->facultyPayload())
            ->assertForbidden();

        $this->assertDatabaseMissing('faculties', ['name' => 'تجارة وإدارة']);
    }

    public function test_super_admin_can_create_faculty_and_it_appears_in_public_status(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/faculties', $this->facultyPayload())
            ->assertCreated()
            ->assertJsonPath('message', 'تم إضافة الكلية بنجاح.');

        $this->assertDatabaseHas('faculties', [
            'slug' => 'commerce',
            'name' => 'تجارة وإدارة',
            'seat_number_min_digits' => 8,
            'seat_number_max_digits' => 10,
            'is_active' => true,
        ]);

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonFragment([
                'value' => 'commerce',
                'label' => 'تجارة وإدارة',
                'seat_number_min_digits' => 8,
                'seat_number_max_digits' => 10,
            ]);
    }

    public function test_staff_can_issue_current_student_ticket_after_faculty_is_created(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/faculties', $this->facultyPayload())->assertCreated();

        $this->issueTicketAsStaff([
            'full_name' => 'سارة أحمد علي',
            'order_number' => '123456789',
            'request_type' => 'current_student',
        ])->assertCreated();

        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'سارة أحمد علي',
            'order_number' => '123456789',
            'student_kind' => StudentKind::CurrentStudent->value,
        ]);
    }

    public function test_returns_422_when_faculty_name_is_missing(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/faculties', [
            'seat_number_min_digits' => 7,
            'seat_number_max_digits' => 7,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'اسم الكلية مطلوب.');
    }

    public function test_returns_422_when_seat_number_max_is_below_min(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/faculties', [
            'name' => 'تجارة وإدارة',
            'seat_number_min_digits' => 9,
            'seat_number_max_digits' => 7,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.seat_number_max_digits.0', 'الحد الأقصى لأرقام الجلوس يجب ألا يقل عن الحد الأدنى.');
    }

    public function test_super_admin_can_update_faculty_name_and_seat_limits(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $faculty = Faculty::query()->where('slug', Faculty::IndustryEnergy)->firstOrFail();

        $this->putJson('/api/admin/faculties/'.$faculty->id, [
            'name' => 'صناعة وطاقة محدثة',
            'seat_number_min_digits' => 6,
            'seat_number_max_digits' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث الكلية بنجاح.');

        $this->assertDatabaseHas('faculties', [
            'id' => $faculty->id,
            'slug' => Faculty::IndustryEnergy,
            'name' => 'صناعة وطاقة محدثة',
            'seat_number_min_digits' => 6,
            'seat_number_max_digits' => 8,
        ]);
    }

    public function test_deactivated_faculty_is_hidden_from_public_status(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $faculty = Faculty::query()->where('slug', Faculty::HealthSciences)->firstOrFail();

        $this->deleteJson('/api/admin/faculties/'.$faculty->id)
            ->assertOk()
            ->assertJsonPath('message', 'تم إخفاء الكلية من شاشات التسجيل.');

        $this->assertDatabaseHas('faculties', [
            'id' => $faculty->id,
            'is_active' => false,
        ]);

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonMissing(['value' => Faculty::HealthSciences]);
    }

    public function test_returns_422_when_deactivating_the_last_active_faculty(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $health = Faculty::query()->where('slug', Faculty::HealthSciences)->firstOrFail();
        $industry = Faculty::query()->where('slug', Faculty::IndustryEnergy)->firstOrFail();

        $this->deleteJson('/api/admin/faculties/'.$health->id)->assertOk();

        $this->deleteJson('/api/admin/faculties/'.$industry->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.faculty.0', 'يجب إبقاء كلية واحدة على الأقل ظاهرة للطلاب الحاليين.');

        $this->assertTrue($industry->fresh()->is_active);
    }

    public function test_arabic_faculty_name_gets_a_generated_slug(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/faculties', [
            'name' => 'كلية مستحدثة',
            'seat_number_min_digits' => 7,
            'seat_number_max_digits' => 7,
        ])->assertCreated();

        $faculty = Faculty::query()->where('name', 'كلية مستحدثة')->first();

        $this->assertNotNull($faculty);
        $this->assertNotSame('', $faculty->slug);
        $this->assertSame(1, Faculty::query()->where('slug', $faculty->slug)->count());
    }

    public function test_admin_dashboard_includes_all_faculties(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        Faculty::query()->where('slug', Faculty::HealthSciences)->update(['is_active' => false]);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('faculties.0.value', Faculty::IndustryEnergy)
            ->assertJsonPath('faculties.0.is_active', true)
            ->assertJsonPath('faculties.1.value', Faculty::HealthSciences)
            ->assertJsonPath('faculties.1.is_active', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function facultyPayload(): array
    {
        return [
            'name' => 'تجارة وإدارة',
            'value' => 'commerce',
            'seat_number_min_digits' => 8,
            'seat_number_max_digits' => 10,
        ];
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

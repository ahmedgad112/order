<?php

namespace Tests\Feature;

use App\Enums\DocumentKind;
use App\Enums\StudentKind;
use App\Enums\UserRole;
use App\Models\College;
use App\Models\Faculty;
use App\Models\QueueSystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentKindAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newStudentPayload(array $overrides = []): array
    {
        return array_merge([
            'student_kind' => StudentKind::NewStudent->value,
            'full_name' => 'محمد أحمد علي',
            'national_id' => '29501011234567',
            'request_type' => 'nomination_card',
            'college' => College::InformationTechnology,
            'order_number' => '123456789',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function currentStudentPayload(array $overrides = []): array
    {
        return array_merge([
            'student_kind' => StudentKind::CurrentStudent->value,
            'full_name' => 'سارة أحمد علي',
            'college' => Faculty::IndustryEnergy,
            'department' => 'تكنولوجيا المعلومات',
            'seat_number' => '1234567',
            'document_kind' => DocumentKind::StudentCard->value,
            'document' => UploadedFile::fake()->image('card.jpg'),
        ], $overrides);
    }

    public function test_public_queue_status_includes_enabled_student_kinds(): void
    {
        QueueSystemSetting::current();

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('system.student_kinds.0.value', StudentKind::NewStudent->value)
            ->assertJsonPath('system.student_kinds.0.label', 'طالب جديد')
            ->assertJsonPath('system.student_kinds.0.enabled', true)
            ->assertJsonPath('system.student_kinds.1.value', StudentKind::CurrentStudent->value)
            ->assertJsonPath('system.student_kinds.1.label', 'طالب حالي (فرقة ثانية)')
            ->assertJsonPath('system.student_kinds.1.enabled', true);
    }

    public function test_super_admin_can_update_enabled_student_kinds(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/system/student-kinds', [
            'enabled_student_kinds' => [StudentKind::NewStudent->value],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث أنواع الطلاب في شاشة الاختيار.')
            ->assertJsonPath('system.student_kinds.0.enabled', true)
            ->assertJsonPath('system.student_kinds.1.enabled', false);

        $this->assertSame(
            [StudentKind::NewStudent->value],
            QueueSystemSetting::query()->first()?->enabled_student_kinds,
        );

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('system.student_kinds.1.enabled', false);
    }

    public function test_super_admin_can_close_all_student_kinds(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/system/student-kinds', [
            'enabled_student_kinds' => [],
        ])
            ->assertOk()
            ->assertJsonPath('system.student_kinds.0.enabled', false)
            ->assertJsonPath('system.student_kinds.1.enabled', false);

        $this->assertSame([], QueueSystemSetting::query()->first()?->enabled_student_kinds);
    }

    public function test_returns_401_when_guest_updates_student_kinds(): void
    {
        QueueSystemSetting::current();

        $this->putJson('/api/admin/system/student-kinds', [
            'enabled_student_kinds' => [StudentKind::NewStudent->value],
        ])->assertUnauthorized();
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_returns_403_when_unprivileged_role_updates_student_kinds(UserRole $role): void
    {
        QueueSystemSetting::current();
        $user = match ($role) {
            UserRole::Teller => User::factory()->teller()->create(),
            UserRole::Manager => User::factory()->manager()->create(),
            UserRole::SuperAdmin => throw new \LogicException('Super admin can update student kinds.'),
        };
        Sanctum::actingAs($user);

        $this->putJson('/api/admin/system/student-kinds', [
            'enabled_student_kinds' => [StudentKind::NewStudent->value],
        ])->assertForbidden();
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

    public function test_returns_422_when_student_kind_value_is_invalid(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/system/student-kinds', [
            'enabled_student_kinds' => ['not_a_student_kind'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['enabled_student_kinds.0']);

        $this->assertSame(
            ['نوع الطالب غير صحيح.'],
            $response->json('errors')['enabled_student_kinds.0'],
        );
    }

    public function test_returns_422_when_current_student_kind_is_disabled(): void
    {
        QueueSystemSetting::current()->update([
            'enabled_student_kinds' => [StudentKind::NewStudent->value],
        ]);

        $this->issueTicketAsStaff([
            'full_name' => 'سارة أحمد علي',
            'order_number' => '987654321',
            'request_type' => 'current_student',
            'college' => Faculty::IndustryEnergy,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['request_type'])
            ->assertJsonPath('errors.request_type.0', 'نوع الطلب غير متاح حالياً.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_new_student_kind_is_disabled(): void
    {
        QueueSystemSetting::current()->update([
            'enabled_student_kinds' => [StudentKind::CurrentStudent->value],
        ]);

        $this->issueTicketAsStaff([
            'full_name' => 'محمد أحمد علي',
            'order_number' => '123456789',
            'request_type' => 'nomination_card',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['request_type'])
            ->assertJsonPath('errors.request_type.0', 'نوع الطلب غير متاح حالياً.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_staff_can_issue_new_student_ticket_when_current_student_is_closed(): void
    {
        QueueSystemSetting::current()->update([
            'enabled_student_kinds' => [StudentKind::NewStudent->value],
        ]);

        $this->issueTicketAsStaff()
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT1');

        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'محمد أحمد علي',
            'student_kind' => StudentKind::NewStudent->value,
        ]);
    }

    public function test_staff_can_issue_current_student_ticket_when_new_student_is_closed(): void
    {
        QueueSystemSetting::current()->update([
            'enabled_student_kinds' => [StudentKind::CurrentStudent->value],
        ]);

        $this->issueTicketAsStaff([
            'full_name' => 'سارة أحمد علي',
            'order_number' => '987654321',
            'request_type' => 'current_student',
            'college' => Faculty::IndustryEnergy,
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'O1');

        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'سارة أحمد علي',
            'student_kind' => StudentKind::CurrentStudent->value,
            'college' => Faculty::IndustryEnergy,
        ]);
    }
}

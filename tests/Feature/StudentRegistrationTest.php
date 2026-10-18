<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\StudentRegistrationStatus;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Services\RolePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'أحمد محمود علي',
            'academic_number' => '2020123456',
            'national_id' => '29501011234567',
            'department' => 'تكنولوجيا المعلومات',
            'section_number' => 2,
            'student_group' => 'A',
        ], $overrides);
    }

    public function test_student_can_register_name_academic_number_department_and_section(): void
    {
        $this->postJson('/api/public/student-registrations', $this->payload([
            'full_name' => '  أحمد   محمود علي  ',
        ]))
            ->assertCreated()
            ->assertJsonPath('message', 'تم تسجيل بياناتك بنجاح.')
            ->assertJsonPath('registration.full_name', 'أحمد محمود علي')
            ->assertJsonPath('registration.academic_number', '2020123456')
            ->assertJsonPath('registration.national_id', '29501011234567')
            ->assertJsonPath('registration.student_group', 'A')
            ->assertJsonPath('registration.turn_number', 1)
            ->assertJsonPath('registration.status', 'waiting')
            ->assertJsonPath('registration.department', 'تكنولوجيا المعلومات')
            ->assertJsonPath('registration.section_number', 2);

        $this->assertDatabaseHas('student_registrations', [
            'full_name' => 'أحمد محمود علي',
            'academic_number' => '2020123456',
            'national_id' => '29501011234567',
            'student_group' => 'A',
            'turn_number' => 1,
            'status' => 'waiting',
            'department' => 'تكنولوجيا المعلومات',
            'section_number' => 2,
        ]);
    }

    public function test_arabic_digits_are_stored_as_western_digits(): void
    {
        $this->postJson('/api/public/student-registrations', $this->payload([
            'academic_number' => '٢٠٢٠ ١٢٣٤٥٦',
            'national_id' => '٢٩٥٠١٠١١٢٣٤٥٦٧',
            'section_number' => '٣',
            'student_group' => 'b',
        ]))
            ->assertCreated()
            ->assertJsonPath('registration.academic_number', '2020123456')
            ->assertJsonPath('registration.national_id', '29501011234567')
            ->assertJsonPath('registration.student_group', 'B')
            ->assertJsonPath('registration.section_number', 3);

        $this->assertDatabaseHas('student_registrations', [
            'academic_number' => '2020123456',
            'section_number' => 3,
        ]);
    }

    public function test_empty_registration_returns_required_field_messages(): void
    {
        $this->postJson('/api/public/student-registrations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'full_name' => 'اسم الطالب مطلوب.',
                'academic_number' => 'الرقم الأكاديمي مطلوب.',
                'national_id' => 'الرقم القومي مطلوب.',
                'department' => 'القسم مطلوب.',
                'section_number' => 'رقم السكشن مطلوب.',
                'student_group' => 'يجب اختيار المجموعة.',
            ]);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_short_name_is_rejected(): void
    {
        $this->postJson('/api/public/student-registrations', $this->payload([
            'full_name' => 'سم',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'full_name' => 'يجب أن يتكون الاسم من 3 أحرف على الأقل.',
            ]);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_short_department_is_rejected(): void
    {
        $this->postJson('/api/public/student-registrations', $this->payload([
            'department' => 'ا',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'department' => 'يجب أن يتكون اسم القسم من حرفين على الأقل.',
            ]);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_academic_number_with_letters_is_rejected(): void
    {
        $this->postJson('/api/public/student-registrations', $this->payload([
            'academic_number' => 'abc12345',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'academic_number' => 'يجب أن يتكون الرقم الأكاديمي من 4 إلى 20 رقمًا.',
            ]);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_section_zero_is_rejected(): void
    {
        $this->postJson('/api/public/student-registrations', $this->payload([
            'section_number' => 0,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'section_number' => 'رقم السكشن يجب أن يكون من 1 إلى 999.',
            ]);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_duplicate_academic_number_is_rejected(): void
    {
        StudentRegistration::factory()->create([
            'academic_number' => '2020123456',
            'full_name' => 'طالب سابق',
        ]);

        $this->postJson('/api/public/student-registrations', $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'academic_number' => 'هذا الرقم الأكاديمي مسجل بالفعل.',
            ]);

        $this->assertDatabaseCount('student_registrations', 1);
        $this->assertDatabaseHas('student_registrations', [
            'full_name' => 'طالب سابق',
            'academic_number' => '2020123456',
        ]);
    }

    public function test_posted_id_is_not_stored(): void
    {
        $this->postJson('/api/public/student-registrations', $this->payload([
            'id' => 999,
        ]))
            ->assertCreated()
            ->assertJsonPath('registration.academic_number', '2020123456');

        $this->assertDatabaseMissing('student_registrations', [
            'id' => 999,
        ]);
    }

    public function test_guest_listing_returns_401(): void
    {
        $this->getJson('/api/admin/student-registrations')
            ->assertUnauthorized();
    }

    public function test_teller_listing_returns_403(): void
    {
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->getJson('/api/admin/student-registrations')
            ->assertForbidden();
    }

    public function test_teller_download_returns_403(): void
    {
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->get('/api/admin/student-registrations/export')
            ->assertForbidden();
    }

    public function test_teller_delete_returns_403_and_keeps_the_registration(): void
    {
        $registration = StudentRegistration::factory()->create();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->deleteJson('/api/admin/student-registrations/'.$registration->id)
            ->assertForbidden();

        $this->assertModelExists($registration);
    }

    public function test_manager_can_list_one_section(): void
    {
        StudentRegistration::factory()->create([
            'full_name' => 'أحمد في الأول',
            'academic_number' => '1111222233',
            'department' => 'تكنولوجيا المعلومات',
            'section_number' => 1,
        ]);
        StudentRegistration::factory()->create([
            'full_name' => 'سارة في الثاني',
            'academic_number' => '2222333344',
            'department' => 'تكنولوجيا المعلومات',
            'section_number' => 2,
        ]);
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/admin/student-registrations?section_number=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('registrations.0.full_name', 'أحمد في الأول')
            ->assertJsonPath('registrations.0.section_number', 1);
    }

    public function test_searching_for_a_percent_sign_does_not_return_every_student(): void
    {
        StudentRegistration::factory()->create([
            'full_name' => 'أحمد محمود',
            'academic_number' => '3333444455',
        ]);
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/admin/student-registrations?search='.urlencode('%'))
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_manager_download_builds_a_sheet_per_section_and_escapes_cells(): void
    {
        StudentRegistration::factory()->create([
            'full_name' => '=1+1',
            'academic_number' => '1111222233',
            'department' => 'تكنولوجيا المعلومات',
            'section_number' => 1,
        ]);
        StudentRegistration::factory()->create([
            'full_name' => 'سارة <script>',
            'academic_number' => '2222333344',
            'department' => 'تكنولوجيا المعلومات',
            'section_number' => 2,
        ]);
        Sanctum::actingAs(User::factory()->manager()->create());

        $content = $this->get('/api/admin/student-registrations/export')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('الرقم القومي', $content);
        $this->assertStringContainsString('المجموعة', $content);
        $this->assertStringContainsString('ss:Name="تكنولوجيا المعلومات - 1"', $content);
        $this->assertStringContainsString('ss:Name="تكنولوجيا المعلومات - 2"', $content);
        $this->assertStringContainsString('ss:Type="String">&apos;=1+1</Data>', $content);
        $this->assertStringContainsString('سارة &lt;script&gt;', $content);
        $this->assertStringNotContainsString('<script>', $content);
        $this->assertStringContainsString('1111222233', $content);
        $this->assertStringContainsString('2222333344', $content);
    }

    public function test_manager_can_download_a_single_section(): void
    {
        StudentRegistration::factory()->create([
            'full_name' => 'أحمد في الأول',
            'academic_number' => '1111222233',
            'section_number' => 1,
        ]);
        StudentRegistration::factory()->create([
            'full_name' => 'سارة في الثاني',
            'academic_number' => '2222333344',
            'section_number' => 2,
        ]);
        Sanctum::actingAs(User::factory()->manager()->create());

        $content = $this->get('/api/admin/student-registrations/export?section_number=1')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('1111222233', $content);
        $this->assertStringContainsString('أحمد في الأول', $content);
        $this->assertStringNotContainsString('2222333344', $content);
        $this->assertStringNotContainsString('سارة في الثاني', $content);
    }

    public function test_manager_can_delete_a_registration_and_the_academic_number_can_be_used_again(): void
    {
        $registration = StudentRegistration::factory()->create([
            'academic_number' => '2020123456',
            'full_name' => 'طالب سابق',
        ]);
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->deleteJson('/api/admin/student-registrations/'.$registration->id)
            ->assertOk()
            ->assertJsonPath('message', 'تم حذف تسجيل الطالب.');

        $this->assertModelMissing($registration);

        $this->postJson('/api/public/student-registrations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('registration.academic_number', '2020123456');
    }

    public function test_short_national_id_is_rejected(): void
    {
        $this->postJson('/api/public/student-registrations', $this->payload([
            'national_id' => '12345',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'national_id' => 'يجب أن يتكون الرقم القومي من 14 رقمًا بالضبط.',
            ]);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_group_other_than_a_or_b_is_rejected(): void
    {
        $this->postJson('/api/public/student-registrations', $this->payload([
            'student_group' => 'C',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'student_group' => 'المجموعة يجب أن تكون A أو B.',
            ]);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_duplicate_national_id_is_rejected(): void
    {
        StudentRegistration::factory()->create([
            'national_id' => '29501011234567',
            'full_name' => 'طالب سابق',
        ]);

        $this->postJson('/api/public/student-registrations', $this->payload([
            'academic_number' => '2020999888',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'national_id' => 'هذا الرقم القومي مسجل بالفعل.',
            ]);

        $this->assertDatabaseCount('student_registrations', 1);
    }

    public function test_each_registration_gets_the_next_turn_for_the_day(): void
    {
        StudentRegistration::factory()->create([
            'service_date' => now('Africa/Cairo')->subDay()->toDateString(),
            'turn_number' => 8,
        ]);

        $this->postJson('/api/public/student-registrations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('registration.turn_number', 1);

        $this->postJson('/api/public/student-registrations', $this->payload([
            'full_name' => 'منى سعيد حسن',
            'academic_number' => '2020123457',
            'national_id' => '29501011234568',
        ]))
            ->assertCreated()
            ->assertJsonPath('registration.turn_number', 2);
    }

    public function test_manager_can_search_by_national_id(): void
    {
        StudentRegistration::factory()->create([
            'full_name' => 'أحمد محمود',
            'national_id' => '29501011234567',
        ]);
        StudentRegistration::factory()->create([
            'full_name' => 'سارة علي',
            'national_id' => '29501011234568',
        ]);
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/admin/student-registrations?search=29501011234567')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('registrations.0.full_name', 'أحمد محمود');
    }

    public function test_teller_lists_only_waiting_registrations(): void
    {
        StudentRegistration::factory()->create([
            'full_name' => 'في الانتظار',
            'turn_number' => 1,
            'status' => StudentRegistrationStatus::Waiting,
        ]);
        StudentRegistration::factory()->create([
            'full_name' => 'خلص',
            'turn_number' => 2,
            'status' => StudentRegistrationStatus::Completed,
        ]);
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->getJson('/api/teller/student-registrations')
            ->assertOk()
            ->assertJsonCount(1, 'registrations')
            ->assertJsonPath('registrations.0.full_name', 'في الانتظار');
    }

    public function test_teller_can_mark_a_registration_done(): void
    {
        $teller = User::factory()->teller()->create();
        $registration = StudentRegistration::factory()->create([
            'turn_number' => 4,
            'status' => StudentRegistrationStatus::Waiting,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/student-registrations/'.$registration->id.'/complete')
            ->assertOk()
            ->assertJsonPath('message', 'تم إنهاء الدور.')
            ->assertJsonPath('registration.status', 'completed')
            ->assertJsonPath('registration.status_label', 'تم');

        $registration->refresh();

        $this->assertSame(StudentRegistrationStatus::Completed, $registration->status);
        $this->assertNotNull($registration->completed_at);
        $this->assertSame($teller->id, $registration->completed_by);
    }

    public function test_marking_a_finished_registration_done_again_returns_422(): void
    {
        $registration = StudentRegistration::factory()->create([
            'status' => StudentRegistrationStatus::Completed,
        ]);
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/student-registrations/'.$registration->id.'/complete')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'registration' => 'تم إنهاء هذا الدور من قبل.',
            ]);
    }

    public function test_guest_cannot_mark_a_registration_done(): void
    {
        $registration = StudentRegistration::factory()->create();

        $this->postJson('/api/teller/student-registrations/'.$registration->id.'/complete')
            ->assertUnauthorized();

        $this->assertSame(StudentRegistrationStatus::Waiting, $registration->fresh()->status);
    }

    public function test_user_without_teller_access_cannot_mark_a_registration_done(): void
    {
        RolePermission::query()
            ->where('role', Role::SLUG_TELLER)
            ->where('permission', Permission::AccessTellerPanel->value)
            ->update(['allowed' => false]);
        app(RolePermissionResolver::class)->forget();

        $registration = StudentRegistration::factory()->create();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/student-registrations/'.$registration->id.'/complete')
            ->assertForbidden();

        $this->assertSame(StudentRegistrationStatus::Waiting, $registration->fresh()->status);
    }
}

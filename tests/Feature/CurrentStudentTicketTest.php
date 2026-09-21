<?php

namespace Tests\Feature;

use App\Enums\DocumentKind;
use App\Enums\StudentKind;
use App\Enums\TicketStatus;
use App\Models\Faculty;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrentStudentTicketTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_staff_can_issue_current_student_ticket_with_name_order_and_type(): void
    {
        QueueSystemSetting::current();

        $this->issueTicketAsStaff([
            'full_name' => 'سارة أحمد علي',
            'order_number' => '987654321',
            'request_type' => 'current_student',
            'college' => Faculty::IndustryEnergy,
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'O1')
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value)
            ->assertJsonPath('ticket.student_kind', StudentKind::CurrentStudent->value);

        $ticket = QueueTicket::query()->first();

        $this->assertNotNull($ticket);
        $this->assertSame(StudentKind::CurrentStudent, $ticket->student_kind);
        $this->assertSame('سارة أحمد علي', $ticket->full_name);
        $this->assertSame('987654321', $ticket->order_number);
        $this->assertNull($ticket->request_type);
        $this->assertNull($ticket->national_id);
        $this->assertSame(Faculty::IndustryEnergy, $ticket->college);
        $this->assertNull($ticket->document_path);
    }

    public function test_new_and_current_students_keep_independent_ticket_sequences(): void
    {
        QueueSystemSetting::current();

        $this->issueTicketAsStaff([
            'full_name' => 'محمد أحمد علي',
            'order_number' => '123456789',
            'request_type' => 'nomination_card',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT1');

        $this->issueTicketAsStaff([
            'full_name' => 'سارة أحمد علي',
            'order_number' => '123456788',
            'request_type' => 'current_student',
            'college' => Faculty::IndustryEnergy,
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'O1');

        $this->issueTicketAsStaff([
            'full_name' => 'علي محمود حسن',
            'order_number' => '123456787',
            'request_type' => 'nomination_card',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT2');

        $this->issueTicketAsStaff([
            'full_name' => 'منى سعيد',
            'order_number' => '123456786',
            'request_type' => 'current_student',
            'college' => Faculty::HealthSciences,
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'O2');
    }

    public function test_admin_can_search_current_student_ticket_by_code(): void
    {
        QueueSystemSetting::current();
        QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'سارة بالكود',
        ]);
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => 'nomination_card',
            'full_name' => 'طالب جديد بنفس الرقم',
        ]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/admin/tickets?search=O1')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'سارة بالكود')
            ->assertJsonPath('tickets.0.ticket_number', 'O1');
    }

    public function test_returns_403_when_guest_issues_current_student_ticket_from_public_endpoint(): void
    {
        QueueSystemSetting::current();

        $this->post('/api/public/tickets', $this->currentStudentPayload(), [
            'Accept' => 'application/json',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'التسجيل يتم عن طريق الموظف.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_public_queue_status_includes_faculties_and_document_kinds(): void
    {
        QueueSystemSetting::current();

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('system.faculties.0.value', Faculty::IndustryEnergy)
            ->assertJsonPath('system.faculties.0.label', 'صناعة وطاقة')
            ->assertJsonPath('system.faculties.0.seat_number_min_digits', 7)
            ->assertJsonPath('system.faculties.0.seat_number_max_digits', 7)
            ->assertJsonPath('system.faculties.1.value', Faculty::HealthSciences)
            ->assertJsonPath('system.faculties.1.seat_number_min_digits', 7)
            ->assertJsonPath('system.faculties.1.seat_number_max_digits', 9)
            ->assertJsonPath('system.document_kinds.0.value', DocumentKind::StatusStatement->value)
            ->assertJsonPath('system.document_kinds.1.value', DocumentKind::StudentCard->value);
    }

    public function test_guest_can_track_current_student_ticket_by_seat_number(): void
    {
        QueueSystemSetting::current();
        QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 8,
            'seat_number' => '7654321',
            'full_name' => 'سارة أحمد',
        ]);

        $this->postJson('/api/public/tickets/track', [
            'seat_number' => '7654321',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.ticket_number', 'O8')
            ->assertJsonPath('ticket.position_in_queue', 1)
            ->assertJsonMissingPath('ticket.seat_number');
    }

    public function test_guest_can_track_current_student_ticket_by_nine_digit_seat_number(): void
    {
        QueueSystemSetting::current();
        QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 9,
            'college' => Faculty::HealthSciences,
            'seat_number' => '123456789',
            'full_name' => 'سارة أحمد',
        ]);

        $this->postJson('/api/public/tickets/track', [
            'seat_number' => '123456789',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.ticket_number', 'O9')
            ->assertJsonMissingPath('ticket.seat_number');
    }

    #[DataProvider('invalidTrackedSeatNumbers')]
    public function test_returns_422_when_tracked_seat_number_is_outside_seven_to_nine_digits(string $seatNumber): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets/track', [
            'seat_number' => $seatNumber,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seat_number'])
            ->assertJsonPath('errors.seat_number.0', 'يجب أن يتكون رقم الجلوس من 7 إلى 9 أرقام.');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidTrackedSeatNumbers(): array
    {
        return [
            'too_short' => ['123456'],
            'too_long' => ['1234567890'],
        ];
    }

    public function test_returns_401_when_guest_views_current_student_document(): void
    {
        Storage::fake();
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 2,
        ]);

        $this->getJson('/api/teller/tickets/'.$ticket->id.'/document')
            ->assertUnauthorized();
    }

    public function test_staff_can_view_current_student_document(): void
    {
        Storage::fake();
        QueueSystemSetting::current();
        $path = UploadedFile::fake()->image('card.jpg')->store('current-student-documents');
        $ticket = QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 3,
            'document_path' => $path,
        ]);
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->get('/api/teller/tickets/'.$ticket->id.'/document')
            ->assertOk();
    }

    public function test_returns_404_when_current_student_document_is_missing(): void
    {
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 4,
            'document_path' => null,
        ]);
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->getJson('/api/teller/tickets/'.$ticket->id.'/document')
            ->assertNotFound();
    }

    public function test_staff_scan_includes_current_student_fields(): void
    {
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 5,
            'full_name' => 'سارة أحمد علي',
            'college' => Faculty::HealthSciences,
            'department' => 'علوم المختبرات',
            'seat_number' => '1112223',
            'document_kind' => DocumentKind::StatusStatement,
        ]);
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->getJson('/api/teller/tickets/scan/'.$ticket->public_token)
            ->assertOk()
            ->assertJsonPath('ticket.student_kind', StudentKind::CurrentStudent->value)
            ->assertJsonPath('ticket.student_kind_label', 'طالب حالي (فرقة ثانية)')
            ->assertJsonPath('ticket.request_type_label', 'طالب حالي (فرقة ثانية)')
            ->assertJsonPath('ticket.college_label', 'علوم صحية')
            ->assertJsonPath('ticket.department', 'علوم المختبرات')
            ->assertJsonPath('ticket.seat_number', '1112223')
            ->assertJsonPath('ticket.document_kind', DocumentKind::StatusStatement->value)
            ->assertJsonPath('ticket.has_document', true)
            ->assertJsonPath('ticket.document_url', '/teller/tickets/'.$ticket->id.'/document')
            ->assertJsonPath('ticket.has_entered', false)
            ->assertJsonPath('ticket.has_paid', false)
            ->assertJsonPath('ticket.has_file_withdrawn', false)
            ->assertJsonPath('ticket.has_documents_reviewed', false)
            ->assertJsonPath('ticket.has_medical_checked', false)
            ->assertJsonPath('ticket.has_face_printed', false)
            ->assertJsonPath('ticket.file_delivered', false);
    }

    public function test_teller_can_complete_current_student_without_medical_or_face_steps(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 7,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-entered')
            ->assertOk()
            ->assertJsonPath('ticket.has_entered', true)
            ->assertJsonPath('ticket.has_documents_reviewed', false);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-documents-reviewed')
            ->assertOk()
            ->assertJsonPath('message', 'تم تسجيل مراجعة الورق.')
            ->assertJsonPath('ticket.has_documents_reviewed', true)
            ->assertJsonPath('ticket.has_medical_checked', false)
            ->assertJsonPath('ticket.has_face_printed', false);

        $this->assertNotNull($ticket->fresh()->documents_reviewed_at);
        $this->assertNull($ticket->fresh()->medical_checked_at);
        $this->assertNull($ticket->fresh()->face_printed_at);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-file-delivered')
            ->assertOk()
            ->assertJsonPath('ticket.file_delivered', true);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/complete')
            ->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Completed->value);
    }

    public function test_returns_422_when_current_student_uses_admission_steps(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->currentStudent()->serving($teller)->create([
            'ticket_number' => 8,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-paid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'هذه الخطوة غير مطلوبة للطالب الحالي.');

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-file-withdrawn')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'هذه الخطوة غير مطلوبة للطالب الحالي.');

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-medical-checked')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'هذه الخطوة غير مطلوبة للطالب الحالي.');

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-face-printed')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'هذه الخطوة غير مطلوبة للطالب الحالي.');

        $this->assertNull($ticket->fresh()->paid_at);
        $this->assertNull($ticket->fresh()->file_withdrawn_at);
        $this->assertNull($ticket->fresh()->medical_checked_at);
        $this->assertNull($ticket->fresh()->face_printed_at);
    }

    public function test_returns_422_when_current_student_skips_document_review(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->currentStudent()->serving($teller)->create([
            'ticket_number' => 9,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-file-delivered')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'أكمل خدمة «مراجعة ورق» أولاً.');

        $this->assertNull($ticket->fresh()->file_delivered_at);
    }

    public function test_returns_422_when_current_student_reviews_documents_before_entry(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 13,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-documents-reviewed')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'أكمل خدمة «طلب دخول» أولاً.');

        $this->assertNull($ticket->fresh()->documents_reviewed_at);
    }

    public function test_returns_422_when_new_student_reviews_documents(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 10,
            'college' => Faculty::IndustryEnergy,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-documents-reviewed')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'مراجعة الورق متاحة لطلبات الطالب الحالي فقط.');

        $this->assertNull($ticket->fresh()->documents_reviewed_at);
    }

    public function test_teller_ticket_list_filters_current_student_by_document_review_step(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        QueueTicket::factory()->currentStudent()->serving($teller)->create([
            'ticket_number' => 11,
            'full_name' => 'في انتظار مراجعة الورق',
            'college' => Faculty::IndustryEnergy,
        ]);
        QueueTicket::factory()->fileWithdrawn($teller)->create([
            'ticket_number' => 12,
            'full_name' => 'في انتظار الكشف',
            'college' => Faculty::IndustryEnergy,
        ]);
        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets?step=documents_reviewed')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'في انتظار مراجعة الورق');

        $this->getJson('/api/teller/tickets?step=medical_checked')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'في انتظار الكشف');
    }

    public function test_manager_can_update_current_student_ticket(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        $ticket = QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 6,
            'full_name' => 'الاسم الأصلي',
            'college' => Faculty::IndustryEnergy,
            'department' => 'تكنولوجيا المعلومات',
            'seat_number' => '1234567',
        ]);
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => 'سارة المعدلة',
            'college' => Faculty::HealthSciences,
            'department' => 'الرعاية الصحية',
            'seat_number' => '765432198',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.full_name', 'سارة المعدلة')
            ->assertJsonPath('ticket.college', Faculty::HealthSciences)
            ->assertJsonPath('ticket.college_label', 'علوم صحية')
            ->assertJsonPath('ticket.department', 'الرعاية الصحية')
            ->assertJsonPath('ticket.seat_number', '765432198');
    }

    public function test_returns_422_when_updated_industry_energy_seat_number_is_not_seven_digits(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        $ticket = QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 14,
            'college' => Faculty::IndustryEnergy,
            'department' => 'تكنولوجيا المعلومات',
            'seat_number' => '1234567',
        ]);
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => $ticket->full_name,
            'college' => Faculty::IndustryEnergy,
            'department' => $ticket->department,
            'seat_number' => '12345678',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seat_number'])
            ->assertJsonPath('errors.seat_number.0', 'يجب أن يتكون رقم الجلوس من 7 أرقام بالضبط.');

        $this->assertSame('1234567', $ticket->fresh()->seat_number);
    }
}

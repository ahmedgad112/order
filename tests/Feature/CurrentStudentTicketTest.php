<?php

namespace Tests\Feature;

use App\Enums\College;
use App\Enums\DocumentKind;
use App\Enums\Faculty;
use App\Enums\RequestType;
use App\Enums\StudentKind;
use App\Enums\TicketStatus;
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
            'college' => Faculty::IndustryEnergy->value,
            'department' => 'تكنولوجيا المعلومات',
            'seat_number' => '1234567',
            'document_kind' => DocumentKind::StudentCard->value,
            'document' => UploadedFile::fake()->image('card.jpg'),
        ], $overrides);
    }

    public function test_guest_can_issue_current_student_ticket_with_document(): void
    {
        Storage::fake();
        QueueSystemSetting::current();

        $this->post('/api/public/tickets', $this->currentStudentPayload(), [
            'Accept' => 'application/json',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'O1')
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value)
            ->assertJsonMissingPath('ticket.national_id')
            ->assertJsonMissingPath('ticket.seat_number')
            ->assertJsonMissingPath('ticket.document_path');

        $ticket = QueueTicket::query()->first();

        $this->assertNotNull($ticket);
        $this->assertSame(StudentKind::CurrentStudent, $ticket->student_kind);
        $this->assertSame('سارة أحمد علي', $ticket->full_name);
        $this->assertSame(Faculty::IndustryEnergy->value, $ticket->college);
        $this->assertSame('تكنولوجيا المعلومات', $ticket->department);
        $this->assertSame('1234567', $ticket->seat_number);
        $this->assertSame(DocumentKind::StudentCard, $ticket->document_kind);
        $this->assertNull($ticket->national_id);
        $this->assertNull($ticket->order_number);
        $this->assertNull($ticket->request_type);
        $this->assertTrue($ticket->hasDocument());
        Storage::assertExists((string) $ticket->document_path);
    }

    public function test_new_and_current_students_keep_independent_ticket_sequences(): void
    {
        Storage::fake();
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', [
            'student_kind' => StudentKind::NewStudent->value,
            'full_name' => 'محمد أحمد علي',
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '123456789',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'N1');

        $this->post('/api/public/tickets', $this->currentStudentPayload(), [
            'Accept' => 'application/json',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'O1');

        $this->postJson('/api/public/tickets', [
            'student_kind' => StudentKind::NewStudent->value,
            'full_name' => 'علي محمود حسن',
            'national_id' => '29501011234568',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '123456788',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'N2');

        $this->post('/api/public/tickets', $this->currentStudentPayload([
            'full_name' => 'منى سعيد',
            'seat_number' => '7654321',
            'document' => UploadedFile::fake()->image('card2.jpg'),
        ]), [
            'Accept' => 'application/json',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'O2');

        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'محمد أحمد علي',
            'ticket_number' => 1,
            'student_kind' => StudentKind::NewStudent->value,
        ]);
        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'سارة أحمد علي',
            'ticket_number' => 1,
            'student_kind' => StudentKind::CurrentStudent->value,
        ]);
        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'علي محمود حسن',
            'ticket_number' => 2,
            'student_kind' => StudentKind::NewStudent->value,
        ]);
        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'منى سعيد',
            'ticket_number' => 2,
            'student_kind' => StudentKind::CurrentStudent->value,
        ]);
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
            'full_name' => 'طالب جديد بنفس الرقم',
        ]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/admin/tickets?search=O1')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'سارة بالكود')
            ->assertJsonPath('tickets.0.ticket_number', 'O1');
    }

    public function test_returns_422_when_current_student_payload_is_empty(): void
    {
        QueueSystemSetting::current();

        $this->post('/api/public/tickets', [
            'student_kind' => StudentKind::CurrentStudent->value,
        ], [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'college', 'department', 'seat_number', 'document_kind', 'document'])
            ->assertJsonPath('errors.full_name.0', 'الاسم الكامل مطلوب.')
            ->assertJsonPath('errors.college.0', 'يجب تحديد الكلية.')
            ->assertJsonPath('errors.department.0', 'يجب كتابة القسم.')
            ->assertJsonPath('errors.seat_number.0', 'رقم الجلوس مطلوب.')
            ->assertJsonPath('errors.document_kind.0', 'يجب اختيار نوع المستند.')
            ->assertJsonPath('errors.document.0', 'يجب رفع صورة المستند.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    #[DataProvider('invalidSeatNumbers')]
    public function test_returns_422_when_seat_number_is_not_seven_digits(string $seatNumber): void
    {
        Storage::fake();
        QueueSystemSetting::current();

        $this->post('/api/public/tickets', $this->currentStudentPayload([
            'seat_number' => $seatNumber,
        ]), [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seat_number'])
            ->assertJsonPath('errors.seat_number.0', 'يجب أن يتكون رقم الجلوس من 7 أرقام بالضبط.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidSeatNumbers(): array
    {
        return [
            'too_short' => ['123456'],
            'too_long' => ['12345678'],
            'letters' => ['123456a'],
        ];
    }

    public function test_returns_422_when_faculty_is_not_listed(): void
    {
        Storage::fake();
        QueueSystemSetting::current();

        $this->post('/api/public/tickets', $this->currentStudentPayload([
            'college' => 'information_technology',
        ]), [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['college'])
            ->assertJsonPath('errors.college.0', 'يجب اختيار الكلية الصحيحة.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_active_ticket_already_uses_the_seat_number(): void
    {
        Storage::fake();
        QueueSystemSetting::current();
        QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 1,
            'seat_number' => '1234567',
        ]);

        $this->post('/api/public/tickets', $this->currentStudentPayload(), [
            'Accept' => 'application/json',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['seat_number'])
            ->assertJsonPath('errors.seat_number.0', 'يوجد تذكرة نشطة اليوم بنفس رقم الجلوس.');

        $this->assertDatabaseCount('queue_tickets', 1);
    }

    public function test_public_queue_status_includes_faculties_and_document_kinds(): void
    {
        QueueSystemSetting::current();

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('system.faculties.0.value', Faculty::IndustryEnergy->value)
            ->assertJsonPath('system.faculties.0.label', 'صناعة وطاقة')
            ->assertJsonPath('system.faculties.1.value', Faculty::HealthSciences->value)
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
            'college' => Faculty::HealthSciences->value,
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

    public function test_returns_422_when_current_student_uses_medical_or_face_steps(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->currentStudent()->serving($teller)->create([
            'ticket_number' => 8,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-medical-checked')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'الكشف الطبي وبصمة الوجه غير مطلوبين للطالب الحالي.');

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-face-printed')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'الكشف الطبي وبصمة الوجه غير مطلوبين للطالب الحالي.');

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
            ->assertJsonPath('errors.ticket.0', 'سجّل مراجعة الورق أولاً قبل تسليم الملف.');

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
            ->assertJsonPath('errors.ticket.0', 'سجّل طلب الدخول أولاً قبل مراجعة الورق.');

        $this->assertNull($ticket->fresh()->documents_reviewed_at);
    }

    public function test_returns_422_when_new_student_reviews_documents(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 10,
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
        ]);
        QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 12,
            'full_name' => 'في انتظار الكشف',
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
            'college' => Faculty::IndustryEnergy->value,
            'department' => 'تكنولوجيا المعلومات',
            'seat_number' => '1234567',
        ]);
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => 'سارة المعدلة',
            'college' => Faculty::HealthSciences->value,
            'department' => 'الرعاية الصحية',
            'seat_number' => '7654321',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.full_name', 'سارة المعدلة')
            ->assertJsonPath('ticket.college', Faculty::HealthSciences->value)
            ->assertJsonPath('ticket.college_label', 'علوم صحية')
            ->assertJsonPath('ticket.department', 'الرعاية الصحية')
            ->assertJsonPath('ticket.seat_number', '7654321');
    }
}

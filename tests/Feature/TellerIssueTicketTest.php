<?php

namespace Tests\Feature;

use App\Enums\StudentKind;
use App\Enums\TicketStatus;
use App\Models\Faculty;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TellerIssueTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_creates_ticket_with_name_order_number_and_type(): void
    {
        QueueSystemSetting::current();

        $this->issueTicketAsStaff([
            'full_name' => 'محمد أحمد علي',
            'order_number' => '123456789',
            'request_type' => 'nomination_card',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT1')
            ->assertJsonPath('ticket.full_name', 'محمد أحمد علي')
            ->assertJsonPath('ticket.order_number', '123456789')
            ->assertJsonPath('ticket.request_type', 'nomination_card')
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value);

        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'محمد أحمد علي',
            'order_number' => '123456789',
            'request_type' => 'nomination_card',
            'student_kind' => StudentKind::NewStudent->value,
            'national_id' => null,
            'college' => Faculty::IndustryEnergy,
            'status' => TicketStatus::Waiting->value,
        ]);
    }

    public function test_staff_creates_ticket_with_request_type_only(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/tickets', [
            'request_type' => 'nomination_card',
            'college' => Faculty::IndustryEnergy,
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT1')
            ->assertJsonPath('ticket.full_name', null)
            ->assertJsonPath('ticket.order_number', null)
            ->assertJsonPath('ticket.request_type', 'nomination_card')
            ->assertJsonPath('ticket.college', Faculty::IndustryEnergy)
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value);

        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => null,
            'order_number' => null,
            'request_type' => 'nomination_card',
            'student_kind' => StudentKind::NewStudent->value,
            'college' => Faculty::IndustryEnergy,
            'status' => TicketStatus::Waiting->value,
        ]);
    }

    public function test_staff_creates_type_only_ticket_when_name_and_order_number_are_empty(): void
    {
        QueueSystemSetting::current();

        $this->issueTicketAsStaff([
            'full_name' => '',
            'order_number' => '',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT1')
            ->assertJsonPath('ticket.full_name', null)
            ->assertJsonPath('ticket.order_number', null);
    }

    public function test_staff_creates_multiple_type_only_tickets_with_sequential_numbers(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/tickets', [
            'college' => Faculty::IndustryEnergy,
            'request_type' => 'nomination_card',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT1');

        $this->postJson('/api/teller/tickets', [
            'college' => Faculty::IndustryEnergy,
            'request_type' => 'nomination_card',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT2')
            ->assertJsonPath('ticket.full_name', null)
            ->assertJsonPath('ticket.order_number', null);

        $this->assertDatabaseCount('queue_tickets', 2);
    }

    public function test_staff_issues_batch_of_type_only_tickets_in_one_request(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/tickets', [
            'college' => Faculty::IndustryEnergy,
            'request_type' => 'nomination_card',
            'count' => 3,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'تم إصدار 3 أدوار بنجاح.')
            ->assertJsonPath('ticket.ticket_number', 'OT1')
            ->assertJsonPath('tickets.0.ticket_number', 'OT1')
            ->assertJsonPath('tickets.1.ticket_number', 'OT2')
            ->assertJsonPath('tickets.2.ticket_number', 'OT3')
            ->assertJsonPath('tickets.0.full_name', null)
            ->assertJsonPath('tickets.0.order_number', null)
            ->assertJsonCount(3, 'tickets');

        $this->assertDatabaseCount('queue_tickets', 3);
        $this->assertDatabaseHas('queue_tickets', [
            'ticket_number' => 1,
            'request_type' => 'nomination_card',
            'full_name' => null,
            'order_number' => null,
            'status' => TicketStatus::Waiting->value,
        ]);
        $this->assertDatabaseHas('queue_tickets', [
            'ticket_number' => 2,
            'request_type' => 'nomination_card',
            'status' => TicketStatus::Waiting->value,
        ]);
        $this->assertDatabaseHas('queue_tickets', [
            'ticket_number' => 3,
            'request_type' => 'nomination_card',
            'status' => TicketStatus::Waiting->value,
        ]);
    }

    public function test_batch_type_only_tickets_continue_the_existing_series(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/tickets', [
            'college' => Faculty::IndustryEnergy,
            'request_type' => 'nomination_card',
        ])->assertCreated();

        $this->postJson('/api/teller/tickets', [
            'college' => Faculty::IndustryEnergy,
            'request_type' => 'nomination_card',
            'count' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT2')
            ->assertJsonPath('tickets.0.ticket_number', 'OT2')
            ->assertJsonPath('tickets.1.ticket_number', 'OT3');

        $this->assertDatabaseCount('queue_tickets', 3);
    }

    public function test_returns_422_when_batch_count_exceeds_limit(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/tickets', [
            'college' => Faculty::IndustryEnergy,
            'request_type' => 'nomination_card',
            'count' => 51,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.count.0', 'يمكن إصدار 50 دور كحد أقصى في المرة الواحدة.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_batch_count_includes_student_name(): void
    {
        QueueSystemSetting::current();

        $this->issueTicketAsStaff([
            'count' => 3,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.count.0', 'يمكن إصدار أكثر من دور فقط من غير اسم ورقم طلب.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_batch_count_is_zero(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/tickets', [
            'college' => Faculty::IndustryEnergy,
            'request_type' => 'nomination_card',
            'count' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.count.0', 'يجب إصدار دور واحد على الأقل.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_public_queue_status_includes_ticket_issued_with_type_only(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/tickets', [
            'college' => Faculty::IndustryEnergy,
            'request_type' => 'nomination_card',
        ])->assertCreated();

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('stats.waiting', 1)
            ->assertJsonPath('waiting.0.ticket_number', 'OT1')
            ->assertJsonPath('waiting.0.full_name', null)
            ->assertJsonPath('waiting.0.masked_name', '');
    }

    public function test_staff_creates_current_student_ticket_from_type(): void
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
            ->assertJsonPath('ticket.student_kind', StudentKind::CurrentStudent->value)
            ->assertJsonPath('ticket.request_type', null)
            ->assertJsonPath('ticket.college', Faculty::IndustryEnergy);

        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'سارة أحمد علي',
            'order_number' => '987654321',
            'student_kind' => StudentKind::CurrentStudent->value,
            'request_type' => null,
            'college' => Faculty::IndustryEnergy,
        ]);
    }

    public function test_returns_422_when_ticket_is_missing_college(): void
    {
        QueueSystemSetting::current();

        $this->issueTicketAsStaff([
            'full_name' => 'سارة أحمد علي',
            'order_number' => '987654321',
            'request_type' => 'nomination_card',
            'college' => null,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.college.0', 'يجب اختيار الكلية.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_teller_cannot_issue_ticket_for_unassigned_faculty(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forFaculties([Faculty::IndustryEnergy])->create();

        $this->issueTicketAsStaff([
            'full_name' => 'سارة أحمد علي',
            'order_number' => '987654321',
            'request_type' => 'nomination_card',
            'college' => Faculty::HealthSciences,
        ], $teller)
            ->assertUnprocessable()
            ->assertJsonPath('errors.college.0', 'الكلية غير متاحة لحسابك.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_403_when_guest_issues_from_public_endpoint(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', [
            'full_name' => 'محمد أحمد علي',
            'order_number' => '123456789',
            'request_type' => 'nomination_card',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'التسجيل يتم عن طريق الموظف.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_401_when_guest_issues_from_staff_endpoint(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/teller/tickets', [
            'full_name' => 'محمد أحمد علي',
            'order_number' => '123456789',
            'request_type' => 'nomination_card',
            'college' => Faculty::IndustryEnergy,
        ])->assertUnauthorized();

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_required_issue_fields_are_missing(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/tickets', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.request_type.0', 'يجب اختيار نوع الطلب.')
            ->assertJsonPath('errors.college.0', 'يجب اختيار الكلية.')
            ->assertJsonMissingPath('errors.full_name')
            ->assertJsonMissingPath('errors.order_number');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_order_number_is_not_nine_digits(): void
    {
        QueueSystemSetting::current();

        $this->issueTicketAsStaff([
            'order_number' => '12345',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.order_number.0', 'يجب أن يتكون رقم الطلب من 9 أرقام بالضبط.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_order_number_is_already_active_today(): void
    {
        QueueSystemSetting::current();
        QueueTicket::factory()->waiting()->create([
            'order_number' => '123456789',
        ]);

        $this->issueTicketAsStaff([
            'order_number' => '123456789',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.order_number.0', 'يوجد تذكرة نشطة اليوم بنفس رقم الطلب.');
    }

    public function test_returns_422_when_request_type_is_disabled(): void
    {
        QueueSystemSetting::current();
        RequestType::query()
            ->where('slug', '!=', 'nomination_card')
            ->update(['enabled' => false]);

        $this->issueTicketAsStaff([
            'request_type' => 'transfer',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.request_type.0', 'نوع الطلب غير متاح حالياً.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_teller_cannot_issue_unassigned_queue_lane(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forQueueLanes(['nomination_card'])->create();

        $this->issueTicketAsStaff([
            'request_type' => 'transfer',
        ], $teller)
            ->assertUnprocessable()
            ->assertJsonPath('errors.request_type.0', 'نوع الطلب غير متاح حالياً.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_staff_issues_ticket_while_system_is_closed(): void
    {
        QueueSystemSetting::current()->update([
            'is_open' => false,
            'closed_message' => 'مغلق للاختبار',
        ]);

        $this->issueTicketAsStaff()
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['system']);
    }
}

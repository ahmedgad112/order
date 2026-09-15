<?php

namespace Tests\Feature;

use App\Enums\StudentKind;
use App\Enums\TicketStatus;
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
            'college' => null,
            'status' => TicketStatus::Waiting->value,
        ]);
    }

    public function test_staff_creates_current_student_ticket_from_type(): void
    {
        QueueSystemSetting::current();

        $this->issueTicketAsStaff([
            'full_name' => 'سارة أحمد علي',
            'order_number' => '987654321',
            'request_type' => 'current_student',
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'O1')
            ->assertJsonPath('ticket.student_kind', StudentKind::CurrentStudent->value)
            ->assertJsonPath('ticket.request_type', null);

        $this->assertDatabaseHas('queue_tickets', [
            'full_name' => 'سارة أحمد علي',
            'order_number' => '987654321',
            'student_kind' => StudentKind::CurrentStudent->value,
            'request_type' => null,
        ]);
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
        ])->assertUnauthorized();

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_required_issue_fields_are_missing(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/teller/tickets', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', 'اسم الطالب مطلوب.')
            ->assertJsonPath('errors.order_number.0', 'رقم الطلب مطلوب.')
            ->assertJsonPath('errors.request_type.0', 'يجب اختيار نوع الطلب.');

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

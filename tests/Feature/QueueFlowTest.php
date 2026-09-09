<?php

namespace Tests\Feature;

use App\Enums\College;
use App\Enums\RequestType;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Events\TicketDeletedEvent;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueueFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_issue_ticket_when_system_is_open(): void
    {
        QueueSystemSetting::current();

        $response = $this->postJson('/api/public/tickets', [
            'full_name' => 'محمد أحمد علي',
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '123456789',
        ]);

        $response->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'N1')
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value);

        $this->assertDatabaseHas('queue_tickets', [
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '123456789',
            'status' => TicketStatus::Waiting->value,
        ]);
    }

    public function test_public_queue_status_returns_the_same_counts_on_repeated_reads(): void
    {
        QueueSystemSetting::current();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
        ]);

        $first = $this->getJson('/api/public/queue-status')->assertOk();
        $second = $this->getJson('/api/public/queue-status')->assertOk();

        $this->assertSame($first->json('stats'), $second->json('stats'));
        $this->assertSame(1, $second->json('stats.waiting'));
    }

    public function test_public_queue_status_updates_after_a_ticket_is_issued(): void
    {
        QueueSystemSetting::current();

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('stats.waiting', 0);

        $this->postJson('/api/public/tickets', [
            'full_name' => 'محمد أحمد علي',
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '123456789',
        ])->assertCreated();

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('stats.waiting', 1)
            ->assertJsonPath('waiting.0.ticket_number', 'N1')
            ->assertJsonPath('waiting.0.full_name', 'محمد أحمد علي')
            ->assertJsonPath('waiting.0.request_type_label', 'حاصل على بطاقة ترشيح')
            ->assertJsonPath('waiting.0.college_label', 'تكنولوجيا المعلومات')
            ->assertJsonMissingPath('waiting.0.national_id');
    }

    public function test_cannot_issue_ticket_when_system_is_closed(): void
    {
        $settings = QueueSystemSetting::current();
        $settings->update([
            'is_open' => false,
            'closed_message' => 'مغلق للاختبار',
        ]);

        $response = $this->postJson('/api/public/tickets', [
            'full_name' => 'محمد أحمد علي',
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '123456789',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['system']);
    }

    public function test_cannot_issue_duplicate_active_ticket(): void
    {
        QueueSystemSetting::current();

        QueueTicket::factory()->waiting()->create([
            'national_id' => '29501011234567',
            'order_number' => 'ORD-1001',
            'ticket_number' => 1,
        ]);

        $response = $this->postJson('/api/public/tickets', [
            'full_name' => 'محمد أحمد علي',
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '987654321',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['national_id']);
    }

    public function test_teller_can_call_complete_and_cancel_flow(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller('شباك 1')->create();

        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'عميل تجريبي',
            'national_id' => '29501011234567',
            'order_number' => 'ORD-1001',
        ]);

        Sanctum::actingAs($teller);

        $call = $this->postJson('/api/teller/call-next');
        $call->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value)
            ->assertJsonPath('ticket.ticket_number', 'N1')
            ->assertJsonPath('ticket.teller_name', $teller->name)
            ->assertJsonPath('ticket.counter_name', 'شباك 1');

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('serving.0.teller_name', $teller->name)
            ->assertJsonPath('serving.0.counter_name', 'شباك 1');

        $ticketId = $call->json('ticket.id');

        $this->postJson("/api/teller/tickets/{$ticketId}/mark-entered")->assertOk();
        $this->postJson("/api/teller/tickets/{$ticketId}/mark-medical-checked")->assertOk();
        $this->postJson("/api/teller/tickets/{$ticketId}/mark-face-printed")->assertOk();
        $this->postJson("/api/teller/tickets/{$ticketId}/mark-file-delivered")->assertOk();

        $complete = $this->postJson("/api/teller/tickets/{$ticketId}/complete");
        $complete->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Completed->value);

        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'national_id' => '29501011234568',
            'order_number' => 'ORD-1002',
        ]);

        $call2 = $this->postJson('/api/teller/call-next');
        $call2->assertOk();
        $ticketId2 = $call2->json('ticket.id');

        $cancel = $this->postJson("/api/teller/tickets/{$ticketId2}/cancel");
        $cancel->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Cancelled->value);
    }

    public function test_teller_can_call_next_while_already_serving(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();

        QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 1,
            'user_id' => $teller->id,
        ]);
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'national_id' => '29501011234568',
            'order_number' => 'ORD-1002',
        ]);

        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/call-next')
            ->assertOk()
            ->assertJsonPath('ticket.ticket_number', 'N2');
    }

    public function test_teller_can_list_tickets_and_mark_entry_then_file_delivery(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller('شباك 1')->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'طالب تجريبي',
            'national_id' => '29501011234567',
            'order_number' => 'ORD-1001',
        ]);

        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.has_entered', false)
            ->assertJsonPath('tickets.0.has_medical_checked', false)
            ->assertJsonPath('tickets.0.has_face_printed', false)
            ->assertJsonPath('tickets.0.file_delivered', false)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.waiting', 1)
            ->assertJsonPath('current_ticket', null)
            ->assertJsonCount(0, 'serving')
            ->assertJsonCount(0, 'absent_tickets');

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-entered")
            ->assertOk()
            ->assertJsonPath('ticket.has_entered', true)
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value);

        $this->assertNotNull($ticket->fresh()->entered_at);

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-medical-checked")
            ->assertOk()
            ->assertJsonPath('ticket.has_medical_checked', true);

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-face-printed")
            ->assertOk()
            ->assertJsonPath('ticket.has_face_printed', true);

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-file-delivered")
            ->assertOk()
            ->assertJsonPath('ticket.file_delivered', true)
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value);

        $this->assertNotNull($ticket->fresh()->file_delivered_at);

        $this->postJson("/api/teller/tickets/{$ticket->id}/complete")
            ->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Completed->value);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function currentProcessSteps(): array
    {
        return [
            'entered' => ['entered', 'في انتظار الدخول'],
            'medical_checked' => ['medical_checked', 'في انتظار الكشف'],
            'face_printed' => ['face_printed', 'في انتظار البصمة'],
            'file_delivered' => ['file_delivered', 'في انتظار التسليم'],
        ];
    }

    #[DataProvider('currentProcessSteps')]
    public function test_admin_ticket_list_filters_by_current_process_step(string $step, string $expectedName): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller()->create();

        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'في انتظار الدخول',
        ]);
        QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 2,
            'full_name' => 'في انتظار الكشف',
        ]);
        QueueTicket::factory()->medicalChecked($teller)->create([
            'ticket_number' => 3,
            'full_name' => 'في انتظار البصمة',
        ]);
        QueueTicket::factory()->facePrinted($teller)->create([
            'ticket_number' => 4,
            'full_name' => 'في انتظار التسليم',
        ]);
        QueueTicket::factory()->completed($teller)->create([
            'ticket_number' => 5,
            'full_name' => 'تم الاكتمال',
        ]);
        QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 6,
            'full_name' => 'ملغى بعد الدخول',
            'status' => TicketStatus::Cancelled,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tickets?step='.$step)
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', $expectedName);
    }

    public function test_returns_422_when_admin_process_step_filter_is_invalid(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tickets?step=waiting')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'step' => 'خطوة الطلب غير صحيحة.',
            ]);
    }

    public function test_teller_ticket_list_filters_by_current_process_step(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'في انتظار الدخول',
        ]);
        QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 2,
            'full_name' => 'في انتظار الكشف',
        ]);

        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets?step=medical_checked')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'في انتظار الكشف');
    }

    public function test_returns_422_when_teller_process_step_filter_is_invalid(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets?step=waiting')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'step' => 'خطوة الطلب غير صحيحة.',
            ]);
    }

    public function test_admin_ticket_list_filters_by_search_term(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'أحمد علي',
        ]);
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'full_name' => 'سارة محمد',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tickets?search='.urlencode('أحمد'))
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'أحمد علي');
    }

    public function test_admin_ticket_list_defaults_to_today_and_excludes_previous_days(): void
    {
        $this->freezeTime();

        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'مسجل أمس',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'full_name' => 'مسجل اليوم',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'مسجل اليوم')
            ->assertJsonPath('date', now()->toDateString())
            ->assertJsonPath('today', now()->toDateString())
            ->assertJsonPath('is_today', true)
            ->assertJsonPath('stats.total', 1);
    }

    public function test_admin_ticket_list_returns_registrations_for_a_past_date(): void
    {
        $this->freezeTime();

        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $yesterday = now()->subDay()->toDateString();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'مسجل أمس',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'full_name' => 'مسجل اليوم',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tickets?date='.$yesterday)
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'مسجل أمس')
            ->assertJsonPath('date', $yesterday)
            ->assertJsonPath('is_today', false)
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('available_dates.0', now()->toDateString())
            ->assertJsonPath('available_dates.1', $yesterday);
    }

    public function test_returns_422_when_admin_ticket_date_filter_is_invalid(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tickets?date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date' => 'تاريخ الأرشيف غير صحيح.',
            ]);
    }

    public function test_returns_422_when_admin_ticket_date_filter_is_in_the_future(): void
    {
        $this->freezeTime();

        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/tickets?date='.now()->addDay()->toDateString())
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date' => 'لا يمكن عرض تسجيلات تاريخ في المستقبل.',
            ]);
    }

    public function test_teller_cannot_skip_process_steps(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
        ]);

        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-medical-checked")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-entered")->assertOk();

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-face-printed")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);

        $this->postJson("/api/teller/tickets/{$ticket->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);
    }

    public function test_teller_cannot_deliver_file_before_entry(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
        ]);

        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-file-delivered")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);
    }

    public function test_teller_status_includes_system_state(): void
    {
        $settings = QueueSystemSetting::current();
        $settings->update([
            'is_open' => false,
            'closed_message' => 'مغلق',
        ]);

        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/queue-status')
            ->assertOk()
            ->assertJsonPath('system.is_open', false)
            ->assertJsonPath('system.closed_message', 'مغلق');
    }

    public function test_admin_can_mark_entered_and_end_day_without_deleting_tickets(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->admin()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/tickets/{$ticket->id}/mark-entered")
            ->assertOk()
            ->assertJsonPath('ticket.has_entered', true)
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value);

        QueueTicket::factory()->waiting()->create(['ticket_number' => 2]);

        $ended = $this->postJson('/api/admin/system/end-day');
        $ended->assertOk()
            ->assertJsonPath('archived_tickets', 2)
            ->assertJsonPath('system.is_open', true)
            ->assertJsonPath('system.is_day_open', false)
            ->assertJsonPath('system.accepting_tickets', false);

        $this->assertDatabaseCount('queue_tickets', 2);
        $this->assertNotNull(QueueSystemSetting::query()->first()?->day_ended_at);
    }

    public function test_role_middleware_blocks_teller_from_admin_routes(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($teller);

        $this->getJson('/api/admin/system/status')->assertForbidden();
    }

    public function test_guest_can_track_ticket_by_national_id(): void
    {
        QueueSystemSetting::current();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 3,
            'national_id' => '29501011234567',
            'order_number' => 'ORD-1001',
            'full_name' => 'محمد أحمد',
        ]);

        $this->postJson('/api/public/tickets/track', [
            'national_id' => '29501011234567',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.ticket_number', 'N3')
            ->assertJsonPath('ticket.position_in_queue', 1);
    }

    public function test_login_returns_token_for_active_user(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@test.local',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@test.local',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);
    }

    public function test_teller_can_mark_absent_and_restore_ticket(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller('شباك 1')->create();

        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'عميل غائب',
            'national_id' => '29501011234567',
            'order_number' => 'ORD-1001',
        ]);

        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/call-next')->assertOk();
        $ticketId = $ticket->id;

        $absent = $this->postJson("/api/teller/tickets/{$ticketId}/mark-absent");
        $absent->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Absent->value);

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticketId,
            'status' => TicketStatus::Absent->value,
        ]);

        $this->getJson('/api/teller/absent-tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.id', $ticketId);

        $restore = $this->postJson("/api/teller/tickets/{$ticketId}/restore");
        $restore->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value);

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticketId,
            'status' => TicketStatus::Waiting->value,
            'user_id' => null,
            'called_at' => null,
        ]);

        $this->getJson('/api/teller/absent-tickets')
            ->assertOk()
            ->assertJsonCount(0, 'tickets');
    }

    public function test_teller_can_mark_waiting_ticket_absent_from_table(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller('شباك 1')->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
        ]);

        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-absent")
            ->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Absent->value);

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::Absent->value,
            'user_id' => $teller->id,
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->teller()->create([
            'email' => 'off@test.local',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'off@test.local',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_manager_can_view_reports_but_cannot_manage_users_or_system(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.permissions.manage_users', false)
            ->assertJsonPath('user.permissions.control_system', false)
            ->assertJsonPath('user.permissions.edit_tickets', true)
            ->assertJsonPath('user.permissions.delete_tickets', false);
        $this->getJson('/api/admin/dashboard')->assertOk();
        $this->getJson('/api/admin/system/status')->assertOk();
        $this->getJson('/api/admin/tickets')->assertOk();
        $this->getJson('/api/admin/tellers')->assertOk();

        $this->getJson('/api/admin/users')->assertForbidden();
        $this->postJson('/api/admin/system/close')->assertForbidden();
        $this->postJson('/api/admin/system/open')->assertForbidden();
        $this->postJson('/api/admin/system/end-day')->assertForbidden();
        $this->postJson('/api/admin/system/open-day')->assertForbidden();
        $this->putJson('/api/admin/system/request-types', [
            'enabled_request_types' => [RequestType::NominationCard->value],
        ])->assertForbidden();
        $this->postJson('/api/admin/users', [
            'name' => 'موظف جديد',
            'email' => 'new-teller@queue.local',
            'password' => 'password123',
            'role' => UserRole::Teller->value,
            'counter_name' => 'شباك 5',
        ])->assertForbidden();
    }

    public function test_super_admin_can_create_manager(): void
    {
        QueueSystemSetting::current();
        $superAdmin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($superAdmin);

        $this->postJson('/api/admin/users', [
            'name' => 'مدير جديد',
            'email' => 'new-manager@queue.local',
            'password' => 'password123',
            'role' => UserRole::Manager->value,
        ])->assertCreated()
            ->assertJsonPath('user.role', UserRole::Manager->value)
            ->assertJsonPath('user.role_label', 'مدير');

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.permissions.manage_users', true)
            ->assertJsonPath('user.permissions.control_system', true)
            ->assertJsonPath('user.permissions.edit_tickets', true)
            ->assertJsonPath('user.permissions.delete_tickets', true);
    }

    public function test_admin_dashboard_returns_metrics_and_teller_performance(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller('شباك 1')->create();

        QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);
        QueueTicket::factory()->completed($teller)->create(['ticket_number' => 2]);
        QueueTicket::factory()->serving($teller)->create(['ticket_number' => 3]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('metrics.total', 3)
            ->assertJsonPath('metrics.waiting', 1)
            ->assertJsonPath('metrics.serving', 1)
            ->assertJsonPath('metrics.completed', 1)
            ->assertJsonPath('system.is_open', true)
            ->assertJsonPath('teller_performance.0.name', $teller->name)
            ->assertJsonPath('teller_performance.0.counter_name', 'شباك 1')
            ->assertJsonPath('teller_performance.0.completed_today', 1)
            ->assertJsonPath('teller_performance.0.serving_now', 1);

        $this->getJson('/api/admin/reports/daily')
            ->assertOk()
            ->assertJsonPath('metrics.total', 3);
    }

    public function test_teller_ticket_list_includes_serving_absent_and_current(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller('شباك 1')->create();
        $current = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 1,
            'full_name' => 'قيد الخدمة',
        ]);
        QueueTicket::factory()->create([
            'ticket_number' => 2,
            'status' => TicketStatus::Absent,
            'full_name' => 'مش موجود',
        ]);

        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets')
            ->assertOk()
            ->assertJsonPath('current_ticket.id', $current->id)
            ->assertJsonPath('serving.0.id', $current->id)
            ->assertJsonPath('absent_tickets.0.full_name', 'مش موجود')
            ->assertJsonPath('stats.serving', 1)
            ->assertJsonPath('stats.absent', 1);
    }

    public function test_returns_401_when_guest_deletes_a_ticket(): void
    {
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);

        $this->deleteJson('/api/admin/tickets/'.$ticket->id)
            ->assertUnauthorized();

        $this->assertModelExists($ticket);
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function nonSuperAdminRoles(): array
    {
        return [
            'teller' => [UserRole::Teller],
            'manager' => [UserRole::Manager],
        ];
    }

    #[DataProvider('nonSuperAdminRoles')]
    public function test_returns_403_when_non_super_admin_deletes_a_ticket(UserRole $role): void
    {
        QueueSystemSetting::current();
        $user = match ($role) {
            UserRole::Teller => User::factory()->teller()->create(),
            UserRole::Manager => User::factory()->manager()->create(),
            UserRole::SuperAdmin => User::factory()->superAdmin()->create(),
        };
        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/admin/tickets/'.$ticket->id)
            ->assertForbidden();

        $this->assertModelExists($ticket);
    }

    public function test_super_admin_can_delete_a_ticket(): void
    {
        QueueSystemSetting::current();
        $superAdmin = User::factory()->superAdmin()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 7,
            'full_name' => 'محمود علي',
        ]);
        Event::fake([TicketDeletedEvent::class]);
        Sanctum::actingAs($superAdmin);

        $this->deleteJson('/api/admin/tickets/'.$ticket->id)
            ->assertOk()
            ->assertJsonPath('message', 'تم حذف الطلب بنجاح.');

        $this->assertModelMissing($ticket);
        Event::assertDispatched(
            TicketDeletedEvent::class,
            fn (TicketDeletedEvent $event): bool => $event->ticketId === $ticket->id && $event->ticketNumber === 'N7',
        );
    }

    public function test_returns_401_when_guest_updates_a_ticket(): void
    {
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => 'اسم معدل',
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '111222333',
        ])->assertUnauthorized();
    }

    public function test_returns_403_when_teller_updates_a_ticket(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'الاسم الأصلي',
        ]);
        Sanctum::actingAs($teller);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => 'اسم معدل',
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '111222333',
        ])->assertForbidden();

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticket->id,
            'full_name' => 'الاسم الأصلي',
        ]);
    }

    public function test_manager_can_update_a_ticket(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 4,
            'full_name' => 'الاسم الأصلي',
            'national_id' => '29501011234567',
            'order_number' => '123456789',
        ]);
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => 'محمد المعدل',
            'national_id' => '29501017654321',
            'request_type' => RequestType::DirectApplication->value,
            'college' => 'كلية التجارة',
            'order_number' => '987654321',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.full_name', 'محمد المعدل')
            ->assertJsonPath('ticket.national_id', '29501017654321')
            ->assertJsonPath('ticket.request_type', RequestType::DirectApplication->value)
            ->assertJsonPath('ticket.request_type_label', 'تقديم مباشر')
            ->assertJsonPath('ticket.college', 'كلية التجارة')
            ->assertJsonPath('ticket.college_label', 'كلية التجارة')
            ->assertJsonPath('ticket.order_number', '987654321');

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticket->id,
            'full_name' => 'محمد المعدل',
            'national_id' => '29501017654321',
            'request_type' => RequestType::DirectApplication->value,
            'college' => 'كلية التجارة',
            'order_number' => '987654321',
        ]);
    }

    public function test_super_admin_can_update_a_ticket(): void
    {
        QueueSystemSetting::current();
        $superAdmin = User::factory()->superAdmin()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 5,
            'full_name' => 'قبل التعديل',
            'national_id' => '29501011234567',
            'order_number' => '123456789',
        ]);
        Sanctum::actingAs($superAdmin);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => 'بعد التعديل',
            'national_id' => '29501011234567',
            'request_type' => RequestType::Transfer->value,
            'college' => 'كلية الهندسة',
            'order_number' => '123456789',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.full_name', 'بعد التعديل');
    }
}

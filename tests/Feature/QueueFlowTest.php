<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
            'order_number' => 'ORD-1001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 1)
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value);

        $this->assertDatabaseHas('queue_tickets', [
            'national_id' => '29501011234567',
            'order_number' => 'ORD-1001',
            'status' => TicketStatus::Waiting->value,
        ]);
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
            'order_number' => 'ORD-1001',
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
            'order_number' => 'ORD-2002',
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
            ->assertJsonPath('ticket.ticket_number', 1)
            ->assertJsonPath('ticket.teller_name', $teller->name)
            ->assertJsonPath('ticket.counter_name', 'شباك 1');

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('serving.0.teller_name', $teller->name)
            ->assertJsonPath('serving.0.counter_name', 'شباك 1');

        $ticketId = $call->json('ticket.id');

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
            ->assertJsonPath('ticket.ticket_number', 2);
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
            ->assertJsonPath('tickets.0.file_delivered', false);

        $enter = $this->postJson("/api/teller/tickets/{$ticket->id}/mark-entered");
        $enter->assertOk()
            ->assertJsonPath('ticket.has_entered', true)
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value);

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::Serving->value,
        ]);
        $this->assertNotNull($ticket->fresh()->entered_at);

        $deliver = $this->postJson("/api/teller/tickets/{$ticket->id}/mark-file-delivered");
        $deliver->assertOk()
            ->assertJsonPath('ticket.file_delivered', true)
            ->assertJsonPath('ticket.status', TicketStatus::Completed->value);

        $this->assertNotNull($ticket->fresh()->file_delivered_at);
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

    public function test_admin_can_mark_entered_and_reset_day(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->admin()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/tickets/{$ticket->id}/mark-entered")
            ->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Completed->value);

        QueueTicket::factory()->waiting()->create(['ticket_number' => 2]);

        $reset = $this->postJson('/api/admin/system/reset-day');
        $reset->assertOk()
            ->assertJsonPath('deleted_tickets', 2);

        $this->assertDatabaseCount('queue_tickets', 0);
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
            ->assertJsonPath('ticket.ticket_number', 3)
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
}

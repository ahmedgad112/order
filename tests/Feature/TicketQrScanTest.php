<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketQrScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_issuing_ticket_returns_public_token_and_omits_personal_ids(): void
    {
        QueueSystemSetting::current();

        $response = $this->postJson('/api/public/tickets', [
            'full_name' => 'محمد أحمد علي',
            'national_id' => '29501011234567',
            'order_number' => 'ORD-1001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 1)
            ->assertJsonPath('ticket.masked_name', 'محمد أ***')
            ->assertJsonMissingPath('ticket.national_id')
            ->assertJsonMissingPath('ticket.order_number')
            ->assertJsonMissingPath('ticket.full_name');

        $token = $response->json('ticket.public_token');
        $this->assertTrue(Str::isUuid($token));
        $this->assertDatabaseHas('queue_tickets', [
            'order_number' => 'ORD-1001',
            'public_token' => $token,
        ]);
    }

    public function test_returns_401_when_guest_scans_ticket(): void
    {
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->waiting()->create();

        $this->getJson('/api/teller/tickets/scan/'.$ticket->public_token)
            ->assertUnauthorized();

        $this->get('/api/teller/tickets/scan/'.$ticket->public_token)
            ->assertUnauthorized();
    }

    public function test_staff_can_scan_token_and_see_personal_details(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 4,
            'full_name' => 'محمد أحمد علي',
            'national_id' => '29501011234567',
            'order_number' => 'ORD-1001',
        ]);

        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets/scan/'.$ticket->public_token)
            ->assertOk()
            ->assertJsonPath('ticket.ticket_number', 4)
            ->assertJsonPath('ticket.full_name', 'محمد أحمد علي')
            ->assertJsonPath('ticket.national_id', '29501011234567')
            ->assertJsonPath('ticket.order_number', 'ORD-1001')
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value)
            ->assertJsonPath('ticket.has_entered', false)
            ->assertJsonPath('ticket.position_in_queue', 1)
            ->assertJsonPath('ticket.people_ahead', 0);
    }

    public function test_returns_404_when_public_token_is_unknown(): void
    {
        QueueSystemSetting::current();
        QueueTicket::factory()->waiting()->create();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->getJson('/api/teller/tickets/scan/'.Str::uuid())
            ->assertNotFound();
    }

    public function test_returns_404_when_ticket_id_is_used_instead_of_token(): void
    {
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->waiting()->create();
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->getJson('/api/teller/tickets/scan/'.$ticket->id)
            ->assertNotFound();
    }

    public function test_public_queue_status_does_not_include_public_token(): void
    {
        QueueSystemSetting::current();
        QueueTicket::factory()->waiting()->create();

        $waiting = $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->json('waiting.0');

        $this->assertIsArray($waiting);
        $this->assertArrayNotHasKey('public_token', $waiting);
        $this->assertArrayNotHasKey('national_id', $waiting);
        $this->assertArrayNotHasKey('order_number', $waiting);
        $this->assertArrayNotHasKey('full_name', $waiting);
    }
}

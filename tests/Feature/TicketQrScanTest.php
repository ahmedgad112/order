<?php

namespace Tests\Feature;

use App\Enums\RequestType;
use App\Enums\TicketStatus;
use App\Models\College;
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

        $response = $this->issueTicketAsStaff([
            'full_name' => 'محمد أحمد علي',
            'order_number' => '123456789',
            'request_type' => RequestType::NominationCard->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT1')
            ->assertJsonPath('ticket.full_name', 'محمد أحمد علي')
            ->assertJsonPath('ticket.order_number', '123456789')
            ->assertJsonMissingPath('ticket.masked_name');

        $token = $response->json('ticket.public_token');
        $this->assertTrue(Str::isUuid($token));
        $this->assertDatabaseHas('queue_tickets', [
            'order_number' => '123456789',
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
            'request_type' => RequestType::NominationCard,
            'college' => College::InformationTechnology,
        ]);

        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets/scan/'.$ticket->public_token)
            ->assertOk()
            ->assertJsonPath('ticket.ticket_number', 'OT4')
            ->assertJsonPath('ticket.public_token', $ticket->public_token)
            ->assertJsonPath('ticket.full_name', 'محمد أحمد علي')
            ->assertJsonPath('ticket.national_id', '29501011234567')
            ->assertJsonPath('ticket.order_number', 'ORD-1001')
            ->assertJsonPath('ticket.college', College::InformationTechnology)
            ->assertJsonPath('ticket.college_label', 'تكنولوجيا المعلومات')
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value)
            ->assertJsonPath('ticket.has_entered', false)
            ->assertJsonPath('ticket.position_in_queue', 1)
            ->assertJsonPath('ticket.people_ahead', 0);
    }

    public function test_teller_ticket_list_includes_public_token_for_printing(): void
    {
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 5,
        ]);

        Sanctum::actingAs(User::factory()->teller()->create());

        $this->getJson('/api/teller/tickets')
            ->assertOk()
            ->assertJsonPath('tickets.0.public_token', $ticket->public_token)
            ->assertJsonPath('tickets.0.ticket_number', 'OT5');
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
        QueueTicket::factory()->waiting()->create([
            'full_name' => 'محمد أحمد علي',
        ]);

        $waiting = $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->json('waiting.0');

        $this->assertIsArray($waiting);
        $this->assertSame('محمد أحمد علي', $waiting['full_name']);
        $this->assertArrayNotHasKey('public_token', $waiting);
        $this->assertArrayNotHasKey('national_id', $waiting);
        $this->assertArrayNotHasKey('order_number', $waiting);
    }
}

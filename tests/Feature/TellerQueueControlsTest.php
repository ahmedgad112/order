<?php

namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Events\TicketCalledEvent;
use App\Events\TicketUpdatedEvent;
use App\Jobs\GenerateTicketAudioJob;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TellerQueueControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_teller_can_call_a_specific_waiting_ticket(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller('شباك 1')->create();
        $first = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);
        $second = QueueTicket::factory()->waiting()->create(['ticket_number' => 2]);
        Event::fake([TicketCalledEvent::class]);
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$second->id}/call")
            ->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value)
            ->assertJsonPath('ticket.ticket_number', 'OT2')
            ->assertJsonPath('ticket.teller_name', $teller->name);

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $second->id,
            'status' => TicketStatus::Serving->value,
            'user_id' => $teller->id,
            'deferred_to_id' => null,
        ]);
        $this->assertDatabaseHas('queue_tickets', [
            'id' => $first->id,
            'status' => TicketStatus::Waiting->value,
        ]);
        Event::assertDispatched(
            TicketCalledEvent::class,
            fn (TicketCalledEvent $event): bool => $event->ticket->id === $second->id,
        );
    }

    public function test_returns_401_when_guest_calls_a_specific_ticket(): void
    {
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);

        $this->postJson("/api/teller/tickets/{$ticket->id}/call")
            ->assertUnauthorized();
    }

    public function test_returns_422_when_calling_a_completed_ticket(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->create([
            'ticket_number' => 1,
            'status' => TicketStatus::Completed,
            'user_id' => $teller->id,
            'called_at' => now(),
            'completed_at' => now(),
        ]);
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/call")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'يمكن نداء التذاكر في الانتظار أو قيد الخدمة فقط.');
    }

    public function test_teller_can_call_a_ticket_already_serving_for_another_teller(): void
    {
        QueueSystemSetting::current();
        $original = User::factory()->teller('شباك 1')->create();
        $claimer = User::factory()->teller('شباك 2')->create();
        $ticket = QueueTicket::factory()->serving($original)->create(['ticket_number' => 7]);
        Event::fake([TicketCalledEvent::class]);
        Sanctum::actingAs($claimer);

        $this->postJson("/api/teller/tickets/{$ticket->id}/call")
            ->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value)
            ->assertJsonPath('ticket.teller_name', $claimer->name)
            ->assertJsonPath('ticket.counter_name', 'شباك 2');

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::Serving->value,
            'user_id' => $claimer->id,
        ]);
        Event::assertDispatched(
            TicketCalledEvent::class,
            fn (TicketCalledEvent $event): bool => $event->ticket->id === $ticket->id
                && $event->ticket->user_id === $claimer->id,
        );
    }

    public function test_returns_422_when_teller_calls_a_ticket_outside_assigned_lanes(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()
            ->forQueueLanes(['nomination_card'])
            ->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => 'transfer',
        ]);
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/call")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticket->id,
            'status' => TicketStatus::Waiting->value,
        ]);
    }

    public function test_returns_422_when_calling_a_ticket_while_system_is_closed(): void
    {
        $settings = QueueSystemSetting::current();
        $settings->update(['is_open' => false, 'closed_message' => 'مغلق']);
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/call")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['system']);
    }

    public function test_skip_moves_a_waiting_ticket_back_five_positions(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $tickets = QueueTicket::factory()->waiting()->count(8)
            ->sequence(fn ($sequence) => ['ticket_number' => $sequence->index + 1])
            ->create();
        Event::fake([TicketUpdatedEvent::class]);
        Sanctum::actingAs($teller);

        $skipped = $tickets->first();

        $this->postJson("/api/teller/tickets/{$skipped->id}/skip")
            ->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Waiting->value);

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $skipped->id,
            'status' => TicketStatus::Waiting->value,
            'deferred_to_id' => $tickets->get(5)->id,
        ]);

        $order = QueueTicket::query()->today()->waiting()->inQueueOrder()->pluck('id')->all();

        $this->assertSame([
            $tickets->get(1)->id,
            $tickets->get(2)->id,
            $tickets->get(3)->id,
            $tickets->get(4)->id,
            $tickets->get(5)->id,
            $skipped->id,
            $tickets->get(6)->id,
            $tickets->get(7)->id,
        ], $order);

        Event::assertDispatched(
            TicketUpdatedEvent::class,
            fn (TicketUpdatedEvent $event): bool => $event->ticketId === $skipped->id,
        );
    }

    public function test_skip_places_ticket_at_the_end_when_fewer_than_five_follow(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $tickets = QueueTicket::factory()->waiting()->count(3)
            ->sequence(fn ($sequence) => ['ticket_number' => $sequence->index + 1])
            ->create();
        Sanctum::actingAs($teller);

        $skipped = $tickets->first();

        $this->postJson("/api/teller/tickets/{$skipped->id}/skip")->assertOk();

        $order = QueueTicket::query()->today()->waiting()->inQueueOrder()->pluck('id')->all();

        $this->assertSame([
            $tickets->get(1)->id,
            $tickets->get(2)->id,
            $skipped->id,
        ], $order);
    }

    public function test_returns_422_when_skipping_the_last_ticket_in_the_queue(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/skip")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'التذكرة بالفعل في نهاية الطابور.');
    }

    public function test_returns_422_when_skipping_a_ticket_that_is_not_waiting(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->serving($teller)->create(['ticket_number' => 1]);
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/skip")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket'])
            ->assertJsonPath('errors.ticket.0', 'يمكن تخطي التذاكر في الانتظار فقط.');
    }

    public function test_returns_422_when_teller_skips_a_ticket_outside_assigned_lanes(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()
            ->forQueueLanes(['nomination_card'])
            ->create();
        QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'request_type' => 'transfer',
        ]);
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/skip")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);
    }

    public function test_returns_401_when_guest_skips_a_ticket(): void
    {
        QueueSystemSetting::current();
        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);

        $this->postJson("/api/teller/tickets/{$ticket->id}/skip")
            ->assertUnauthorized();
    }

    public function test_skipped_ticket_keeps_reporting_its_real_queue_position(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $tickets = QueueTicket::factory()->waiting()->count(7)
            ->sequence(fn ($sequence) => ['ticket_number' => $sequence->index + 1])
            ->create();
        Sanctum::actingAs($teller);

        $skipped = $tickets->first();

        $this->postJson("/api/teller/tickets/{$skipped->id}/skip")->assertOk();

        $this->postJson('/api/public/tickets/track', [
            'national_id' => $skipped->national_id,
        ])
            ->assertOk()
            ->assertJsonPath('ticket.position_in_queue', 6);
    }

    public function test_teller_can_recall_their_serving_ticket(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller('شباك 1')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create(['ticket_number' => 1]);
        Event::fake([TicketCalledEvent::class]);
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/recall")
            ->assertOk()
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value);

        Event::assertDispatched(
            TicketCalledEvent::class,
            fn (TicketCalledEvent $event): bool => $event->ticket->id === $ticket->id,
        );
    }

    public function test_calling_a_ticket_dispatches_audio_pregeneration(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);
        Queue::fake();
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/call")->assertOk();

        Queue::assertPushed(
            GenerateTicketAudioJob::class,
            fn (GenerateTicketAudioJob $job): bool => $job->ticketId === $ticket->id,
        );
    }

    public function test_recalling_a_ticket_dispatches_audio_pregeneration(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->serving($teller)->create(['ticket_number' => 1]);
        Queue::fake();
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/recall")->assertOk();

        Queue::assertPushed(
            GenerateTicketAudioJob::class,
            fn (GenerateTicketAudioJob $job): bool => $job->ticketId === $ticket->id,
        );
    }

    public function test_marking_a_checkpoint_recalls_the_ticket_to_the_counter(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->serving($teller)->create(['ticket_number' => 1]);
        $originalCalledAt = $ticket->called_at;
        Queue::fake();
        Event::fake([TicketCalledEvent::class]);
        Sanctum::actingAs($teller);

        $this->travel(5)->seconds();

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-paid")->assertOk();

        Event::assertDispatched(TicketCalledEvent::class);
        Queue::assertPushed(
            GenerateTicketAudioJob::class,
            fn (GenerateTicketAudioJob $job): bool => $job->ticketId === $ticket->id,
        );
        $this->assertTrue($ticket->fresh()->called_at->gt($originalCalledAt));
    }

    public function test_returns_422_when_recalling_a_waiting_ticket(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 1]);
        Sanctum::actingAs($teller);

        $this->postJson("/api/teller/tickets/{$ticket->id}/recall")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);
    }
}

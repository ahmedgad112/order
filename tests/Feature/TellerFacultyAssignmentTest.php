<?php

namespace Tests\Feature;

use App\Enums\ProcessStep;
use App\Enums\TicketStatus;
use App\Models\Faculty;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TellerFacultyAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_faculties_to_a_teller(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/users/'.$teller->id, [
            'assigned_faculties' => [Faculty::IndustryEnergy],
        ])
            ->assertOk()
            ->assertJsonPath('user.assigned_faculties.0.value', Faculty::IndustryEnergy)
            ->assertJsonMissingPath('user.assigned_faculties.1');

        $this->assertSame(
            [Faculty::IndustryEnergy],
            $teller->fresh()->assignedFacultyValues(),
        );
    }

    public function test_creating_a_teller_without_faculties_assigns_all_faculties(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users', [
            'name' => 'موظف كلية',
            'email' => 'faculty-teller@example.com',
            'password' => 'password123',
            'role' => 'teller',
            'counter_name' => 'شباك كلية',
        ])
            ->assertCreated()
            ->assertJsonCount(count(Faculty::slugs()), 'user.assigned_faculties')
            ->assertJsonPath('user.assigned_faculties.0.value', Faculty::IndustryEnergy);
    }

    public function test_users_index_includes_faculties_catalog(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'faculties' => [
                    ['value', 'label'],
                ],
            ]);
    }

    public function test_me_endpoint_returns_assigned_faculties_for_teller(): void
    {
        $teller = User::factory()->teller()->forFaculties([
            Faculty::HealthSciences,
        ])->create();
        Sanctum::actingAs($teller);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.assigned_faculties.0.value', Faculty::HealthSciences)
            ->assertJsonMissingPath('user.assigned_faculties.1');
    }

    public function test_teller_call_next_skips_current_student_tickets_outside_assigned_faculties(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()
            ->forQueueLanes(['current_student'])
            ->forFaculties([Faculty::IndustryEnergy])
            ->create();

        QueueTicket::factory()->waiting()->currentStudent()->create([
            'ticket_number' => 1,
            'college' => Faculty::HealthSciences,
        ]);
        $assignedTicket = QueueTicket::factory()->waiting()->currentStudent()->create([
            'ticket_number' => 2,
            'college' => Faculty::IndustryEnergy,
        ]);

        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/call-next')
            ->assertOk()
            ->assertJsonPath('ticket.id', $assignedTicket->id);

        $this->assertSame(TicketStatus::Serving, $assignedTicket->fresh()->status);
    }

    public function test_teller_tickets_list_only_includes_assigned_faculty_current_students(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()
            ->forQueueLanes(['current_student'])
            ->forFaculties([Faculty::IndustryEnergy])
            ->create();

        $visible = QueueTicket::factory()->waiting()->currentStudent()->create([
            'ticket_number' => 10,
            'college' => Faculty::IndustryEnergy,
        ]);
        QueueTicket::factory()->waiting()->currentStudent()->create([
            'ticket_number' => 11,
            'college' => Faculty::HealthSciences,
        ]);

        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.id', $visible->id);
    }

    public function test_teller_cannot_mark_file_delivered_for_other_faculty(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()
            ->forFaculties([Faculty::IndustryEnergy])
            ->forProcessSteps([ProcessStep::FileDelivered->value])
            ->create();

        $ticket = QueueTicket::factory()->currentStudent()->create([
            'ticket_number' => 20,
            'college' => Faculty::HealthSciences,
            'status' => TicketStatus::Serving,
            'entered_at' => now(),
            'documents_reviewed_at' => now(),
            'user_id' => $teller->id,
            'called_at' => now(),
        ]);

        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-file-delivered')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);

        $this->assertNull($ticket->fresh()->file_delivered_at);
    }

    public function test_teller_can_mark_file_delivered_for_assigned_faculty(): void
    {
        QueueSystemSetting::query()->updateOrCreate(
            ['id' => 1],
            ['is_open' => true, 'day_ended_at' => null],
        );

        $teller = User::factory()->teller()
            ->forFaculties([Faculty::IndustryEnergy])
            ->forProcessSteps([ProcessStep::FileDelivered->value])
            ->create();

        $ticket = QueueTicket::factory()->currentStudent()->create([
            'ticket_number' => 21,
            'college' => Faculty::IndustryEnergy,
            'status' => TicketStatus::Serving,
            'entered_at' => now(),
            'documents_reviewed_at' => now(),
            'user_id' => $teller->id,
            'called_at' => now(),
        ]);

        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-file-delivered')
            ->assertOk();

        $this->assertNotNull($ticket->fresh()->file_delivered_at);
    }

    public function test_teller_tickets_list_only_includes_assigned_faculty(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()
            ->forQueueLanes(['nomination_card', 'current_student'])
            ->forFaculties([Faculty::IndustryEnergy])
            ->create();

        $visible = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 30,
            'request_type' => 'nomination_card',
            'college' => Faculty::IndustryEnergy,
        ]);
        QueueTicket::factory()->waiting()->currentStudent()->create([
            'ticket_number' => 31,
            'college' => Faculty::HealthSciences,
        ]);

        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.id', $visible->id);
    }

    public function test_manager_is_not_constrained_by_assigned_faculties(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        $ticket = QueueTicket::factory()->currentStudent()->create([
            'ticket_number' => 40,
            'college' => Faculty::HealthSciences,
            'status' => TicketStatus::Serving,
            'entered_at' => now(),
            'documents_reviewed_at' => now(),
            'user_id' => $manager->id,
            'called_at' => now(),
        ]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-file-delivered')
            ->assertOk();

        $this->assertNotNull($ticket->fresh()->file_delivered_at);
    }
}

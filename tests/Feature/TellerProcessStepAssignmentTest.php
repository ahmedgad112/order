<?php

namespace Tests\Feature;

use App\Enums\ProcessStep;
use App\Enums\TicketStatus;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TellerProcessStepAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_process_steps_to_a_teller(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/users/'.$teller->id, [
            'process_steps' => [
                ProcessStep::Entered->value,
                ProcessStep::Paid->value,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('user.process_steps.0.value', ProcessStep::Entered->value)
            ->assertJsonPath('user.process_steps.1.value', ProcessStep::Paid->value);

        $this->assertSame(
            [ProcessStep::Entered->value, ProcessStep::Paid->value],
            $teller->fresh()->processStepValues(),
        );
    }

    public function test_teller_cannot_mark_unassigned_process_step(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forProcessSteps([
            ProcessStep::Entered->value,
        ])->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 3,
            'entered_at' => now(),
            'status' => TicketStatus::Serving,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-paid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ticket']);

        $this->assertNull($ticket->fresh()->paid_at);
    }

    public function test_teller_can_mark_assigned_process_step(): void
    {
        QueueSystemSetting::query()->updateOrCreate(
            ['id' => 1],
            ['is_open' => true, 'day_ended_at' => null],
        );

        $teller = User::factory()->teller()->forProcessSteps([
            ProcessStep::Entered->value,
            ProcessStep::Paid->value,
        ])->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 4,
            'status' => TicketStatus::Waiting,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-entered')
            ->assertOk();

        $this->assertNotNull($ticket->fresh()->entered_at);
    }

    public function test_manager_is_not_constrained_by_process_steps(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 5,
            'entered_at' => now(),
            'status' => TicketStatus::Serving,
        ]);
        Sanctum::actingAs($manager);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/mark-paid')
            ->assertOk();

        $this->assertNotNull($ticket->fresh()->paid_at);
    }

    public function test_me_endpoint_returns_assigned_process_steps_for_teller(): void
    {
        $teller = User::factory()->teller()->forProcessSteps([
            ProcessStep::MedicalChecked->value,
            'completed',
        ])->create();
        Sanctum::actingAs($teller);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.process_steps.0.value', ProcessStep::MedicalChecked->value)
            ->assertJsonPath('user.process_steps.1.value', 'completed')
            ->assertJsonMissingPath('user.process_steps.2');
    }

    public function test_users_index_includes_process_steps_catalog(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'process_steps' => [
                    ['value', 'label', 'enabled'],
                ],
            ]);
    }
}

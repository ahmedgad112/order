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

class BulkProcessStepMarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_teller_can_bulk_mark_process_step_on_eligible_tickets(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forProcessSteps([
            ProcessStep::MedicalChecked->value,
        ])->create();

        $readyOne = QueueTicket::factory()->fileWithdrawn($teller)->create([
            'ticket_number' => 1,
            'college' => Faculty::IndustryEnergy,
        ]);
        $readyTwo = QueueTicket::factory()->fileWithdrawn($teller)->create([
            'ticket_number' => 2,
            'college' => Faculty::IndustryEnergy,
        ]);
        $alreadyDone = QueueTicket::factory()->medicalChecked($teller)->create([
            'ticket_number' => 3,
            'college' => Faculty::IndustryEnergy,
        ]);

        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/services/'.ProcessStep::MedicalChecked->value.'/complete-bulk', [
            'ticket_ids' => [$readyOne->id, $readyTwo->id, $alreadyDone->id],
        ])
            ->assertOk()
            ->assertJsonPath('updated_count', 2)
            ->assertJsonPath('failed_count', 1);

        $this->assertNotNull($readyOne->fresh()->medical_checked_at);
        $this->assertNotNull($readyTwo->fresh()->medical_checked_at);
    }

    public function test_bulk_mark_rejects_empty_ticket_ids(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/services/'.ProcessStep::Paid->value.'/complete-bulk', [
            'ticket_ids' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_ids']);
    }

    public function test_bulk_mark_forbids_teller_without_assigned_step(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forProcessSteps([
            ProcessStep::Paid->value,
        ])->create();

        $ticket = QueueTicket::factory()->fileWithdrawn($teller)->create([
            'ticket_number' => 4,
            'college' => Faculty::IndustryEnergy,
            'status' => TicketStatus::Serving,
        ]);

        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/services/'.ProcessStep::MedicalChecked->value.'/complete-bulk', [
            'ticket_ids' => [$ticket->id],
        ])
            ->assertOk()
            ->assertJsonPath('updated_count', 0)
            ->assertJsonPath('failed_count', 1);

        $this->assertNull($ticket->fresh()->medical_checked_at);
    }

    public function test_unauthenticated_bulk_mark_returns_401(): void
    {
        $this->postJson('/api/teller/tickets/services/'.ProcessStep::Paid->value.'/complete-bulk', [
            'ticket_ids' => [1],
        ])->assertUnauthorized();
    }
}

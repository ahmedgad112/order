<?php

namespace Tests\Feature;

use App\Enums\ProcessStep;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\College;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TellerQueueLaneAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_assigns_tellers_to_a_queue_lane(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        $assigned = User::factory()->teller('شباك 1')->create(['name' => 'موظف الترشيح']);
        $other = User::factory()->teller('شباك 2')->create(['name' => 'موظف التحويل']);
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/queue-lanes/'.'nomination_card'.'/tellers', [
            'teller_ids' => [$assigned->id],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث موظفي نوع الطلب.')
            ->assertJsonPath('queue_lanes.0.value', 'nomination_card')
            ->assertJsonPath('queue_lanes.0.teller_ids.0', $assigned->id);

        $this->assertContains('nomination_card', $assigned->fresh()->queue_lanes);
        $this->assertNotContains('nomination_card', $other->fresh()->queue_lanes);
        $this->assertContains('transfer', $other->fresh()->queue_lanes);
    }

    public function test_empty_teller_ids_unassigns_all_tellers_from_the_lane(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/queue-lanes/'.'transfer'.'/tellers', [
            'teller_ids' => [],
        ])->assertOk();

        $this->assertNotContains('transfer', $teller->fresh()->queue_lanes);
        $this->assertContains('nomination_card', $teller->fresh()->queue_lanes);
    }

    public function test_returns_401_when_guest_updates_lane_tellers(): void
    {
        $this->putJson('/api/admin/queue-lanes/'.'nomination_card'.'/tellers', [
            'teller_ids' => [],
        ])->assertUnauthorized();
    }

    public function test_returns_403_when_teller_updates_lane_tellers(): void
    {
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($teller);

        $this->putJson('/api/admin/queue-lanes/'.'nomination_card'.'/tellers', [
            'teller_ids' => [$teller->id],
        ])->assertForbidden();
    }

    public function test_returns_404_when_lane_is_invalid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/queue-lanes/unknown-lane/tellers', [
            'teller_ids' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lane']);
    }

    public function test_returns_422_when_teller_ids_includes_a_non_teller(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/queue-lanes/'.'nomination_card'.'/tellers', [
            'teller_ids' => [$manager->id],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['teller_ids.0']);

        $this->assertSame(
            'أحد الموظفين المحددين غير موجود أو ليس موظفاً.',
            $response->json('errors')['teller_ids.0'][0],
        );
    }

    public function test_teller_call_next_skips_tickets_outside_assigned_lanes(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forQueueLanes(['nomination_card'])->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => 'transfer',
            'college' => 'كلية التجارة',
            'national_id' => '29501011234567',
            'order_number' => '111111111',
        ]);
        $assignedTicket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'request_type' => 'nomination_card',
            'college' => College::InformationTechnology,
            'national_id' => '29501011234568',
            'order_number' => '222222222',
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/call-next')
            ->assertOk()
            ->assertJsonPath('ticket.id', $assignedTicket->id)
            ->assertJsonPath('ticket.ticket_number', 'OT2')
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value);
    }

    public function test_returns_422_when_teller_has_no_assigned_lanes(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forQueueLanes([])->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => 'nomination_card',
            'college' => College::InformationTechnology,
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/call-next')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['queue'])
            ->assertJsonPath('errors.queue.0', 'لم يتم تخصيص أي نوع طلب لحسابك.');
    }

    public function test_returns_422_when_waiting_tickets_are_outside_assigned_lanes(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forQueueLanes(['nomination_card'])->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => 'transfer',
            'college' => 'كلية التجارة',
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/call-next')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['queue'])
            ->assertJsonPath('errors.queue.0', 'لا توجد تذاكر في الانتظار لنوع الطلب المخصص لك.');
    }

    public function test_teller_ticket_list_hides_tickets_outside_assigned_lanes(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forQueueLanes(['nomination_card'])->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => 'nomination_card',
            'college' => College::InformationTechnology,
            'national_id' => '29501011234567',
            'order_number' => '111111111',
        ]);
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'request_type' => 'transfer',
            'college' => 'كلية التجارة',
            'national_id' => '29501011234568',
            'order_number' => '222222222',
        ]);
        QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 3,
        ]);
        Sanctum::actingAs($teller);

        $this->getJson('/api/teller/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.ticket_number', 'OT1')
            ->assertJsonPath('tickets.0.request_type', 'nomination_card');
    }

    public function test_teller_call_next_serves_document_completion_tickets_on_their_own_lane(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forQueueLanes(['document_completion'])->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => 'nomination_card',
            'college' => College::InformationTechnology,
            'national_id' => '29501011234567',
            'order_number' => '111111111',
        ]);
        $assignedTicket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'request_type' => 'document_completion',
            'completion_step' => ProcessStep::MedicalChecked,
            'college' => 'كلية الهندسة',
            'national_id' => '29501011234568',
            'order_number' => '222222222',
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/call-next')
            ->assertOk()
            ->assertJsonPath('ticket.id', $assignedTicket->id)
            ->assertJsonPath('ticket.request_type', 'document_completion')
            ->assertJsonPath('ticket.completion_step', ProcessStep::MedicalChecked->value)
            ->assertJsonPath('ticket.request_type_label', 'استكمال أوراق — كشف طبي');
    }

    public function test_admin_dashboard_includes_queue_lane_assignments(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller('شباك 1')->create(['name' => 'أحمد']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('queue_lanes.0.value', 'nomination_card')
            ->assertJsonPath('queue_lanes.0.teller_ids.0', $teller->id)
            ->assertJsonPath('queue_lanes.3.value', 'document_completion')
            ->assertJsonPath('queue_lanes.4.value', 'current_student');
    }

    public function test_public_queue_status_does_not_include_queue_lane_assignments(): void
    {
        QueueSystemSetting::current();

        $response = $this->getJson('/api/public/queue-status')->assertOk();

        $this->assertArrayNotHasKey('queue_lanes', $response->json());
        $this->assertArrayNotHasKey('queue_lanes', $response->json('system') ?? []);
    }

    public function test_creating_a_teller_without_queue_lanes_assigns_all_lanes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users', [
            'name' => 'موظف جديد',
            'email' => 'lane-teller@queue.local',
            'password' => 'password123',
            'role' => UserRole::Teller->value,
            'counter_name' => 'شباك 9',
        ])
            ->assertCreated()
            ->assertJsonCount(5, 'user.queue_lanes')
            ->assertJsonPath('user.queue_lanes.0.value', 'nomination_card');
    }

    public function test_users_index_includes_queue_lane_options(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('queue_lanes.0.value', 'nomination_card')
            ->assertJsonPath('queue_lanes.3.value', 'document_completion')
            ->assertJsonPath('queue_lanes.4.value', 'current_student')
            ->assertJsonCount(5, 'queue_lanes');
    }

    public function test_super_admin_updates_teller_queue_lanes_from_user_management(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/users/'.$teller->id, [
            'queue_lanes' => ['transfer', 'current_student'],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'user.queue_lanes')
            ->assertJsonPath('user.queue_lanes.0.value', 'transfer')
            ->assertJsonPath('user.queue_lanes.1.value', 'current_student');

        $this->assertSame(
            ['transfer', 'current_student'],
            $teller->fresh()->queue_lanes,
        );
    }
}

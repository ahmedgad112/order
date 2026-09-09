<?php

namespace Tests\Feature;

use App\Enums\College;
use App\Enums\QueueLane;
use App\Enums\RequestType;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
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

        $this->putJson('/api/admin/queue-lanes/'.QueueLane::NominationCard->value.'/tellers', [
            'teller_ids' => [$assigned->id],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث موظفي نوع الطلب.')
            ->assertJsonPath('queue_lanes.0.value', QueueLane::NominationCard->value)
            ->assertJsonPath('queue_lanes.0.teller_ids.0', $assigned->id);

        $this->assertContains(QueueLane::NominationCard->value, $assigned->fresh()->queue_lanes);
        $this->assertNotContains(QueueLane::NominationCard->value, $other->fresh()->queue_lanes);
        $this->assertContains(QueueLane::Transfer->value, $other->fresh()->queue_lanes);
    }

    public function test_empty_teller_ids_unassigns_all_tellers_from_the_lane(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/queue-lanes/'.QueueLane::Transfer->value.'/tellers', [
            'teller_ids' => [],
        ])->assertOk();

        $this->assertNotContains(QueueLane::Transfer->value, $teller->fresh()->queue_lanes);
        $this->assertContains(QueueLane::NominationCard->value, $teller->fresh()->queue_lanes);
    }

    public function test_returns_401_when_guest_updates_lane_tellers(): void
    {
        $this->putJson('/api/admin/queue-lanes/'.QueueLane::NominationCard->value.'/tellers', [
            'teller_ids' => [],
        ])->assertUnauthorized();
    }

    public function test_returns_403_when_teller_updates_lane_tellers(): void
    {
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($teller);

        $this->putJson('/api/admin/queue-lanes/'.QueueLane::NominationCard->value.'/tellers', [
            'teller_ids' => [$teller->id],
        ])->assertForbidden();
    }

    public function test_returns_404_when_lane_is_invalid(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/queue-lanes/unknown-lane/tellers', [
            'teller_ids' => [],
        ])->assertNotFound();
    }

    public function test_returns_422_when_teller_ids_includes_a_non_teller(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/queue-lanes/'.QueueLane::NominationCard->value.'/tellers', [
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
        $teller = User::factory()->teller()->forQueueLanes([QueueLane::NominationCard])->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => RequestType::Transfer,
            'college' => 'كلية التجارة',
            'national_id' => '29501011234567',
            'order_number' => '111111111',
        ]);
        $assignedTicket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'request_type' => RequestType::NominationCard,
            'college' => College::InformationTechnology->value,
            'national_id' => '29501011234568',
            'order_number' => '222222222',
        ]);
        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/call-next')
            ->assertOk()
            ->assertJsonPath('ticket.id', $assignedTicket->id)
            ->assertJsonPath('ticket.ticket_number', 'N2')
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value);
    }

    public function test_returns_422_when_teller_has_no_assigned_lanes(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller()->forQueueLanes([])->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => RequestType::NominationCard,
            'college' => College::InformationTechnology->value,
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
        $teller = User::factory()->teller()->forQueueLanes([QueueLane::NominationCard])->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => RequestType::Transfer,
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
        $teller = User::factory()->teller()->forQueueLanes([QueueLane::NominationCard])->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => RequestType::NominationCard,
            'college' => College::InformationTechnology->value,
            'national_id' => '29501011234567',
            'order_number' => '111111111',
        ]);
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 2,
            'request_type' => RequestType::Transfer,
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
            ->assertJsonPath('tickets.0.ticket_number', 'N1')
            ->assertJsonPath('tickets.0.request_type', RequestType::NominationCard->value);
    }

    public function test_admin_dashboard_includes_queue_lane_assignments(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller('شباك 1')->create(['name' => 'أحمد']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('queue_lanes.0.value', QueueLane::NominationCard->value)
            ->assertJsonPath('queue_lanes.0.teller_ids.0', $teller->id)
            ->assertJsonPath('queue_lanes.3.value', QueueLane::CurrentStudent->value);
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
            ->assertJsonCount(4, 'user.queue_lanes')
            ->assertJsonPath('user.queue_lanes.0.value', QueueLane::NominationCard->value);
    }

    public function test_users_index_includes_queue_lane_options(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonPath('queue_lanes.0.value', QueueLane::NominationCard->value)
            ->assertJsonPath('queue_lanes.3.value', QueueLane::CurrentStudent->value)
            ->assertJsonCount(4, 'queue_lanes');
    }

    public function test_super_admin_updates_teller_queue_lanes_from_user_management(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $teller = User::factory()->teller()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/users/'.$teller->id, [
            'queue_lanes' => [QueueLane::Transfer->value, QueueLane::CurrentStudent->value],
        ])
            ->assertOk()
            ->assertJsonCount(2, 'user.queue_lanes')
            ->assertJsonPath('user.queue_lanes.0.value', QueueLane::Transfer->value)
            ->assertJsonPath('user.queue_lanes.1.value', QueueLane::CurrentStudent->value);

        $this->assertSame(
            [QueueLane::Transfer->value, QueueLane::CurrentStudent->value],
            $teller->fresh()->queue_lanes,
        );
    }
}

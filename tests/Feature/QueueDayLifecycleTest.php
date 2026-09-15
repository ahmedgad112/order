<?php

namespace Tests\Feature;

use App\Enums\RequestType;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QueueDayLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_issue_ticket_when_the_day_has_ended(): void
    {
        $settings = QueueSystemSetting::current();
        $settings->update([
            'day_ended_at' => now(),
        ]);

        $this->issueTicketAsStaff([
            'full_name' => 'محمد أحمد علي',
            'order_number' => '123456789',
            'request_type' => RequestType::NominationCard->value,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['system']);

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_teller_can_call_next_after_the_day_has_ended(): void
    {
        QueueSystemSetting::current();
        $teller = User::factory()->teller('شباك 1')->create();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
        ]);

        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/system/end-day')
            ->assertOk()
            ->assertJsonPath('system.is_open', true)
            ->assertJsonPath('system.is_day_open', false);

        Sanctum::actingAs($teller);
        $this->postJson('/api/teller/call-next')
            ->assertOk()
            ->assertJsonPath('ticket.ticket_number', 'OT1')
            ->assertJsonPath('ticket.status', TicketStatus::Serving->value);
    }

    public function test_opening_a_new_day_restarts_ticket_numbers_and_keeps_archived_tickets(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        $archived = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 3,
            'full_name' => 'طلب مؤرشف',
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/system/end-day')->assertOk();
        $this->postJson('/api/admin/system/open-day')
            ->assertOk()
            ->assertJsonPath('system.is_day_open', true)
            ->assertJsonPath('system.accepting_tickets', true);

        $this->assertModelExists($archived);

        $this->issueTicketAsStaff([
            'full_name' => 'عميل اليوم الجديد',
            'order_number' => '987654321',
            'request_type' => RequestType::NominationCard->value,
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT1');

        $this->assertDatabaseCount('queue_tickets', 2);
        $this->assertDatabaseHas('queue_tickets', [
            'id' => $archived->id,
            'ticket_number' => 3,
            'full_name' => 'طلب مؤرشف',
        ]);
    }

    public function test_returns_422_when_ending_an_already_ended_day(): void
    {
        $settings = QueueSystemSetting::current();
        $settings->update([
            'day_ended_at' => now(),
        ]);
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/system/end-day')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['system']);
    }

    public function test_returns_422_when_opening_a_day_that_is_already_open(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/system/open-day')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['system']);
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
    public function test_returns_403_when_non_super_admin_ends_the_day(UserRole $role): void
    {
        QueueSystemSetting::current();
        $user = match ($role) {
            UserRole::Teller => User::factory()->teller()->create(),
            UserRole::Manager => User::factory()->manager()->create(),
            UserRole::SuperAdmin => User::factory()->superAdmin()->create(),
        };
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/system/end-day')->assertForbidden();
        $this->assertNull(QueueSystemSetting::query()->value('day_ended_at'));
    }
}

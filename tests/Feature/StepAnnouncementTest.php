<?php

namespace Tests\Feature;

use App\Jobs\GenerateTicketAudioJob;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\StepAnnouncement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StepAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_all_steps_with_defaults(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/admin/step-announcements')
            ->assertOk()
            ->assertJsonCount(7, 'steps')
            ->assertJsonPath('steps.1.step', 'paid')
            ->assertJsonPath('steps.1.enabled', true)
            ->assertJsonPath('steps.1.destination', 'سحب الملف')
            ->assertJsonPath('steps.0.step', 'entered')
            ->assertJsonPath('steps.0.destination', null);
    }

    public function test_super_admin_can_update_step_announcements(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson('/api/admin/step-announcements', [
            'steps' => [
                ['step' => 'paid', 'enabled' => false, 'destination' => 'شباك الدفع'],
                ['step' => 'entered', 'enabled' => true, 'destination' => null],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('steps.1.enabled', false)
            ->assertJsonPath('steps.1.destination', 'شباك الدفع');

        $this->assertDatabaseHas('step_announcements', [
            'step' => 'paid',
            'enabled' => false,
            'destination' => 'شباك الدفع',
        ]);
    }

    public function test_manager_and_teller_cannot_manage_step_announcements(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->putJson('/api/admin/step-announcements', [
            'steps' => [['step' => 'paid', 'enabled' => true]],
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->teller()->create());

        $this->getJson('/api/admin/step-announcements')->assertForbidden();
    }

    public function test_unknown_step_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson('/api/admin/step-announcements', [
            'steps' => [['step' => 'nonsense', 'enabled' => true]],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['steps.0.step']);
    }

    public function test_marking_step_dispatches_audio_job_with_step(): void
    {
        Queue::fake();
        QueueSystemSetting::current();
        Sanctum::actingAs($teller = User::factory()->teller()->create());
        $ticket = QueueTicket::factory()->serving($teller)->create();

        $this->postJson("/api/teller/tickets/{$ticket->id}/mark-paid")->assertOk();

        Queue::assertPushed(
            GenerateTicketAudioJob::class,
            fn (GenerateTicketAudioJob $job): bool => $job->step === 'paid',
        );
    }

    public function test_ticket_audio_returns_null_for_muted_step(): void
    {
        QueueSystemSetting::current();
        StepAnnouncement::forStep('paid')->update(['enabled' => false]);
        $ticket = QueueTicket::factory()->serving()->create();

        $this->postJson('/api/public/ticket-audio', [
            'ticket_id' => $ticket->id,
            'step' => 'paid',
        ])
            ->assertOk()
            ->assertJsonPath('audio_url', null);
    }

    public function test_super_admin_can_update_call_template(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson('/api/admin/system/call-template', [
            'call_template' => 'العميل {order} إلى {counter}',
        ])
            ->assertOk()
            ->assertJsonPath('system.call_template', 'العميل {order} إلى {counter}');

        $this->assertSame(
            'العميل {order} إلى {counter}',
            QueueSystemSetting::current()->call_template,
        );
    }

    public function test_call_template_requires_order_and_counter_placeholders(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->putJson('/api/admin/system/call-template', [
            'call_template' => 'نص بدون متغيرات',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['call_template']);
    }
}

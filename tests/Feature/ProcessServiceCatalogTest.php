<?php

namespace Tests\Feature;

use App\Enums\ProcessStep;
use App\Enums\TicketStatus;
use App\Models\ProcessService;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProcessServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_custom_process_service(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/process-services', [
            'label' => 'تصوير مستندات',
            'flow' => ProcessService::FLOW_ADMISSION,
            'sort_order' => 25,
        ])
            ->assertCreated()
            ->assertJsonPath('process_service.label', 'تصوير مستندات')
            ->assertJsonPath('process_service.is_system', false)
            ->assertJsonPath('process_service.flow', ProcessService::FLOW_ADMISSION);

        $this->assertDatabaseHas('process_services', [
            'label' => 'تصوير مستندات',
            'is_system' => false,
        ]);
    }

    public function test_can_delete_system_process_service(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $system = ProcessService::findBySystemKey(ProcessStep::Paid->value);
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/admin/process-services/'.$system->id)
            ->assertOk();

        $this->assertDatabaseMissing('process_services', ['id' => $system->id]);
        $this->assertFalse(ProcessService::isSystemStepEnabled(ProcessStep::Paid->value));
    }

    public function test_can_update_system_process_service_flow_and_label(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $system = ProcessService::findBySystemKey(ProcessStep::MedicalChecked->value);
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/process-services/'.$system->id, [
            'label' => 'كشف طبي معدّل',
            'flow' => ProcessService::FLOW_BOTH,
            'is_enabled' => true,
            'sort_order' => 55,
        ])
            ->assertOk()
            ->assertJsonPath('process_service.label', 'كشف طبي معدّل')
            ->assertJsonPath('process_service.flow', ProcessService::FLOW_BOTH)
            ->assertJsonPath('process_service.sort_order', 55);
    }

    public function test_can_disable_system_process_service(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $system = ProcessService::findBySystemKey(ProcessStep::MedicalChecked->value);
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/process-services/'.$system->id, [
            'is_enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('process_service.is_enabled', false);

        $this->assertFalse(ProcessService::isSystemStepEnabled(ProcessStep::MedicalChecked->value));
    }

    public function test_can_delete_custom_process_service(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $custom = ProcessService::query()->create([
            'slug' => 'photo-docs',
            'label' => 'تصوير',
            'flow' => ProcessService::FLOW_BOTH,
            'is_system' => false,
            'is_enabled' => true,
            'sort_order' => 90,
        ]);
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/admin/process-services/'.$custom->id)
            ->assertOk();

        $this->assertDatabaseMissing('process_services', ['id' => $custom->id]);
    }

    public function test_teller_can_complete_custom_service_in_pipeline(): void
    {
        QueueSystemSetting::current();
        ProcessService::query()->where('system_key', ProcessStep::Paid->value)->update(['is_enabled' => false]);
        ProcessService::flushCatalog();

        $custom = ProcessService::query()->create([
            'slug' => 'extra-check',
            'label' => 'فحص إضافي',
            'flow' => ProcessService::FLOW_ADMISSION,
            'is_system' => false,
            'is_enabled' => true,
            'sort_order' => 25,
        ]);

        $teller = User::factory()->teller()->forProcessSteps([
            ProcessStep::Entered->value,
            'extra-check',
            ProcessStep::FileWithdrawn->value,
        ])->create();

        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 11,
            'entered_at' => now(),
            'status' => TicketStatus::Serving,
        ]);

        Sanctum::actingAs($teller);

        $this->postJson('/api/teller/tickets/'.$ticket->id.'/services/extra-check/complete')
            ->assertOk()
            ->assertJsonPath('ticket.completed_service_slugs.0', 'extra-check');

        $this->assertDatabaseHas('ticket_service_completions', [
            'queue_ticket_id' => $ticket->id,
            'process_service_id' => $custom->id,
        ]);
    }

    public function test_manager_cannot_manage_process_services_without_control_system(): void
    {
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->getJson('/api/admin/process-services')->assertForbidden();
        $this->postJson('/api/admin/process-services', [
            'label' => 'خدمة جديدة',
        ])->assertForbidden();
    }

    public function test_ticket_pipeline_excludes_disabled_services(): void
    {
        ProcessService::query()->where('system_key', ProcessStep::MedicalChecked->value)->update(['is_enabled' => false]);
        ProcessService::flushCatalog();

        $ticket = QueueTicket::factory()->waiting()->create(['ticket_number' => 12]);
        $keys = collect($ticket->processPipelinePayload())->pluck('key')->all();

        $this->assertNotContains(ProcessStep::MedicalChecked->value, $keys);
        $this->assertContains(ProcessStep::Entered->value, $keys);
    }
}

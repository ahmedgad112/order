<?php

namespace Tests\Feature;

use App\Enums\ProcessStep;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_list_request_types(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/admin/request-types')
            ->assertOk()
            ->assertJsonCount(4, 'request_types')
            ->assertJsonPath('request_types.0.value', 'nomination_card')
            ->assertJsonPath('request_types.0.code_prefix', 'OT')
            ->assertJsonPath('request_types.0.tickets_count', 0);
    }

    public function test_super_admin_can_create_request_type_with_generated_prefix(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/request-types', [
            'label' => 'منحة تفوق',
            'college_mode' => 'text',
            'college_label' => 'الكلية',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'تم إضافة نوع الطلب بنجاح.')
            ->assertJsonCount(5, 'request_types');

        $type = RequestType::query()->where('label', 'منحة تفوق')->firstOrFail();

        $this->assertNotNull($type->slug);
        $this->assertSame('OA', $type->code_prefix);
        $this->assertTrue($type->enabled);
    }

    public function test_generated_prefixes_do_not_collide(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/request-types', [
            'label' => 'نوع أول',
            'college_mode' => 'text',
        ])->assertCreated();

        $this->postJson('/api/admin/request-types', [
            'label' => 'نوع ثاني',
            'college_mode' => 'text',
        ])->assertCreated();

        $prefixes = RequestType::prefixes();

        $this->assertSame(count($prefixes), count(array_unique($prefixes)));
        $this->assertContains('OA', $prefixes);
        $this->assertContains('OC', $prefixes);
    }

    public function test_manager_and_teller_cannot_manage_request_types(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/request-types', [
            'label' => 'نوع',
            'college_mode' => 'text',
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/admin/request-types', [
            'label' => 'نوع',
            'college_mode' => 'text',
        ])->assertForbidden();
    }

    public function test_returns_422_when_creating_request_type_without_label(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/request-types', [
            'college_mode' => 'text',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label']);
    }

    public function test_new_request_type_is_issuable_and_uses_generated_prefix(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/request-types', [
            'label' => 'منحة تفوق',
            'college_mode' => 'text',
            'college_label' => 'الكلية',
        ])->assertCreated();

        $slug = RequestType::query()->where('label', 'منحة تفوق')->value('slug');

        $this->issueTicketAsStaff([
            'request_type' => $slug,
        ])
            ->assertCreated()
            ->assertJsonPath('ticket.request_type', $slug);

        $ticket = QueueTicket::query()->firstOrFail();

        $this->assertSame('OA1', $ticket->ticketCode());
        $this->assertSame($slug, $ticket->queueLaneValue());
    }

    public function test_ticket_code_parsing_recognizes_generated_prefix(): void
    {
        QueueSystemSetting::current();
        RequestType::query()->create([
            'slug' => 'scholarship',
            'label' => 'منحة',
            'code_prefix' => 'OA',
            'college_mode' => 'text',
            'enabled' => true,
        ]);

        QueueTicket::factory()->create([
            'ticket_number' => 7,
            'request_type' => 'scholarship',
        ]);

        $parsed = QueueTicket::parseTicketCode('OA7');

        $this->assertNotNull($parsed);
        $this->assertSame('scholarship', $parsed[1]);
        $this->assertSame(7, $parsed[2]);
    }

    public function test_super_admin_can_update_request_type(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $type = RequestType::findBySlug('transfer');

        $this->putJson('/api/admin/request-types/'.$type->id, [
            'label' => 'تحويل جديد',
            'college_mode' => 'select',
            'college_label' => 'الكلية المحول إليها',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث نوع الطلب بنجاح.');

        $fresh = $type->fresh();

        $this->assertSame('تحويل جديد', $fresh->label);
        $this->assertSame('select', $fresh->college_mode);
        $this->assertSame('OB', $fresh->code_prefix);
        $this->assertSame('transfer', $fresh->slug);
    }

    public function test_disabling_type_hides_it_from_issue_options_but_keeps_tickets(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $type = RequestType::findBySlug('transfer');

        $this->putJson('/api/admin/request-types/'.$type->id, [
            'enabled' => false,
        ])->assertOk();

        $this->issueTicketAsStaff([
            'request_type' => 'transfer',
        ])->assertUnprocessable();

        $this->assertNotContains('transfer', RequestType::enabledSlugs());
        $this->assertContains('transfer', RequestType::laneValues());
    }

    public function test_cannot_disable_the_last_enabled_request_type(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        RequestType::query()
            ->where('slug', '!=', 'transfer')
            ->update(['enabled' => false]);
        RequestType::flushCatalog();

        $type = RequestType::findBySlug('transfer');

        $this->putJson('/api/admin/request-types/'.$type->id, [
            'enabled' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.enabled.0', 'يجب إبقاء نوع طلب واحد على الأقل ظاهراً.');
    }

    public function test_cannot_delete_request_type_with_tickets(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        QueueTicket::factory()->create(['request_type' => 'transfer']);

        $type = RequestType::findBySlug('transfer');

        $this->deleteJson('/api/admin/request-types/'.$type->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.request_type.0', 'لا يمكن حذف نوع طلب له تذاكر مسجلة. عطّله بدلاً من ذلك.');

        $this->assertDatabaseHas('request_types', ['slug' => 'transfer']);
    }

    public function test_can_delete_request_type_without_tickets(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $type = RequestType::query()->create([
            'slug' => 'temporary',
            'label' => 'نوع مؤقت',
            'code_prefix' => 'OA',
            'college_mode' => 'text',
            'enabled' => true,
        ]);

        $this->deleteJson('/api/admin/request-types/'.$type->id)
            ->assertOk()
            ->assertJsonPath('message', 'تم حذف نوع الطلب.');

        $this->assertDatabaseMissing('request_types', ['slug' => 'temporary']);
        $this->assertNotContains('temporary', RequestType::laneValues());
    }

    public function test_tellers_can_be_assigned_to_custom_request_type_lane(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/request-types', [
            'label' => 'منحة',
            'college_mode' => 'text',
        ])->assertCreated();

        $slug = RequestType::query()->where('label', 'منحة')->value('slug');
        $teller = User::factory()->teller()->forQueueLanes([])->create();

        $this->putJson('/api/admin/queue-lanes/'.$slug.'/tellers', [
            'teller_ids' => [$teller->id],
        ])
            ->assertOk()
            ->assertJsonPath('queue_lanes.4.value', $slug)
            ->assertJsonPath('queue_lanes.4.teller_ids.0', $teller->id);
    }

    public function test_request_type_counter_name_is_stored_and_returned(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/request-types', [
            'label' => 'دفع الرسوم',
            'college_mode' => 'text',
            'counter_name' => 'شباك الدفع',
        ])
            ->assertCreated()
            ->assertJsonPath('request_types.4.counter_name', 'شباك الدفع');

        $this->assertSame(
            'شباك الدفع',
            RequestType::query()->where('label', 'دفع الرسوم')->value('counter_name'),
        );
    }

    public function test_request_type_counter_name_can_be_updated_and_cleared(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $type = RequestType::findBySlug('transfer');

        $this->putJson('/api/admin/request-types/'.$type->id, [
            'counter_name' => 'شباك التحويل',
        ])->assertOk();

        $this->assertSame('شباك التحويل', $type->fresh()->counter_name);

        $this->putJson('/api/admin/request-types/'.$type->id, [
            'counter_name' => null,
        ])->assertOk();

        $this->assertNull($type->fresh()->counter_name);
    }

    public function test_completion_services_can_be_configured_per_type(): void
    {
        QueueSystemSetting::current();
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/request-types', [
            'label' => 'استكمال سريع',
            'college_mode' => 'text',
            'requires_completion_service' => true,
            'completion_services' => [ProcessStep::Paid->value, ProcessStep::FileDelivered->value],
        ])->assertCreated();

        $slug = RequestType::query()->where('label', 'استكمال سريع')->value('slug');

        $this->getJson('/api/admin/system/status')
            ->assertOk()
            ->assertJsonPath('system.request_types.4.value', $slug)
            ->assertJsonPath('system.request_types.4.requires_completion_service', true)
            ->assertJsonCount(2, 'system.request_types.4.completion_services')
            ->assertJsonPath('system.request_types.4.completion_services.0.value', ProcessStep::Paid->value);
    }
}

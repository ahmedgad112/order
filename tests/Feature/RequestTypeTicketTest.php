<?php

namespace Tests\Feature;

use App\Enums\College;
use App\Enums\ProcessStep;
use App\Enums\RequestType;
use App\Enums\UserRole;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestTypeTicketTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function issuePayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'محمد أحمد علي',
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::InformationTechnology->value,
            'order_number' => '123456789',
        ], $overrides);
    }

    public function test_guest_can_issue_ticket_with_request_type_and_nine_digit_order_number(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'request_type' => RequestType::Transfer->value,
            'college' => 'كلية الهندسة',
            'order_number' => '987654321',
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('queue_tickets', [
            'national_id' => '29501011234567',
            'request_type' => RequestType::Transfer->value,
            'college' => 'كلية الهندسة',
            'order_number' => '987654321',
        ]);
    }

    public function test_request_types_keep_independent_ticket_sequences(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload())
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT1');

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'national_id' => '29501011234568',
            'request_type' => RequestType::Transfer->value,
            'college' => 'كلية الهندسة',
            'order_number' => '123456788',
        ]))
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OB1');

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'national_id' => '29501011234569',
            'request_type' => RequestType::DocumentCompletion->value,
            'completion_step' => ProcessStep::MedicalChecked->value,
            'college' => 'كلية الهندسة',
            'order_number' => '123456787',
        ]))
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OF1');

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'national_id' => '29501011234570',
            'request_type' => RequestType::DirectApplication->value,
            'college' => 'كلية الهندسة',
            'order_number' => '123456786',
        ]))
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OD1');

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'national_id' => '29501011234571',
            'order_number' => '123456785',
        ]))
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OT2');
    }

    public function test_admin_can_search_ticket_by_request_type_code(): void
    {
        QueueSystemSetting::current();
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => RequestType::NominationCard,
            'college' => College::InformationTechnology->value,
            'full_name' => 'طالب ترشيح',
        ]);
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => RequestType::Transfer,
            'college' => 'كلية التجارة',
            'full_name' => 'طالب تحويل',
        ]);
        QueueTicket::factory()->waiting()->create([
            'ticket_number' => 1,
            'request_type' => RequestType::DocumentCompletion,
            'completion_step' => ProcessStep::MedicalChecked,
            'college' => 'كلية الهندسة',
            'full_name' => 'طالب استكمال',
        ]);
        QueueTicket::factory()->currentStudent()->waiting()->create([
            'ticket_number' => 1,
            'full_name' => 'طالب حالي',
        ]);
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/admin/tickets?search=OT1')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'طالب ترشيح')
            ->assertJsonPath('tickets.0.ticket_number', 'OT1');

        $this->getJson('/api/admin/tickets?search=OB1')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'طالب تحويل')
            ->assertJsonPath('tickets.0.ticket_number', 'OB1');

        $this->getJson('/api/admin/tickets?search=OF1')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'طالب استكمال')
            ->assertJsonPath('tickets.0.ticket_number', 'OF1');

        $this->getJson('/api/admin/tickets?search=O1')
            ->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.full_name', 'طالب حالي')
            ->assertJsonPath('tickets.0.ticket_number', 'O1');
    }

    public function test_returns_422_when_request_type_is_missing(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'request_type' => '',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['request_type'])
            ->assertJsonPath('errors.request_type.0', 'يجب اختيار نوع الطلب.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_guest_can_issue_nomination_ticket_with_listed_college(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'college' => College::FoodIndustryTechnology->value,
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('queue_tickets', [
            'national_id' => '29501011234567',
            'request_type' => RequestType::NominationCard->value,
            'college' => College::FoodIndustryTechnology->value,
        ]);
    }

    public function test_returns_422_when_nomination_college_is_missing(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'college' => '',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['college'])
            ->assertJsonPath('errors.college.0', 'يجب تحديد الكلية.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_nomination_college_is_not_listed(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'college' => 'كلية الهندسة',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['college'])
            ->assertJsonPath('errors.college.0', 'يجب اختيار الكلية الواردة في بطاقة الترشيح.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    #[DataProvider('freeTextCollegeRequestTypes')]
    public function test_returns_422_when_free_text_college_is_missing(RequestType $requestType): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'request_type' => $requestType->value,
            'college' => '',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['college'])
            ->assertJsonPath('errors.college.0', 'يجب تحديد الكلية.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    /**
     * @return array<string, array{0: RequestType}>
     */
    public static function freeTextCollegeRequestTypes(): array
    {
        return [
            'direct_application' => [RequestType::DirectApplication],
            'transfer' => [RequestType::Transfer],
        ];
    }

    public function test_public_queue_status_includes_colleges(): void
    {
        QueueSystemSetting::current();

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('system.colleges.0.value', College::InformationTechnology->value)
            ->assertJsonPath('system.colleges.0.label', 'تكنولوجيا المعلومات')
            ->assertJsonPath('system.request_types.0.college_mode', 'select')
            ->assertJsonPath('system.request_types.0.college_label', 'الكلية الواردة في بطاقة الترشيح')
            ->assertJsonPath('system.request_types.1.college_mode', 'text')
            ->assertJsonPath('system.request_types.2.college_mode', 'text')
            ->assertJsonPath('system.request_types.3.college_mode', 'text')
            ->assertJsonPath('system.request_types.3.requires_completion_service', true)
            ->assertJsonCount(3, 'system.request_types.3.completion_services')
            ->assertJsonPath('system.request_types.3.completion_services.0.value', ProcessStep::MedicalChecked->value)
            ->assertJsonPath('system.request_types.3.completion_services.1.value', ProcessStep::FacePrinted->value)
            ->assertJsonPath('system.request_types.3.completion_services.2.value', ProcessStep::FileDelivered->value);
    }

    public function test_returns_422_when_request_type_is_disabled(): void
    {
        QueueSystemSetting::current()->update([
            'enabled_request_types' => [RequestType::NominationCard->value],
        ]);

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'request_type' => RequestType::Transfer->value,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['request_type'])
            ->assertJsonPath('errors.request_type.0', 'نوع الطلب غير متاح حالياً.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    #[DataProvider('invalidOrderNumbers')]
    public function test_returns_422_when_order_number_is_not_nine_digits(string $orderNumber): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'order_number' => $orderNumber,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order_number'])
            ->assertJsonPath('errors.order_number.0', 'يجب أن يتكون رقم الطلب من 9 أرقام بالضبط.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidOrderNumbers(): array
    {
        return [
            'too_short' => ['12345678'],
            'too_long' => ['1234567890'],
            'letters' => ['12345678a'],
            'old_format' => ['ORD-1001'],
        ];
    }

    #[DataProvider('invalidNationalIds')]
    public function test_returns_422_when_national_id_is_not_fourteen_digits(string $nationalId): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'national_id' => $nationalId,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['national_id'])
            ->assertJsonPath('errors.national_id.0', 'يجب أن يتكون الرقم القومي من 14 رقمًا بالضبط.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidNationalIds(): array
    {
        return [
            'too_short' => ['2950101123456'],
            'too_long' => ['295010112345678'],
            'letters' => ['2950101123456a'],
        ];
    }

    public function test_public_queue_status_includes_enabled_request_types(): void
    {
        QueueSystemSetting::current();

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('system.request_types.0.value', RequestType::NominationCard->value)
            ->assertJsonPath('system.request_types.0.label', 'حاصل على بطاقة ترشيح')
            ->assertJsonPath('system.request_types.0.enabled', true)
            ->assertJsonPath('system.request_types.0.college_mode', 'select')
            ->assertJsonPath('system.request_types.1.value', RequestType::DirectApplication->value)
            ->assertJsonPath('system.request_types.1.enabled', true)
            ->assertJsonPath('system.request_types.2.value', RequestType::Transfer->value)
            ->assertJsonPath('system.request_types.2.enabled', true)
            ->assertJsonPath('system.request_types.3.value', RequestType::DocumentCompletion->value)
            ->assertJsonPath('system.request_types.3.label', 'استكمال أوراق')
            ->assertJsonPath('system.request_types.3.enabled', true)
            ->assertJsonPath('system.request_types.3.requires_completion_service', true)
            ->assertJsonCount(3, 'system.request_types.3.completion_services')
            ->assertJsonPath('system.request_types.3.completion_services.0.label', 'كشف طبي')
            ->assertJsonPath('system.request_types.3.completion_services.1.value', ProcessStep::FacePrinted->value)
            ->assertJsonPath('system.request_types.3.completion_services.2.value', ProcessStep::FileDelivered->value);
    }

    public function test_super_admin_can_update_enabled_request_types(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/system/request-types', [
            'enabled_request_types' => [
                RequestType::NominationCard->value,
                RequestType::Transfer->value,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تحديث أنواع الطلبات المتاحة.')
            ->assertJsonPath('system.request_types.0.enabled', true)
            ->assertJsonPath('system.request_types.1.enabled', false)
            ->assertJsonPath('system.request_types.2.enabled', true);

        $this->assertSame(
            [RequestType::NominationCard->value, RequestType::Transfer->value],
            QueueSystemSetting::query()->first()?->enabled_request_types,
        );

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('system.request_types.1.enabled', false);
    }

    public function test_returns_401_when_guest_updates_request_types(): void
    {
        QueueSystemSetting::current();

        $this->putJson('/api/admin/system/request-types', [
            'enabled_request_types' => [RequestType::NominationCard->value],
        ])->assertUnauthorized();
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_returns_403_when_unprivileged_role_updates_request_types(UserRole $role): void
    {
        QueueSystemSetting::current();
        $user = match ($role) {
            UserRole::Teller => User::factory()->teller()->create(),
            UserRole::Manager => User::factory()->manager()->create(),
            UserRole::SuperAdmin => throw new \LogicException('Super admin can update request types.'),
        };
        Sanctum::actingAs($user);

        $this->putJson('/api/admin/system/request-types', [
            'enabled_request_types' => [RequestType::NominationCard->value],
        ])->assertForbidden();
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function unprivilegedRoles(): array
    {
        return [
            'teller' => [UserRole::Teller],
            'manager' => [UserRole::Manager],
        ];
    }

    public function test_returns_422_when_no_request_types_are_enabled(): void
    {
        QueueSystemSetting::current();
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/system/request-types', [
            'enabled_request_types' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['enabled_request_types'])
            ->assertJsonPath('errors.enabled_request_types.0', 'يجب اختيار نوع طلب واحد على الأقل.');
    }

    public function test_manager_can_update_ticket_request_type_even_when_disabled_for_kiosk(): void
    {
        QueueSystemSetting::current()->update([
            'enabled_request_types' => [RequestType::NominationCard->value],
        ]);
        $manager = User::factory()->manager()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 4,
            'request_type' => RequestType::NominationCard,
            'college' => College::InformationTechnology->value,
            'order_number' => '123456789',
        ]);
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => $ticket->full_name,
            'national_id' => $ticket->national_id,
            'request_type' => RequestType::Transfer->value,
            'college' => 'كلية التجارة',
            'order_number' => '123456789',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.request_type', RequestType::Transfer->value)
            ->assertJsonPath('ticket.college', 'كلية التجارة')
            ->assertJsonPath('ticket.college_label', 'كلية التجارة');
    }

    public function test_returns_422_when_manager_updates_nomination_ticket_with_unlisted_college(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 4,
            'request_type' => RequestType::NominationCard,
            'college' => College::InformationTechnology->value,
            'order_number' => '123456789',
        ]);
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => $ticket->full_name,
            'national_id' => $ticket->national_id,
            'request_type' => RequestType::NominationCard->value,
            'college' => 'كلية الهندسة',
            'order_number' => '123456789',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['college'])
            ->assertJsonPath('errors.college.0', 'يجب اختيار الكلية الواردة في بطاقة الترشيح.');

        $this->assertDatabaseHas('queue_tickets', [
            'id' => $ticket->id,
            'college' => College::InformationTechnology->value,
        ]);
    }

    public function test_guest_can_issue_document_completion_ticket_with_selected_process_step(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'request_type' => RequestType::DocumentCompletion->value,
            'completion_step' => ProcessStep::MedicalChecked->value,
            'college' => 'كلية الهندسة',
            'order_number' => '456789123',
        ]))
            ->assertCreated()
            ->assertJsonPath('ticket.ticket_number', 'OF1');

        $ticket = QueueTicket::query()->first();

        $this->assertNotNull($ticket);
        $this->assertSame(RequestType::DocumentCompletion, $ticket->request_type);
        $this->assertSame(ProcessStep::MedicalChecked, $ticket->completion_step);
        $this->assertSame('كلية الهندسة', $ticket->college);
        $this->assertSame('456789123', $ticket->order_number);
        $this->assertNotNull($ticket->entered_at);
        $this->assertNull($ticket->medical_checked_at);
        $this->assertSame('استكمال أوراق — كشف طبي', $ticket->requestTypeLabel());
    }

    public function test_document_completion_face_print_ticket_skips_earlier_checkpoints(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'request_type' => RequestType::DocumentCompletion->value,
            'completion_step' => ProcessStep::FacePrinted->value,
            'college' => 'كلية الهندسة',
        ]))->assertCreated();

        $ticket = QueueTicket::query()->first();

        $this->assertNotNull($ticket);
        $this->assertNotNull($ticket->entered_at);
        $this->assertNotNull($ticket->medical_checked_at);
        $this->assertNull($ticket->face_printed_at);
        $this->assertSame('استكمال أوراق — بصمة وجه', $ticket->requestTypeLabel());
    }

    public function test_returns_422_when_document_completion_step_is_missing(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'request_type' => RequestType::DocumentCompletion->value,
            'college' => 'كلية الهندسة',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['completion_step'])
            ->assertJsonPath('errors.completion_step.0', 'يجب اختيار الخدمة المراد استكمال أوراقها.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_returns_422_when_document_completion_step_is_invalid(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'request_type' => RequestType::DocumentCompletion->value,
            'completion_step' => ProcessStep::Entered->value,
            'college' => 'كلية الهندسة',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['completion_step'])
            ->assertJsonPath('errors.completion_step.0', 'الخدمة المراد استكمال أوراقها غير صحيحة.');

        $this->assertDatabaseCount('queue_tickets', 0);
    }

    public function test_ignores_completion_step_when_request_type_is_not_document_completion(): void
    {
        QueueSystemSetting::current();

        $this->postJson('/api/public/tickets', $this->issuePayload([
            'request_type' => RequestType::Transfer->value,
            'completion_step' => ProcessStep::MedicalChecked->value,
            'college' => 'كلية الهندسة',
        ]))
            ->assertCreated();

        $this->assertDatabaseHas('queue_tickets', [
            'national_id' => '29501011234567',
            'request_type' => RequestType::Transfer->value,
            'completion_step' => null,
            'college' => 'كلية الهندسة',
        ]);
    }

    public function test_manager_can_update_ticket_to_document_completion(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 6,
            'request_type' => RequestType::Transfer,
            'college' => 'كلية التجارة',
            'order_number' => '123456789',
        ]);
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => $ticket->full_name,
            'national_id' => $ticket->national_id,
            'request_type' => RequestType::DocumentCompletion->value,
            'completion_step' => ProcessStep::FileDelivered->value,
            'college' => 'كلية التجارة',
            'order_number' => '123456789',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.request_type', RequestType::DocumentCompletion->value)
            ->assertJsonPath('ticket.completion_step', ProcessStep::FileDelivered->value)
            ->assertJsonPath('ticket.request_type_label', 'استكمال أوراق — تسليم ملف');
    }

    public function test_manager_update_clears_completion_step_when_leaving_document_completion(): void
    {
        QueueSystemSetting::current();
        $manager = User::factory()->manager()->create();
        $ticket = QueueTicket::factory()->waiting()->create([
            'ticket_number' => 7,
            'request_type' => RequestType::DocumentCompletion,
            'completion_step' => ProcessStep::MedicalChecked,
            'college' => 'كلية الهندسة',
            'order_number' => '123456789',
        ]);
        Sanctum::actingAs($manager);

        $this->putJson('/api/admin/tickets/'.$ticket->id, [
            'full_name' => $ticket->full_name,
            'national_id' => $ticket->national_id,
            'request_type' => RequestType::Transfer->value,
            'completion_step' => ProcessStep::MedicalChecked->value,
            'college' => 'كلية التجارة',
            'order_number' => '123456789',
        ])
            ->assertOk()
            ->assertJsonPath('ticket.request_type', RequestType::Transfer->value)
            ->assertJsonPath('ticket.completion_step', null)
            ->assertJsonPath('ticket.request_type_label', 'تحويل (مناظر / غير مناظر)');
    }
}

<?php

namespace Tests;

use App\Models\Faculty;
use App\Models\ProcessService;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\User;
use App\Services\RolePermissionResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Role::flushCatalog();
        RequestType::flushCatalog();
        ProcessService::flushCatalog();

        if (Schema::hasTable('roles')) {
            Role::seedSystemRoles();
            app(RolePermissionResolver::class)->seedDefaults();
        }

        if (Schema::hasTable('process_services') && ProcessService::query()->doesntExist()) {
            ProcessService::seedDefaults();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function issueTicketAsStaff(array $payload = [], ?User $staff = null): TestResponse
    {
        Sanctum::actingAs($staff ?? User::factory()->teller()->create());

        return $this->postJson('/api/teller/tickets', array_merge([
            'full_name' => 'محمد أحمد علي',
            'order_number' => '123456789',
            'request_type' => 'nomination_card',
            'college' => Faculty::IndustryEnergy,
        ], $payload));
    }
}

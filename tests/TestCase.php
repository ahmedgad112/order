<?php

namespace Tests;

use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RequestType::flushCatalog();
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
        ], $payload));
    }
}

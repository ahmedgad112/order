<?php

namespace Tests\Feature;

use App\Support\PendingMigrationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PendingMigrationRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_run_pending_migrations_during_tests(): void
    {
        $ran = $this->app->make(PendingMigrationRunner::class)->run();

        $this->assertFalse($ran);
    }

    public function test_application_boots_after_queue_day_migration(): void
    {
        $this->assertDatabaseHas('migrations', [
            'migration' => '2026_09_02_114124_add_queue_day_session_columns',
        ]);
        $this->assertTrue(Schema::hasColumn('queue_tickets', 'session_started_at'));
        $this->assertTrue(Schema::hasColumn('queue_system_settings', 'day_ended_at'));
    }
}

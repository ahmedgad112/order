<?php

namespace Tests\Feature;

use App\Events\AnnouncementMadeEvent;
use App\Events\MicAudioChunkEvent;
use App\Jobs\GenerateTicketAudioJob;
use App\Models\QueueTicket;
use App\Models\User;
use App\Services\SpeechService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SpeechAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function fakeSpeech(): void
    {
        $speech = Mockery::mock(SpeechService::class)->makePartial();
        $speech->shouldReceive('synthesize')->andReturn('fake-mp3-bytes');
        $this->app->instance(SpeechService::class, $speech);
    }

    public function test_manager_can_send_announcement(): void
    {
        Event::fake();
        $this->fakeSpeech();

        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/announce', [
            'text' => 'على الجميع التوجه إلى القاعة الرئيسية',
            'voice' => 'ar-EG-SalmaNeural',
            'rate' => '0%',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم إرسال الإعلان الصوتي.')
            ->assertJsonStructure(['audio_url']);

        Event::assertDispatched(AnnouncementMadeEvent::class);
    }

    public function test_super_admin_can_send_announcement(): void
    {
        Event::fake();
        $this->fakeSpeech();

        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->postJson('/api/admin/announce', [
            'text' => 'تنبيه لجميع المتقدمين',
        ])->assertOk();

        Event::assertDispatched(AnnouncementMadeEvent::class);
    }

    public function test_teller_cannot_send_announcement(): void
    {
        $this->fakeSpeech();

        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/admin/announce', [
            'text' => 'إعلان تجريبي',
        ])->assertForbidden();
    }

    public function test_guest_cannot_send_announcement(): void
    {
        $this->postJson('/api/admin/announce', [
            'text' => 'إعلان تجريبي',
        ])->assertUnauthorized();
    }

    public function test_announcement_requires_text(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/announce', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('text');
    }

    public function test_ticket_audio_returns_url_for_serving_ticket(): void
    {
        $this->fakeSpeech();

        $teller = User::factory()->teller('شباك 3')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create();

        $this->postJson('/api/public/ticket-audio', [
            'ticket_id' => $ticket->id,
        ])
            ->assertOk()
            ->assertJsonStructure(['audio_url']);

        $audioUrl = $this->postJson('/api/public/ticket-audio', [
            'ticket_id' => $ticket->id,
        ])->json('audio_url');

        $this->assertStringContainsString('/api/public/audio/', $audioUrl);
        $this->assertStringEndsWith('.mp3', $audioUrl);
    }

    public function test_ticket_audio_reuses_cached_file_for_repeat_calls(): void
    {
        $speech = Mockery::mock(SpeechService::class)->makePartial();
        $speech->shouldReceive('synthesize')->once()->andReturn('fake-mp3-bytes');
        $this->app->instance(SpeechService::class, $speech);

        $teller = User::factory()->teller('شباك 3')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create();

        $first = $this->postJson('/api/public/ticket-audio', [
            'ticket_id' => $ticket->id,
        ])->assertOk()->json('audio_url');

        $second = $this->postJson('/api/public/ticket-audio', [
            'ticket_id' => $ticket->id,
        ])->assertOk()->json('audio_url');

        $this->assertSame($first, $second);
    }

    public function test_ticket_audio_rejects_non_serving_ticket(): void
    {
        $this->fakeSpeech();

        $ticket = QueueTicket::factory()->waiting()->create();

        $this->postJson('/api/public/ticket-audio', [
            'ticket_id' => $ticket->id,
        ])->assertNotFound();
    }

    public function test_generate_ticket_audio_job_stores_announcement_file(): void
    {
        $speech = Mockery::mock(SpeechService::class)->makePartial();
        $speech->shouldReceive('synthesize')->once()->andReturn('fake-mp3-bytes');
        $this->app->instance(SpeechService::class, $speech);

        $teller = User::factory()->teller('شباك 3')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create();

        (new GenerateTicketAudioJob($ticket->id))->handle(
            $this->app->make(SpeechService::class),
        );

        $this->assertCount(1, Storage::files('announcements'));
        $this->assertStringStartsWith('tts-', basename(Storage::files('announcements')[0]));
    }

    public function test_stream_audio_serves_stored_file(): void
    {
        Storage::put('announcements/test-file.mp3', 'fake-audio');

        $this->get('/api/public/audio/test-file.mp3')
            ->assertOk();
    }

    public function test_stream_audio_rejects_invalid_filename(): void
    {
        $this->get('/api/public/audio/secret.php')->assertNotFound();
        $this->get('/api/public/audio/missing.mp3')->assertNotFound();
    }

    public function test_manager_can_send_mic_chunk(): void
    {
        Event::fake();

        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/mic-chunk', [
            'audio' => UploadedFile::fake()->create('chunk.webm', 64),
            'session_id' => fake()->uuid(),
            'seq' => 0,
        ])->assertOk();

        Event::assertDispatched(MicAudioChunkEvent::class);
    }

    public function test_teller_cannot_send_mic_chunk(): void
    {
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->postJson('/api/admin/mic-chunk', [
            'audio' => UploadedFile::fake()->create('chunk.webm', 64),
            'session_id' => fake()->uuid(),
            'seq' => 0,
        ])->assertForbidden();
    }

    public function test_mic_chunk_requires_audio_or_final_flag(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/mic-chunk', [
            'session_id' => fake()->uuid(),
            'seq' => 0,
        ])->assertUnprocessable();
    }

    public function test_mic_chunk_accepts_final_marker_without_audio(): void
    {
        Event::fake();

        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/mic-chunk', [
            'session_id' => fake()->uuid(),
            'seq' => 5,
            'final' => true,
        ])->assertOk();

        Event::assertDispatched(MicAudioChunkEvent::class);
    }
}

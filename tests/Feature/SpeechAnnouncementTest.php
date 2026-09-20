<?php

namespace Tests\Feature;

use App\Events\AnnouncementMadeEvent;
use App\Events\MicAudioChunkEvent;
use App\Jobs\GenerateTicketAudioJob;
use App\Models\AnnouncementLog;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\RequestType;
use App\Models\StepAnnouncement;
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

        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/announce', [
            'text' => 'على الجميع التوجه إلى القاعة الرئيسية',
            'voice' => 'ar-EG-SalmaNeural',
            'rate' => '0%',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم إرسال الإعلان الصوتي.')
            ->assertJsonStructure(['audio_url']);

        $this->assertDatabaseHas('announcement_logs', [
            'user_id' => $manager->id,
            'text' => 'على الجميع التوجه إلى القاعة الرئيسية',
            'voice' => 'ar-EG-SalmaNeural',
            'rate' => '0%',
        ]);

        Event::assertDispatched(AnnouncementMadeEvent::class, function (AnnouncementMadeEvent $event): bool {
            return $event->text === 'على الجميع التوجه إلى القاعة الرئيسية'
                && $event->id !== null
                && str_contains($event->audioUrl, '/api/public/audio/');
        });

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('announcement.text', 'على الجميع التوجه إلى القاعة الرئيسية')
            ->assertJsonPath('announcement.id', AnnouncementLog::query()->latest('id')->value('id'));
    }

    public function test_text_announcement_is_sent_when_tts_fails(): void
    {
        Event::fake();

        $speech = Mockery::mock(SpeechService::class)->makePartial();
        $speech->shouldReceive('synthesizeToFile')->andThrow(new \RuntimeException('tts down'));
        $this->app->instance(SpeechService::class, $speech);

        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/announce', [
            'text' => 'على الجميع التوجه إلى القاعة الرئيسية',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم إرسال الإعلان النصي.')
            ->assertJsonPath('audio_url', null);

        $this->assertDatabaseHas('announcement_logs', [
            'text' => 'على الجميع التوجه إلى القاعة الرئيسية',
            'audio_filename' => null,
        ]);

        Event::assertDispatched(AnnouncementMadeEvent::class, function (AnnouncementMadeEvent $event): bool {
            return $event->text === 'على الجميع التوجه إلى القاعة الرئيسية'
                && $event->audioUrl === ''
                && $event->id !== null;
        });

        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('announcement.text', 'على الجميع التوجه إلى القاعة الرئيسية')
            ->assertJsonPath('announcement.audio_url', null);
    }

    public function test_public_queue_status_returns_null_announcement_when_none_exist(): void
    {
        $this->getJson('/api/public/queue-status')
            ->assertOk()
            ->assertJsonPath('announcement', null);
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

    public function test_ticket_announcement_text_uses_egyptian_arabic_speech(): void
    {
        $teller = User::factory()->teller('شباك 1')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 1,
            'full_name' => 'محمد أحمد علي',
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket);

        $this->assertSame('رَقَم أَو تِي وَاحِد، محمد أحمد علي، بُرْجَاء التَّوَجُّه إِلَى شِبَاك وَاحِد', $text);
    }

    public function test_ticket_announcement_text_omits_name_and_speaks_type_only_number(): void
    {
        $teller = User::factory()->teller('شباك 12')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 12,
            'full_name' => null,
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket);

        $this->assertSame('رَقَم أَو تِي اِتْنَاشَر، بُرْجَاء التَّوَجُّه إِلَى شِبَاك اِتْنَاشَر', $text);
    }

    public function test_ticket_announcement_text_prefixes_counter_names_without_shebak(): void
    {
        $teller = User::factory()->teller('الدفع')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 4,
            'full_name' => null,
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket);

        $this->assertSame('رَقَم أَو تِي أَرْبَعَة، بُرْجَاء التَّوَجُّه إِلَى شِبَاك الدفع', $text);
    }

    public function test_ticket_announcement_text_prefixes_numeric_counter_names(): void
    {
        $teller = User::factory()->teller('3')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 4,
            'full_name' => null,
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket);

        $this->assertSame('رَقَم أَو تِي أَرْبَعَة، بُرْجَاء التَّوَجُّه إِلَى شِبَاك تَلَاتَة', $text);
    }

    public function test_ticket_announcement_prefers_teller_counter_over_request_type_counter(): void
    {
        RequestType::findBySlug('nomination_card')->update(['counter_name' => 'الدفع']);
        $teller = User::factory()->teller('شباك 3')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 4,
            'full_name' => null,
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket);

        $this->assertSame('رَقَم أَو تِي أَرْبَعَة، بُرْجَاء التَّوَجُّه إِلَى شِبَاك تَلَاتَة', $text);
    }

    public function test_ticket_announcement_falls_back_to_request_type_counter_when_teller_has_none(): void
    {
        RequestType::findBySlug('nomination_card')->update(['counter_name' => 'الدفع']);
        $teller = User::factory()->manager()->create(['counter_name' => null]);
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 4,
            'full_name' => null,
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket);

        $this->assertSame('رَقَم أَو تِي أَرْبَعَة، بُرْجَاء التَّوَجُّه إِلَى شِبَاك الدفع', $text);
    }

    public function test_step_announcement_speaks_teller_counter(): void
    {
        $teller = User::factory()->teller('شباك 3')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 4,
            'full_name' => null,
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket, 'paid');

        $this->assertSame('رَقَم أَو تِي أَرْبَعَة، بُرْجَاء التَّوَجُّه إِلَى شِبَاك تَلَاتَة', $text);
    }

    public function test_step_announcement_does_not_replace_counter_with_destination(): void
    {
        StepAnnouncement::forStep('entered')->update(['destination' => 'دفع مصاريف اداريه']);
        $teller = User::factory()->teller('شباك 1')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 4,
            'full_name' => null,
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket, 'entered');

        $this->assertSame('رَقَم أَو تِي أَرْبَعَة، بُرْجَاء التَّوَجُّه إِلَى شِبَاك وَاحِد', $text);
    }

    public function test_step_announcement_speaks_destination_when_template_includes_it(): void
    {
        QueueSystemSetting::current()->update([
            'call_template' => 'رَقَم {order}، بُرْجَاء التَّوَجُّه إِلَى {counter}، {destination}',
        ]);
        StepAnnouncement::forStep('paid')->update(['destination' => 'شباك الدفع']);
        $teller = User::factory()->teller('شباك 3')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 4,
            'full_name' => null,
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket, 'paid');

        $this->assertSame('رَقَم أَو تِي أَرْبَعَة، بُرْجَاء التَّوَجُّه إِلَى شِبَاك تَلَاتَة، شِبَاك الدفع', $text);
    }

    public function test_ticket_announcement_uses_custom_call_template(): void
    {
        QueueSystemSetting::current()->update(['call_template' => 'العميل {order} إلى {counter}']);
        $teller = User::factory()->teller('شباك 2')->create();
        $ticket = QueueTicket::factory()->serving($teller)->create([
            'ticket_number' => 5,
            'full_name' => null,
            'request_type' => 'nomination_card',
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket);

        $this->assertSame('العميل أَو تِي خَمْسَة إلى شِبَاك اِتْنِين', $text);
    }

    public function test_ticket_announcement_text_speaks_current_student_prefix(): void
    {
        $ticket = QueueTicket::factory()->currentStudent()->create([
            'ticket_number' => 8,
            'full_name' => null,
            'user_id' => null,
        ]);

        $text = app(SpeechService::class)->ticketAnnouncementText($ticket);

        $this->assertSame('رَقَم أَو تَمَانْيَة، بُرْجَاء التَّوَجُّه إِلَى الشِّبَاك', $text);
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

    public function test_manager_can_send_recorded_announcement(): void
    {
        Event::fake();

        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->postJson('/api/admin/mic-recording', [
            'audio' => UploadedFile::fake()->create('recording.webm', 128, 'audio/webm'),
        ])
            ->assertOk()
            ->assertJsonStructure(['audio_url'])
            ->assertJsonPath('message', 'تم إرسال التسجيل إلى شاشة العرض.');

        $this->assertDatabaseHas('announcement_logs', [
            'user_id' => $manager->id,
            'text' => 'تسجيل صوتي',
            'voice' => 'recording',
        ]);

        $this->assertCount(1, Storage::files('announcements'));

        Event::assertDispatched(AnnouncementMadeEvent::class, function (AnnouncementMadeEvent $event): bool {
            return $event->text === 'تسجيل صوتي'
                && $event->id !== null
                && str_contains($event->audioUrl, '/api/public/audio/');
        });
    }

    public function test_mic_recording_requires_audio(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/mic-recording', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('audio');
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

    public function test_mic_chunk_prepends_session_init_segment_to_later_chunks(): void
    {
        Event::fake();

        Sanctum::actingAs(User::factory()->manager()->create());

        $sessionId = fake()->uuid();

        $this->postJson('/api/admin/mic-chunk', [
            'audio' => UploadedFile::fake()->createWithContent('chunk.webm', 'INIT-SEGMENT'),
            'session_id' => $sessionId,
            'seq' => 0,
        ])->assertOk();

        $audioUrl = $this->postJson('/api/admin/mic-chunk', [
            'audio' => UploadedFile::fake()->createWithContent('chunk.webm', 'CLUSTER-DATA'),
            'session_id' => $sessionId,
            'seq' => 1,
        ])->assertOk()->json('audio_url');

        $filename = basename((string) $audioUrl);
        $stored = Storage::get('announcements/'.$filename);

        $this->assertSame('INIT-SEGMENTCLUSTER-DATA', $stored);

        Event::assertDispatched(MicAudioChunkEvent::class, function (MicAudioChunkEvent $event): bool {
            $rawFilename = basename((string) $event->rawUrl);

            return Storage::get('announcements/'.$rawFilename) === 'CLUSTER-DATA';
        });
    }

    public function test_mic_chunk_different_sessions_do_not_share_headers(): void
    {
        Event::fake();

        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson('/api/admin/mic-chunk', [
            'audio' => UploadedFile::fake()->createWithContent('chunk.webm', 'INIT-SEGMENT'),
            'session_id' => fake()->uuid(),
            'seq' => 0,
        ])->assertOk();

        $audioUrl = $this->postJson('/api/admin/mic-chunk', [
            'audio' => UploadedFile::fake()->createWithContent('chunk.webm', 'OTHER-CHUNK'),
            'session_id' => fake()->uuid(),
            'seq' => 1,
        ])->assertOk()->json('audio_url');

        $stored = Storage::get('announcements/'.basename((string) $audioUrl));

        $this->assertSame('OTHER-CHUNK', $stored);
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

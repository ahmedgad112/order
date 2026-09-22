<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProcessStep;
use App\Events\AnnouncementMadeEvent;
use App\Events\MicAudioChunkEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnnounceRequest;
use App\Http\Requests\MicChunkRequest;
use App\Models\AnnouncementLog;
use App\Models\QueueTicket;
use App\Models\StepAnnouncement;
use App\Services\SpeechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpeechController extends Controller
{
    public function __construct(private readonly SpeechService $speech) {}

    public function ticketAudio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket_id' => ['required', 'integer', 'min:1'],
            'step' => ['sometimes', 'nullable', 'string', Rule::in(ProcessStep::values())],
        ]);

        $step = $validated['step'] ?? null;

        if ($step !== null && ! StepAnnouncement::isEnabledFor($step)) {
            return response()->json(['audio_url' => null]);
        }

        $ticket = QueueTicket::query()
            ->today()
            ->serving()
            ->with('teller')
            ->find($validated['ticket_id']);

        abort_unless($ticket instanceof QueueTicket, 404, 'التذكرة غير متاحة للنداء.');

        try {
            $filename = $this->speech->synthesizeToFile(
                $this->speech->ticketAnnouncementText($ticket, $step),
            );
        } catch (\Throwable $exception) {
            Log::warning('Ticket TTS failed: '.$exception->getMessage());

            return response()->json(['message' => 'تعذر توليد النداء الصوتي.'], 502);
        }

        return response()->json([
            'audio_url' => $this->speech->audioUrl($filename),
        ]);
    }

    public function streamAudio(string $filename): StreamedResponse
    {
        $path = $this->speech->audioPath($filename);

        abort_unless($path !== null, 404);

        return Storage::response($path);
    }

    public function announce(AnnounceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $filename = null;
        $audioUrl = '';

        try {
            $filename = $this->speech->synthesizeToFile(
                $data['text'],
                $data['voice'] ?? null,
                $data['rate'] ?? null,
            );
            $audioUrl = $this->speech->audioUrl($filename);
        } catch (\Throwable $exception) {
            Log::warning('Announcement TTS failed: '.$exception->getMessage());
        }

        $times = max(1, min(5, (int) ($data['times'] ?? 2)));

        $log = AnnouncementLog::create([
            'user_id' => $request->user()->id,
            'text' => $data['text'],
            'voice' => $data['voice'] ?? SpeechService::DEFAULT_VOICE,
            'rate' => $data['rate'] ?? SpeechService::DEFAULT_RATE,
            'audio_filename' => $filename,
        ]);

        $this->broadcastSafely(new AnnouncementMadeEvent($audioUrl, $data['text'], $log->id, $times));

        return response()->json([
            'message' => $audioUrl !== ''
                ? 'تم إرسال الإعلان الصوتي.'
                : 'تم إرسال الإعلان النصي.',
            'audio_url' => $audioUrl !== '' ? $audioUrl : null,
            'times' => $times,
        ]);
    }

    public function micChunk(MicChunkRequest $request): JsonResponse
    {
        $data = $request->validated();

        $audioUrl = null;
        $rawUrl = null;
        $file = $request->file('audio');

        if ($file instanceof UploadedFile) {
            [$rawName, $playableName] = $this->speech->storeMicChunk($file, $data['session_id']);
            $audioUrl = $this->speech->audioUrl($playableName);
            $rawUrl = $this->speech->audioUrl($rawName);
        }

        $this->broadcastSafely(new MicAudioChunkEvent(
            $audioUrl,
            $data['session_id'],
            (int) ($data['seq'] ?? 0),
            $request->boolean('final'),
            $rawUrl,
        ));

        return response()->json(['audio_url' => $audioUrl]);
    }

    public function micRecording(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:20480'],
        ], [
            'audio.required' => 'التسجيل الصوتي مطلوب.',
            'audio.max' => 'حجم التسجيل كبير جداً.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('audio');

        $filename = $this->speech->storeUploadedAudio($file);
        $audioUrl = $this->speech->audioUrl($filename);

        $log = AnnouncementLog::create([
            'user_id' => $request->user()->id,
            'text' => 'تسجيل صوتي',
            'voice' => 'recording',
            'rate' => SpeechService::DEFAULT_RATE,
            'audio_filename' => $filename,
        ]);

        $this->broadcastSafely(new AnnouncementMadeEvent($audioUrl, 'تسجيل صوتي', $log->id));

        return response()->json([
            'message' => 'تم إرسال التسجيل إلى شاشة العرض.',
            'audio_url' => $audioUrl,
        ]);
    }

    private function broadcastSafely(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $exception) {
            Log::warning('Speech broadcast failed: '.$exception->getMessage());
        }
    }
}

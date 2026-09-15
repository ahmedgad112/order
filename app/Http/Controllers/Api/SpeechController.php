<?php

namespace App\Http\Controllers\Api;

use App\Events\AnnouncementMadeEvent;
use App\Events\MicAudioChunkEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnnounceRequest;
use App\Http\Requests\MicChunkRequest;
use App\Models\QueueTicket;
use App\Services\SpeechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpeechController extends Controller
{
    public function __construct(private readonly SpeechService $speech) {}

    public function ticketAudio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ticket_id' => ['required', 'integer', 'min:1'],
        ]);

        $ticket = QueueTicket::query()
            ->today()
            ->serving()
            ->with('teller')
            ->find($validated['ticket_id']);

        abort_unless($ticket instanceof QueueTicket, 404, 'التذكرة غير متاحة للنداء.');

        try {
            $filename = $this->speech->synthesizeToFile(
                $this->speech->ticketAnnouncementText($ticket),
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

        try {
            $filename = $this->speech->synthesizeToFile(
                $data['text'],
                $data['voice'] ?? null,
                $data['rate'] ?? null,
            );
        } catch (\Throwable $exception) {
            Log::warning('Announcement TTS failed: '.$exception->getMessage());

            return response()->json([
                'message' => 'تعذر توليد الرسالة الصوتية. تأكد من اتصال الخادم بالإنترنت.',
            ], 502);
        }

        $audioUrl = $this->speech->audioUrl($filename);

        $this->broadcastSafely(new AnnouncementMadeEvent($audioUrl, $data['text']));

        return response()->json([
            'message' => 'تم إرسال الإعلان الصوتي.',
            'audio_url' => $audioUrl,
        ]);
    }

    public function micChunk(MicChunkRequest $request): JsonResponse
    {
        $data = $request->validated();

        $audioUrl = null;
        $file = $request->file('audio');

        if ($file instanceof UploadedFile) {
            $audioUrl = $this->speech->audioUrl(
                $this->speech->storeUploadedAudio($file),
            );
        }

        $this->broadcastSafely(new MicAudioChunkEvent(
            $audioUrl,
            $data['session_id'],
            (int) ($data['seq'] ?? 0),
            $request->boolean('final'),
        ));

        return response()->json(['audio_url' => $audioUrl]);
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

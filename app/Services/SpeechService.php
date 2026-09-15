<?php

namespace App\Services;

use Afaya\EdgeTTS\Service\EdgeTTS;
use App\Models\QueueTicket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SpeechService
{
    public const string AUDIO_DIRECTORY = 'announcements';

    public const int FILE_TTL_SECONDS = 1800;

    public const int TTS_TTL_SECONDS = 604800;

    public const string DEFAULT_VOICE = 'ar-EG-SalmaNeural';

    public const string DEFAULT_RATE = '0%';

    /**
     * @return array<string, string>
     */
    public static function voices(): array
    {
        return [
            'ar-EG-SalmaNeural' => 'عربي مصري — سلمى (أنثى)',
            'ar-EG-ShakirNeural' => 'عربي مصري — شاكر (ذكر)',
            'ar-SA-ZariyahNeural' => 'عربي فصحى — زرية (أنثى)',
            'ar-SA-HamedNeural' => 'عربي فصحى — حامد (ذكر)',
        ];
    }

    /**
     * @return list<string>
     */
    public static function rates(): array
    {
        return ['-25%', '0%', '+25%', '+50%'];
    }

    public function synthesizeToFile(string $text, ?string $voice = null, ?string $rate = null): string
    {
        $voice ??= self::DEFAULT_VOICE;
        $rate ??= self::DEFAULT_RATE;

        $filename = 'tts-'.sha1($text.'|'.$voice.'|'.$rate).'.mp3';
        $path = self::AUDIO_DIRECTORY.'/'.$filename;

        if (Storage::exists($path)) {
            return $filename;
        }

        $this->pruneExpiredFiles();

        Storage::put($path, $this->synthesize($text, $voice, $rate));

        return $filename;
    }

    public function synthesize(string $text, string $voice, string $rate): string
    {
        $tts = new EdgeTTS;
        $tts->synthesize($text, $voice, [
            'rate' => $rate,
            'volume' => '+0%',
            'pitch' => '+0Hz',
        ]);

        return $tts->toRaw();
    }

    public function ticketAnnouncementText(QueueTicket $ticket): string
    {
        $counter = $ticket->teller?->counter_name ?: 'الشباك';

        $parts = ['تذكرة رقم '.$ticket->ticketCode()];

        if (filled($ticket->full_name)) {
            $parts[] = $ticket->full_name;
        }

        $parts[] = 'توجه إلى '.$counter;

        return implode('، ', $parts);
    }

    public function storeAudio(string $binary, string $extension): string
    {
        $this->pruneExpiredFiles();

        $filename = Str::uuid()->toString().'.'.$extension;

        Storage::put(self::AUDIO_DIRECTORY.'/'.$filename, $binary);

        return $filename;
    }

    public function storeUploadedAudio(UploadedFile $file): string
    {
        return $this->storeAudio(
            (string) $file->get(),
            $this->extensionForMime((string) $file->getMimeType()),
        );
    }

    /**
     * MediaRecorder chunks after the first lack the container init segment.
     * Returns [rawName, playableName]: the raw fragment for MediaSource
     * streaming, and a standalone-playable file (init segment prepended)
     * for the per-chunk fallback path.
     *
     * @return array{0: string, 1: string}
     */
    public function storeMicChunk(UploadedFile $file, string $sessionId): array
    {
        $bytes = (string) $file->get();
        $extension = $this->extensionForMime((string) $file->getMimeType());
        $rawName = $this->storeAudio($bytes, $extension);
        $headerPath = 'mic-headers/'.$sessionId;

        if (! Storage::exists($headerPath)) {
            Storage::put($headerPath, $bytes);
            $this->pruneMicHeaders();

            return [$rawName, $rawName];
        }

        return [$rawName, $this->storeAudio(Storage::get($headerPath).$bytes, $extension)];
    }

    private function pruneMicHeaders(): void
    {
        foreach (Storage::files('mic-headers') as $path) {
            if (Storage::lastModified($path) < time() - 3600) {
                Storage::delete($path);
            }
        }
    }

    public function audioPath(string $filename): ?string
    {
        if (preg_match('/^[A-Za-z0-9\-]+\.(mp3|webm|m4a|ogg|mp4|wav)$/', $filename) !== 1) {
            return null;
        }

        $path = self::AUDIO_DIRECTORY.'/'.$filename;

        return Storage::exists($path) ? $path : null;
    }

    public function audioUrl(string $filename): string
    {
        return url('/api/public/audio/'.$filename);
    }

    private function extensionForMime(string $mime): string
    {
        return match (true) {
            str_contains($mime, 'webm') => 'webm',
            str_contains($mime, 'ogg') => 'ogg',
            str_contains($mime, 'wav') => 'wav',
            default => 'm4a',
        };
    }

    private function pruneExpiredFiles(): void
    {
        $now = time();

        foreach (Storage::files(self::AUDIO_DIRECTORY) as $path) {
            $ttl = str_starts_with(basename($path), 'tts-')
                ? self::TTS_TTL_SECONDS
                : self::FILE_TTL_SECONDS;

            if (Storage::lastModified($path) < $now - $ttl) {
                Storage::delete($path);
            }
        }
    }
}

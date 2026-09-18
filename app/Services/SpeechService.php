<?php

namespace App\Services;

use Afaya\EdgeTTS\Service\EdgeTTS;
use App\Enums\ProcessStep;
use App\Models\AnnouncementLog;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\RequestType;
use App\Models\StepAnnouncement;
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

    public function ticketAnnouncementText(QueueTicket $ticket, ProcessStep|string|null $step = null): string
    {
        $text = strtr(QueueSystemSetting::current()->callTemplate(), [
            '{order}' => $this->spokenTicketCode($ticket),
            '{name}' => trim((string) $ticket->full_name),
            '{counter}' => $this->destinationText($ticket, $step),
            '{type}' => RequestType::findBySlug($ticket->request_type)?->label ?? '',
        ]);

        $text = $this->spokenMixedText($text);
        $text = (string) preg_replace('/(?:\s*،\s*){2,}/u', '، ', $text);
        $text = (string) preg_replace('/^\s*،\s*|،\s*$/u', '', $text);

        return trim($text);
    }

    private function destinationText(QueueTicket $ticket, ProcessStep|string|null $step): string
    {
        if ($step !== null) {
            $custom = StepAnnouncement::destinationFor($step);

            if ($custom !== null) {
                return str_replace('شباك', 'شِبَاك', $custom);
            }
        }

        $counter = trim((string) ($ticket->resolvedCounterName() ?? ''));

        if ($counter === '') {
            return 'الشِّبَاك';
        }

        if (! str_contains($counter, 'شباك')) {
            $counter = 'شباك '.$counter;
        }

        return str_replace('شباك', 'شِبَاك', $counter);
    }

    private function spokenTicketCode(QueueTicket $ticket): string
    {
        return trim($this->spokenLetters($ticket->ticketPrefix()).' '.$this->spokenNumber((int) $ticket->ticket_number));
    }

    private function spokenLetters(string $letters): string
    {
        $names = [
            'A' => 'إِيه',
            'B' => 'بِي',
            'C' => 'سِي',
            'D' => 'دِي',
            'E' => 'إِي',
            'F' => 'إِف',
            'G' => 'جِي',
            'H' => 'إِتْش',
            'I' => 'آي',
            'J' => 'جِيه',
            'K' => 'كِي',
            'L' => 'إِل',
            'M' => 'إِم',
            'N' => 'إِن',
            'O' => 'أَو',
            'P' => 'بِي',
            'Q' => 'كِيُو',
            'R' => 'آر',
            'S' => 'إِس',
            'T' => 'تِي',
            'U' => 'يُو',
            'V' => 'فِي',
            'W' => 'دَبْلِيُو',
            'X' => 'إِكْس',
            'Y' => 'وَاي',
            'Z' => 'زِي',
        ];

        $spoken = [];

        foreach (str_split(strtoupper($letters)) as $letter) {
            $spoken[] = $names[$letter] ?? $letter;
        }

        return implode(' ', $spoken);
    }

    private function spokenMixedText(string $text): string
    {
        return (string) preg_replace_callback(
            '/[0-9٠-٩]+/u',
            fn (array $matches): string => $this->spokenNumber($this->integerFromDigits($matches[0])),
            $text,
        );
    }

    private function integerFromDigits(string $digits): int
    {
        return (int) strtr($digits, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    private function spokenNumber(int $number): string
    {
        if ($number === 0) {
            return 'صِفْر';
        }

        $thousands = intdiv($number, 1000);
        $remainder = $number % 1000;
        $parts = [];

        if ($thousands > 0) {
            $parts[] = match ($thousands) {
                1 => 'أَلْف',
                2 => 'أَلْفَيْن',
                3 => 'تَلَات آلَاف',
                4 => 'أَرْبَع آلَاف',
                5 => 'خَمْس آلَاف',
                6 => 'سِتّ آلَاف',
                7 => 'سَبْع آلَاف',
                8 => 'تِمْن آلَاف',
                9 => 'تِسْع آلَاف',
                default => $this->spokenHundreds($thousands).' أَلْف',
            };
        }

        if ($remainder > 0) {
            $parts[] = $this->spokenHundreds($remainder);
        }

        return implode(' وَ', $parts);
    }

    private function spokenHundreds(int $number): string
    {
        if ($number < 100) {
            return $this->spokenTens($number);
        }

        $hundred = intdiv($number, 100);
        $rest = $number % 100;

        $hundredWord = match ($hundred) {
            1 => 'مِيَّة',
            2 => 'مِيتِين',
            3 => 'تِلْت مِيَّة',
            4 => 'أَرْبَع مِيَّة',
            5 => 'خَمْس مِيَّة',
            6 => 'سِتّ مِيَّة',
            7 => 'سَبْع مِيَّة',
            8 => 'تِمْن مِيَّة',
            9 => 'تِسْع مِيَّة',
            default => (string) $number,
        };

        if ($rest === 0) {
            return $hundredWord;
        }

        return $hundredWord.' وَ'.$this->spokenTens($rest);
    }

    private function spokenTens(int $number): string
    {
        $ones = [
            1 => 'وَاحِد',
            2 => 'اِتْنِين',
            3 => 'تَلَاتَة',
            4 => 'أَرْبَعَة',
            5 => 'خَمْسَة',
            6 => 'سِتَّة',
            7 => 'سَبْعَة',
            8 => 'تَمَانْيَة',
            9 => 'تِسْعَة',
        ];

        if ($number < 10) {
            return $ones[$number] ?? (string) $number;
        }

        $teens = [
            10 => 'عَشَرَة',
            11 => 'حِدَاشَر',
            12 => 'اِتْنَاشَر',
            13 => 'تَلَتَّاشَر',
            14 => 'أَرْبَعْتَاشَر',
            15 => 'خَمِسْتَاشَر',
            16 => 'سِتَّاشَر',
            17 => 'سَبَعْتَاشَر',
            18 => 'تَمَنْتَاشَر',
            19 => 'تِسَعْتَاشَر',
        ];

        if ($number < 20) {
            return $teens[$number];
        }

        $tens = [
            20 => 'عِشْرِين',
            30 => 'تَلَاتِين',
            40 => 'أَرْبَعِين',
            50 => 'خَمْسِين',
            60 => 'سِتِّين',
            70 => 'سَبْعِين',
            80 => 'تَمَانِين',
            90 => 'تِسْعِين',
        ];

        $ten = intdiv($number, 10) * 10;
        $one = $number % 10;

        if ($one === 0) {
            return $tens[$ten];
        }

        return $ones[$one].' وَ'.$tens[$ten];
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

    /**
     * @return array{id: int, text: string, audio_url: string|null}|null
     */
    public function latestPublicAnnouncement(): ?array
    {
        $log = AnnouncementLog::query()->latest('id')->first();

        if (! $log instanceof AnnouncementLog) {
            return null;
        }

        $audioUrl = null;

        if (is_string($log->audio_filename) && $log->audio_filename !== '' && $this->audioPath($log->audio_filename) !== null) {
            $audioUrl = $this->audioUrl($log->audio_filename);
        }

        return [
            'id' => $log->id,
            'text' => $log->text,
            'audio_url' => $audioUrl,
        ];
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

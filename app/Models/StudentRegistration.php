<?php

namespace App\Models;

use App\Enums\StudentRegistrationGroup;
use App\Enums\StudentRegistrationStatus;
use Database\Factories\StudentRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'full_name',
    'academic_number',
    'national_id',
    'department',
    'section_number',
    'group_number',
    'student_group',
    'service_date',
    'turn_number',
    'status',
    'completed_at',
    'completed_by',
    'people_ahead',
])]
class StudentRegistration extends Model
{
    /** @use HasFactory<StudentRegistrationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section_number' => 'integer',
            'group_number' => 'integer',
            'turn_number' => 'integer',
            'people_ahead' => 'integer',
            'student_group' => StudentRegistrationGroup::class,
            'status' => StudentRegistrationStatus::class,
            'service_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public static function currentServiceDate(): string
    {
        return now('Africa/Cairo')->toDateString();
    }

    /**
     * @param  array{full_name: string, academic_number: string, national_id: string, department: string, section_number: int, group_number: int, student_group: string}  $data
     */
    public static function issue(array $data): self
    {
        $serviceDate = self::currentServiceDate();

        return Cache::lock('student-registration-turn:'.$serviceDate, 15)
            ->block(10, function () use ($data, $serviceDate): self {
                for ($attempt = 1; $attempt <= 5; $attempt++) {
                    try {
                        return DB::transaction(function () use ($data, $serviceDate): self {
                            $turnNumber = (int) static::query()
                                ->whereDate('service_date', $serviceDate)
                                ->orderByDesc('turn_number')
                                ->lockForUpdate()
                                ->value('turn_number') + 1;

                            return static::query()->create([
                                ...$data,
                                'service_date' => $serviceDate,
                                'turn_number' => $turnNumber,
                                'status' => StudentRegistrationStatus::Waiting,
                            ]);
                        });
                    } catch (UniqueConstraintViolationException $exception) {
                        if ($attempt === 5 || ! self::isTurnNumberConflict($exception)) {
                            throw $exception;
                        }
                    }
                }

                throw ValidationException::withMessages([
                    'turn_number' => 'تعذر إصدار الدور. حاول مرة أخرى.',
                ]);
            });
    }

    private static function isTurnNumberConflict(UniqueConstraintViolationException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'turn_number')
            || str_contains($message, 'service_date_turn_number');
    }

    public static function resetDay(): int
    {
        return static::query()->delete();
    }

    public function countPeopleAhead(): int
    {
        return (int) static::query()
            ->whereDate('service_date', $this->service_date)
            ->where('status', StudentRegistrationStatus::Waiting)
            ->where('turn_number', '<', $this->turn_number)
            ->count();
    }

    public function markCompletedBy(?int $userId): self
    {
        if ($this->status === StudentRegistrationStatus::Completed) {
            throw ValidationException::withMessages([
                'registration' => 'تم إنهاء هذا الدور من قبل.',
            ]);
        }

        if ($this->status === StudentRegistrationStatus::Absent) {
            throw ValidationException::withMessages([
                'registration' => 'لا يمكن إنهاء دور مسجّل كـ "مش موجود".',
            ]);
        }

        $this->update([
            'status' => StudentRegistrationStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $userId,
            'people_ahead' => $this->countPeopleAhead(),
        ]);

        return $this->refresh();
    }

    public function markAbsentBy(?int $userId): self
    {
        if ($this->status !== StudentRegistrationStatus::Waiting) {
            throw ValidationException::withMessages([
                'registration' => 'يمكن تسجيل "مش موجود" للطلاب في الانتظار فقط.',
            ]);
        }

        $this->update([
            'status' => StudentRegistrationStatus::Absent,
            'completed_at' => now(),
            'completed_by' => $userId,
            'people_ahead' => $this->countPeopleAhead(),
        ]);

        return $this->refresh();
    }

    public function groupCode(): string
    {
        $letter = $this->student_group?->value ?? '';

        if ($this->group_number === null) {
            return $letter;
        }

        return $this->group_number.$letter;
    }

    public function scopeMatchingRoster(Builder $query, string $department, ?int $sectionNumber, string $search): Builder
    {
        return $query
            ->when($department !== '', fn (Builder $query): Builder => $query->where('department', $department))
            ->when($sectionNumber !== null, fn (Builder $query): Builder => $query->where('section_number', $sectionNumber))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($search, $term): void {
                    $query->whereRaw('full_name LIKE ? ESCAPE ?', [$term, '\\'])
                        ->orWhereRaw('academic_number LIKE ? ESCAPE ?', [$term, '\\'])
                        ->orWhereRaw('national_id LIKE ? ESCAPE ?', [$term, '\\']);

                    if (preg_match('/^\d+$/', $search) === 1) {
                        $query->orWhere('turn_number', (int) $search);
                    }
                });
            })
            ->orderBy('turn_number')
            ->orderBy('id');
    }
}

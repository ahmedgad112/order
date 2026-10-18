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
use Illuminate\Support\Facades\DB;

#[Fillable([
    'full_name',
    'academic_number',
    'national_id',
    'department',
    'section_number',
    'student_group',
    'service_date',
    'turn_number',
    'status',
    'completed_at',
    'completed_by',
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
            'turn_number' => 'integer',
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
     * @param  array{full_name: string, academic_number: string, national_id: string, department: string, section_number: int, student_group: string}  $data
     */
    public static function issue(array $data): self
    {
        return DB::transaction(function () use ($data): self {
            $serviceDate = self::currentServiceDate();
            $turnNumber = (int) static::query()
                ->whereDate('service_date', $serviceDate)
                ->lockForUpdate()
                ->max('turn_number') + 1;

            return static::query()->create([
                ...$data,
                'service_date' => $serviceDate,
                'turn_number' => $turnNumber,
                'status' => StudentRegistrationStatus::Waiting,
            ]);
        });
    }

    public function scopeMatchingRoster(Builder $query, string $department, ?int $sectionNumber, string $search): Builder
    {
        return $query
            ->when($department !== '', fn (Builder $query): Builder => $query->where('department', $department))
            ->when($sectionNumber !== null, fn (Builder $query): Builder => $query->where('section_number', $sectionNumber))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw('full_name LIKE ? ESCAPE ?', [$term, '\\'])
                        ->orWhereRaw('academic_number LIKE ? ESCAPE ?', [$term, '\\'])
                        ->orWhereRaw('national_id LIKE ? ESCAPE ?', [$term, '\\']);
                });
            })
            ->orderBy('department')
            ->orderBy('section_number')
            ->orderBy('turn_number')
            ->orderBy('id');
    }
}

<?php

namespace App\Services;

use App\Enums\DocumentKind;
use App\Enums\ProcessStep;
use App\Enums\StudentKind;
use App\Events\QueueDayResetEvent;
use App\Events\QueueSystemUpdatedEvent;
use App\Models\College;
use App\Models\Faculty;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\RequestType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class QueueSystemService
{
    public function getStatus(): array
    {
        $settings = QueueSystemSetting::current();

        return $this->formatStatus($settings);
    }

    public function closeSystem(User $admin, ?string $message = null): array
    {
        abort_unless($admin->canControlSystem(), 403, 'ليس لديك صلاحية للوصول.');

        $settings = QueueSystemSetting::current();

        $settings->update([
            'is_open' => false,
            'closed_message' => $message ?? 'النظام مغلق مؤقتاً. يرجى المحاولة لاحقاً.',
            'closed_at' => now(),
            'closed_by' => $admin->id,
        ]);

        $status = $this->formatStatus($settings->fresh());

        $this->broadcastSafely(new QueueSystemUpdatedEvent($status));

        return $status;
    }

    public function openSystem(User $admin): array
    {
        abort_unless($admin->canControlSystem(), 403, 'ليس لديك صلاحية للوصول.');

        $settings = QueueSystemSetting::current();

        $settings->update([
            'is_open' => true,
            'closed_message' => null,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        $status = $this->formatStatus($settings->fresh());

        $this->broadcastSafely(new QueueSystemUpdatedEvent($status));

        return $status;
    }

    public function endDay(User $admin): array
    {
        abort_unless($admin->canControlSystem(), 403, 'ليس لديك صلاحية للوصول.');

        $settings = QueueSystemSetting::current();

        if (! $settings->isDayOpen()) {
            throw ValidationException::withMessages([
                'system' => 'تم إنهاء اليوم بالفعل.',
            ]);
        }

        $archivedTickets = QueueTicket::query()->today()->count();

        $settings->update([
            'day_ended_at' => now(),
            'day_ended_by' => $admin->id,
        ]);

        $status = $this->formatStatus($settings->fresh());

        $this->broadcastSafely(new QueueSystemUpdatedEvent($status));

        return [
            'archived_tickets' => $archivedTickets,
            'ended_at' => $status['day_ended_at'],
            'system' => $status,
        ];
    }

    public function openDay(User $admin): array
    {
        abort_unless($admin->canControlSystem(), 403, 'ليس لديك صلاحية للوصول.');

        $settings = QueueSystemSetting::current();

        if ($settings->isDayOpen()) {
            throw ValidationException::withMessages([
                'system' => 'اليوم مفتوح بالفعل. أنهِ اليوم الحالي أولاً قبل فتح يوم جديد.',
            ]);
        }

        QueueTicket::forgetPublicStatusCache();

        $openedAt = now()->startOfSecond();

        $settings->update([
            'current_session_started_at' => $openedAt,
            'day_ended_at' => null,
            'day_ended_by' => null,
            'last_reset_at' => $openedAt,
            'last_reset_by' => $admin->id,
        ]);

        QueueTicket::forgetPublicStatusCache();

        $status = $this->formatStatus($settings->fresh());
        $openedAtIso = $openedAt->toIso8601String();

        $this->broadcastSafely(new QueueDayResetEvent(0, $openedAtIso));
        $this->broadcastSafely(new QueueSystemUpdatedEvent($status));

        return [
            'opened_at' => $openedAtIso,
            'system' => $status,
        ];
    }

    /**
     * @param  list<string>  $enabledRequestTypes
     * @return array<string, mixed>
     */
    public function updateEnabledRequestTypes(User $admin, array $enabledRequestTypes): array
    {
        abort_unless($admin->canControlSystem(), 403, 'ليس لديك صلاحية للوصول.');

        $enabledRequestTypes = array_values(array_unique($enabledRequestTypes));

        RequestType::query()->get()->each(
            fn (RequestType $type) => $type->update([
                'enabled' => in_array($type->slug, $enabledRequestTypes, true),
            ]),
        );

        $status = $this->formatStatus(QueueSystemSetting::current());

        $this->broadcastSafely(new QueueSystemUpdatedEvent($status));

        return $status;
    }

    /**
     * @param  list<string>  $enabledStudentKinds
     * @return array<string, mixed>
     */
    public function updateEnabledStudentKinds(User $admin, array $enabledStudentKinds): array
    {
        abort_unless($admin->canControlSystem(), 403, 'ليس لديك صلاحية للوصول.');

        $settings = QueueSystemSetting::current();

        $settings->update([
            'enabled_student_kinds' => array_values(array_unique($enabledStudentKinds)),
        ]);

        $status = $this->formatStatus($settings->fresh());

        $this->broadcastSafely(new QueueSystemUpdatedEvent($status));

        return $status;
    }

    /**
     * @param  list<int>  $tellerIds
     * @return list<array{value: string, label: string, teller_ids: list<int>}>
     */
    public function assignTellersToLane(string $lane, array $tellerIds): array
    {
        if (! in_array($lane, RequestType::laneValues(), true)) {
            throw ValidationException::withMessages([
                'lane' => 'نوع الطلب غير صحيح.',
            ]);
        }

        $selected = array_values(array_unique(array_map('intval', $tellerIds)));

        $tellers = User::query()
            ->whereIn('role', Role::queueSlugs())
            ->orderBy('id')
            ->get();

        foreach ($tellers as $teller) {
            $current = $teller->queueLaneValues();

            if (in_array($teller->id, $selected, true)) {
                $current[] = $lane;
            } else {
                $current = array_filter(
                    $current,
                    fn (string $value): bool => $value !== $lane,
                );
            }

            $teller->update([
                'queue_lanes' => array_values(array_unique($current)),
            ]);
        }

        return $this->queueLaneAssignments();
    }

    /**
     * @return list<array{value: string, label: string, teller_ids: list<int>}>
     */
    public function queueLaneAssignments(): array
    {
        $tellers = User::query()
            ->whereIn('role', Role::queueSlugs())
            ->orderBy('name')
            ->get();

        return array_map(
            function (array $lane) use ($tellers): array {
                $assigned = $tellers
                    ->filter(fn (User $teller): bool => $teller->servesQueueLane($lane['value']))
                    ->values();

                return [
                    'value' => $lane['value'],
                    'label' => $lane['label'],
                    'teller_ids' => $assigned->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                ];
            },
            RequestType::lanePayload(),
        );
    }

    public function assertSystemOpen(): void
    {
        $settings = QueueSystemSetting::current();

        if (! $settings->is_open) {
            throw ValidationException::withMessages([
                'system' => $settings->closed_message ?? 'النظام مغلق حالياً.',
            ]);
        }
    }

    public function assertAcceptingTickets(): void
    {
        $this->assertSystemOpen();

        $settings = QueueSystemSetting::current();

        if (! $settings->isDayOpen()) {
            throw ValidationException::withMessages([
                'system' => 'انتهى استقبال الطلبات اليوم.',
            ]);
        }
    }

    public function isOpen(): bool
    {
        return QueueSystemSetting::current()->is_open;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastStatus(): array
    {
        $status = $this->getStatus();

        $this->broadcastSafely(new QueueSystemUpdatedEvent($status));

        return $status;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStatus(QueueSystemSetting $settings): array
    {
        $dayOpen = $settings->isDayOpen();

        return [
            'is_open' => $settings->is_open,
            'is_day_open' => $dayOpen,
            'accepting_tickets' => $settings->is_open && $dayOpen,
            'closed_message' => $settings->closed_message,
            'closed_at' => $settings->closed_at?->toIso8601String(),
            'day_ended_at' => $settings->day_ended_at?->toIso8601String(),
            'day_ended_message' => $dayOpen ? null : 'انتهى استقبال الطلبات اليوم. يمكن متابعة الطلبات الحالية.',
            'last_reset_at' => $settings->last_reset_at?->toIso8601String(),
            'current_session_started_at' => $settings->currentSessionStartedAt()->toIso8601String(),
            'call_template' => $settings->callTemplate(),
            'request_types' => RequestType::payload(),
            'student_kinds' => StudentKind::payload($settings->enabledStudentKindValues()),
            'completion_service_options' => ProcessStep::admissionCompletionPayload(),
            'colleges' => College::payload(),
            'faculties' => Faculty::payload(),
            'document_kinds' => DocumentKind::payload(),
        ];
    }

    private function broadcastSafely(object $event): void
    {
        try {
            event($event);
        } catch (\Throwable $exception) {
            Log::warning('Queue system broadcast failed: '.$exception->getMessage());
        }
    }
}

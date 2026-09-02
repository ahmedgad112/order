<?php

namespace App\Services;

use App\Events\QueueDayResetEvent;
use App\Events\QueueSystemUpdatedEvent;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
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

<?php

namespace App\Services;

use App\Events\QueueDayResetEvent;
use App\Events\QueueSystemUpdatedEvent;
use App\Models\QueueSystemSetting;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

    public function resetDay(User $admin): array
    {
        abort_unless($admin->canControlSystem(), 403, 'ليس لديك صلاحية للوصول.');

        $deletedTickets = DB::transaction(function () use ($admin): int {
            $count = QueueTicket::query()->today()->count();

            QueueTicket::query()->today()->delete();
            QueueTicket::forgetPublicStatusCache();

            $settings = QueueSystemSetting::current();
            $settings->update([
                'last_reset_at' => now(),
                'last_reset_by' => $admin->id,
            ]);

            return $count;
        });

        $resetAt = now()->toIso8601String();

        $this->broadcastSafely(new QueueDayResetEvent($deletedTickets, $resetAt));
        $this->broadcastSafely(new QueueSystemUpdatedEvent($this->getStatus()));

        return [
            'deleted_tickets' => $deletedTickets,
            'reset_at' => $resetAt,
            'system' => $this->getStatus(),
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

    public function isOpen(): bool
    {
        return QueueSystemSetting::current()->is_open;
    }

    private function formatStatus(QueueSystemSetting $settings): array
    {
        return [
            'is_open' => $settings->is_open,
            'closed_message' => $settings->closed_message,
            'closed_at' => $settings->closed_at?->toIso8601String(),
            'last_reset_at' => $settings->last_reset_at?->toIso8601String(),
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

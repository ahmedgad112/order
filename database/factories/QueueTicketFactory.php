<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueTicket>
 */
class QueueTicketFactory extends Factory
{
    protected $model = QueueTicket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_number' => fake()->unique()->numberBetween(1, 9999),
            'public_token' => fake()->uuid(),
            'full_name' => fake()->name(),
            'national_id' => fake()->numerify('##############'),
            'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
            'status' => TicketStatus::Waiting,
            'user_id' => null,
            'called_at' => null,
            'entered_at' => null,
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ];
    }

    public function waiting(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Waiting,
            'user_id' => null,
            'called_at' => null,
            'entered_at' => null,
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }

    public function serving(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Serving,
            'user_id' => $teller?->id,
            'called_at' => now(),
            'entered_at' => now(),
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }

    public function completed(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Completed,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(5),
            'entered_at' => now()->subMinutes(5),
            'medical_checked_at' => now()->subMinutes(4),
            'face_printed_at' => now()->subMinutes(3),
            'completed_at' => now(),
            'file_delivered_at' => now()->subMinutes(1),
        ]);
    }
}

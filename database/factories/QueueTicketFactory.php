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
            'full_name' => fake()->name(),
            'national_id' => fake()->numerify('##############'),
            'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
            'status' => TicketStatus::Waiting,
            'user_id' => null,
            'called_at' => null,
            'completed_at' => null,
        ];
    }

    public function waiting(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Waiting,
            'user_id' => null,
            'called_at' => null,
            'completed_at' => null,
        ]);
    }

    public function serving(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Serving,
            'user_id' => $teller?->id,
            'called_at' => now(),
            'completed_at' => null,
        ]);
    }

    public function completed(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Completed,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }
}

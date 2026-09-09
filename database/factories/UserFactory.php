<?php

namespace Database\Factories;

use App\Enums\QueueLane;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Teller,
            'counter_name' => 'شباك '.fake()->numberBetween(1, 9),
            'queue_lanes' => QueueLane::values(),
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->superAdmin();
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::SuperAdmin,
            'counter_name' => null,
            'queue_lanes' => null,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Manager,
            'counter_name' => null,
            'queue_lanes' => null,
        ]);
    }

    public function teller(?string $counterName = null): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Teller,
            'counter_name' => $counterName ?? 'شباك 1',
            'queue_lanes' => QueueLane::values(),
        ]);
    }

    /**
     * @param  list<string>|list<QueueLane>  $lanes
     */
    public function forQueueLanes(array $lanes): static
    {
        return $this->state(fn () => [
            'queue_lanes' => array_map(
                fn (QueueLane|string $lane): string => $lane instanceof QueueLane ? $lane->value : $lane,
                $lanes,
            ),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    protected $model = Faculty::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $digits = fake()->numberBetween(6, 9);

        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->unique()->words(2, true),
            'seat_number_min_digits' => $digits,
            'seat_number_max_digits' => $digits,
            'sort_order' => fake()->numberBetween(10, 99),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}

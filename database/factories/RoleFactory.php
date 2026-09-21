<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'slug' => Str::slug($name, '_').'_'.Str::lower(Str::random(4)),
            'name' => $name,
            'rank' => 20,
            'is_system' => false,
            'is_super_admin' => false,
            'serves_queue' => false,
        ];
    }

    public function servesQueue(): static
    {
        return $this->state(fn () => [
            'serves_queue' => true,
            'rank' => 15,
        ]);
    }
}

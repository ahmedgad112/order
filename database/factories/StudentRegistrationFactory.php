<?php

namespace Database\Factories;

use App\Enums\StudentRegistrationGroup;
use App\Enums\StudentRegistrationStatus;
use App\Models\StudentRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentRegistration>
 */
class StudentRegistrationFactory extends Factory
{
    protected $model = StudentRegistration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'academic_number' => fake()->unique()->numerify('##########'),
            'national_id' => fake()->unique()->numerify('##############'),
            'department' => 'تكنولوجيا المعلومات',
            'section_number' => fake()->numberBetween(1, 20),
            'group_number' => fake()->numberBetween(1, 20),
            'student_group' => StudentRegistrationGroup::GroupA,
            'service_date' => StudentRegistration::currentServiceDate(),
            'turn_number' => fake()->unique()->numberBetween(1, 900000),
            'status' => StudentRegistrationStatus::Waiting,
        ];
    }
}

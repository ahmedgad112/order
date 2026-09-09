<?php

namespace Database\Factories;

use App\Enums\College;
use App\Enums\DocumentKind;
use App\Enums\Faculty;
use App\Enums\RequestType;
use App\Enums\StudentKind;
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
        $requestType = fake()->randomElement(RequestType::cases());

        return [
            'ticket_number' => fake()->unique()->numberBetween(1, 9999),
            'public_token' => fake()->uuid(),
            'full_name' => fake()->name(),
            'student_kind' => StudentKind::NewStudent,
            'national_id' => fake()->numerify('##############'),
            'request_type' => $requestType,
            'college' => $requestType === RequestType::NominationCard
                ? fake()->randomElement(College::values())
                : 'كلية الهندسة',
            'department' => null,
            'order_number' => fake()->unique()->numerify('#########'),
            'seat_number' => null,
            'document_kind' => null,
            'document_path' => null,
            'status' => TicketStatus::Waiting,
            'user_id' => null,
            'called_at' => null,
            'entered_at' => null,
            'documents_reviewed_at' => null,
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
            'documents_reviewed_at' => null,
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }

    public function currentStudent(): static
    {
        return $this->state(fn () => [
            'student_kind' => StudentKind::CurrentStudent,
            'national_id' => null,
            'request_type' => null,
            'college' => fake()->randomElement(Faculty::values()),
            'department' => 'تكنولوجيا المعلومات',
            'order_number' => null,
            'seat_number' => fake()->unique()->numerify('#######'),
            'document_kind' => DocumentKind::StudentCard,
            'document_path' => 'current-student-documents/card.jpg',
        ]);
    }

    public function serving(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Serving,
            'user_id' => $teller?->id,
            'called_at' => now(),
            'entered_at' => now(),
            'documents_reviewed_at' => null,
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }

    public function medicalChecked(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Serving,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(3),
            'entered_at' => now()->subMinutes(3),
            'documents_reviewed_at' => null,
            'medical_checked_at' => now()->subMinutes(2),
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }

    public function facePrinted(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Serving,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(4),
            'entered_at' => now()->subMinutes(4),
            'documents_reviewed_at' => null,
            'medical_checked_at' => now()->subMinutes(3),
            'face_printed_at' => now()->subMinutes(2),
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
            'documents_reviewed_at' => null,
            'medical_checked_at' => now()->subMinutes(4),
            'face_printed_at' => now()->subMinutes(3),
            'completed_at' => now(),
            'file_delivered_at' => now()->subMinutes(1),
        ]);
    }

    public function documentsReviewed(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Serving,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(3),
            'entered_at' => now()->subMinutes(3),
            'documents_reviewed_at' => now()->subMinutes(2),
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }
}

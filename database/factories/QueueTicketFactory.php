<?php

namespace Database\Factories;

use App\Enums\DocumentKind;
use App\Enums\StudentKind;
use App\Enums\TicketStatus;
use App\Models\College;
use App\Models\Faculty;
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
            'student_kind' => StudentKind::NewStudent,
            'national_id' => fake()->numerify('##############'),
            'request_type' => 'nomination_card',
            'completion_step' => null,
            'college' => fake()->randomElement(College::activeSlugs()) ?: College::InformationTechnology,
            'department' => null,
            'order_number' => fake()->unique()->numerify('#########'),
            'seat_number' => null,
            'document_kind' => null,
            'document_path' => null,
            'status' => TicketStatus::Waiting,
            'user_id' => null,
            'called_at' => null,
            'entered_at' => null,
            'paid_at' => null,
            'file_withdrawn_at' => null,
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
            'paid_at' => null,
            'file_withdrawn_at' => null,
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
            'completion_step' => null,
            'college' => fake()->randomElement(Faculty::activeSlugs()) ?: Faculty::IndustryEnergy,
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
            'paid_at' => null,
            'file_withdrawn_at' => null,
            'documents_reviewed_at' => null,
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }

    public function paid(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Serving,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(2),
            'entered_at' => now()->subMinutes(2),
            'paid_at' => now()->subMinutes(1),
            'file_withdrawn_at' => null,
            'documents_reviewed_at' => null,
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }

    public function fileWithdrawn(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Serving,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(3),
            'entered_at' => now()->subMinutes(3),
            'paid_at' => now()->subMinutes(2),
            'file_withdrawn_at' => now()->subMinutes(1),
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
            'called_at' => now()->subMinutes(4),
            'entered_at' => now()->subMinutes(4),
            'paid_at' => now()->subMinutes(3),
            'file_withdrawn_at' => now()->subMinutes(2),
            'documents_reviewed_at' => null,
            'medical_checked_at' => now()->subMinutes(1),
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
            'called_at' => now()->subMinutes(5),
            'entered_at' => now()->subMinutes(5),
            'paid_at' => now()->subMinutes(4),
            'file_withdrawn_at' => now()->subMinutes(3),
            'documents_reviewed_at' => null,
            'medical_checked_at' => now()->subMinutes(2),
            'face_printed_at' => now()->subMinutes(1),
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }

    public function completed(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Completed,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(6),
            'entered_at' => now()->subMinutes(6),
            'paid_at' => now()->subMinutes(5),
            'file_withdrawn_at' => now()->subMinutes(4),
            'documents_reviewed_at' => null,
            'medical_checked_at' => now()->subMinutes(3),
            'face_printed_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'file_delivered_at' => now()->subMinutes(1),
        ]);
    }

    public function cancelled(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Cancelled,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(2),
            'entered_at' => now()->subMinutes(2),
            'paid_at' => null,
            'file_withdrawn_at' => null,
            'documents_reviewed_at' => null,
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => now(),
            'file_delivered_at' => null,
        ]);
    }

    public function documentsReviewed(?User $teller = null): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Serving,
            'user_id' => $teller?->id,
            'called_at' => now()->subMinutes(3),
            'entered_at' => now()->subMinutes(3),
            'paid_at' => null,
            'file_withdrawn_at' => null,
            'documents_reviewed_at' => now()->subMinutes(2),
            'medical_checked_at' => null,
            'face_printed_at' => null,
            'completed_at' => null,
            'file_delivered_at' => null,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_name_and_email(): void
    {
        $user = User::factory()->teller()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me', [
            'name' => 'اسم جديد',
            'email' => 'new-email@queue.local',
            'current_password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'اسم جديد')
            ->assertJsonPath('user.email', 'new-email@queue.local');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'اسم جديد',
            'email' => 'new-email@queue.local',
        ]);
    }

    public function test_profile_update_requires_correct_current_password(): void
    {
        $user = User::factory()->teller()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me', [
            'email' => 'new-email@queue.local',
            'current_password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_profile_update_rejects_an_email_that_is_already_taken(): void
    {
        User::factory()->create(['email' => 'taken@queue.local']);
        $user = User::factory()->teller()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/me', [
            'email' => 'taken@queue.local',
            'current_password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'البريد الإلكتروني مستخدم بالفعل.');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_guests_cannot_update_a_profile(): void
    {
        $this->putJson('/api/me', [
            'email' => 'new-email@queue.local',
            'current_password' => 'password',
        ])->assertUnauthorized();
    }
}

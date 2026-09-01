<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function staffRoles(): array
    {
        return [
            'teller' => [UserRole::Teller],
            'manager' => [UserRole::Manager],
            'super_admin' => [UserRole::SuperAdmin],
        ];
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->putJson('/api/me/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnauthorized();
    }

    #[DataProvider('staffRoles')]
    public function test_authenticated_user_can_change_own_password(UserRole $role): void
    {
        $user = $this->userForRole($role);

        Sanctum::actingAs($user);

        $this->putJson('/api/me/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تغيير كلمة المرور بنجاح.');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertFalse(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_rejects_change_when_current_password_is_incorrect(): void
    {
        $user = User::factory()->teller()->create([
            'password' => 'old-password',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password' => 'كلمة المرور الحالية غير صحيحة.',
            ]);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_rejects_change_when_password_confirmation_does_not_match(): void
    {
        $user = User::factory()->teller()->create([
            'password' => 'old-password',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/me/password', [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'other-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => 'تأكيد كلمة المرور غير متطابق.',
            ]);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_rejects_change_when_new_password_is_too_short(): void
    {
        $user = User::factory()->teller()->create([
            'password' => 'old-password',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/me/password', [
            'current_password' => 'old-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'password' => 'يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.',
            ]);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_rejects_change_when_required_fields_are_missing(): void
    {
        Sanctum::actingAs(User::factory()->teller()->create());

        $this->putJson('/api/me/password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password' => 'كلمة المرور الحالية مطلوبة.',
                'password' => 'كلمة المرور الجديدة مطلوبة.',
            ]);
    }

    private function userForRole(UserRole $role): User
    {
        $factory = match ($role) {
            UserRole::Teller => User::factory()->teller(),
            UserRole::Manager => User::factory()->manager(),
            UserRole::SuperAdmin => User::factory()->superAdmin(),
        };

        return $factory->create([
            'password' => 'old-password',
        ]);
    }
}

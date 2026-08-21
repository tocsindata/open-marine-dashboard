<?php

namespace Tests\Feature;

use App\Http\Controllers\PasswordResetController;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_sends_notification_without_disclosing_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'captain@example.test']);

        $known = $this->post(route('password.email'), ['email' => 'CAPTAIN@example.test']);
        $known->assertRedirect()->assertSessionHas('status', PasswordResetController::REQUEST_STATUS);
        Notification::assertSentTo($user, ResetPassword::class);

        Notification::fake();
        $unknown = $this->post(route('password.email'), ['email' => 'unknown@example.test']);
        $unknown->assertRedirect()->assertSessionHas('status', PasswordResetController::REQUEST_STATUS);
        Notification::assertNothingSent();
        $this->assertSame($known->getSession()->get('status'), $unknown->getSession()->get('status'));
    }

    public function test_valid_token_resets_only_matching_user_and_rotates_remember_token(): void
    {
        $user = User::factory()->create([
            'email' => 'captain@example.test',
            'password' => 'OldPassword123',
            'remember_token' => 'old-remember-token',
        ]);
        $other = User::factory()->create(['password' => 'OtherPassword123']);
        $otherPassword = $other->password;
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'CAPTAIN@example.test',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword456', $user->password));
        $this->assertFalse(Hash::check('OldPassword123', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertSame($otherPassword, $other->fresh()->password);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->id,
            'action' => 'auth.password_reset',
            'target_id' => (string) $user->id,
        ]);
    }

    public function test_inactive_account_cannot_receive_or_use_a_reset_token(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'disabled@example.test',
            'is_active' => false,
            'password' => 'OldPassword123',
        ]);

        $this->post(route('password.email'), ['email' => 'DISABLED@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', PasswordResetController::REQUEST_STATUS);
        Notification::assertNothingSent();

        $token = Password::createToken($user);
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'disabled@example.test',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
        $this->assertDatabaseMissing('audit_events', [
            'actor_id' => $user->id,
            'action' => 'auth.password_reset',
        ]);
    }

    public function test_invalid_or_expired_token_does_not_change_password(): void
    {
        $user = User::factory()->create(['email' => 'captain@example.test', 'password' => 'OldPassword123']);

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'captain@example.test',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));

        $token = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'captain@example.test',
            'password' => 'NewPassword456',
            'password_confirmation' => 'NewPassword456',
        ])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        Notification::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.email'), ['email' => 'captain@example.test'])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'captain@example.test'])->assertStatus(429);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_shows_only_the_current_users_activity(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        AuditEvent::create([
            'actor_id' => $user->id,
            'channel' => 'web',
            'action' => 'auth.login',
            'target_type' => User::class,
            'target_id' => (string) $user->id,
            'occurred_at' => now(),
        ]);
        AuditEvent::create([
            'actor_id' => $other->id,
            'channel' => 'web',
            'action' => 'auth.password_reset',
            'target_type' => User::class,
            'target_id' => (string) $other->id,
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('profile.security.show'))
            ->assertOk()
            ->assertSee('Account security')
            ->assertSee($user->email)
            ->assertSee('Signed in')
            ->assertDontSee($other->email)
            ->assertDontSee('Password reset completed');
    }

    public function test_current_password_ends_other_sessions_and_keeps_current_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.security.sessions.destroy-others'), ['password' => 'password'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Other browser sessions have been ended. This browser remains signed in.');

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk();
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->id,
            'action' => 'auth.other_sessions_invalidated',
            'target_id' => (string) $user->id,
        ]);
    }

    public function test_wrong_password_does_not_end_sessions_or_write_activity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.security.sessions.destroy-others'), ['password' => 'incorrect-password'])
            ->assertSessionHasErrors('password');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseMissing('audit_events', [
            'actor_id' => $user->id,
            'action' => 'auth.other_sessions_invalidated',
        ]);
    }

    public function test_password_change_invalidates_an_existing_authenticated_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $user->forceFill(['password' => Hash::make('new-password')])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_unverified_user_cannot_access_account_security(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('profile.security.show'))
            ->assertRedirect(route('verification.notice'));
    }
}

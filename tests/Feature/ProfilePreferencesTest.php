<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_view_profile_preferences(): void
    {
        $user = User::factory()->create([
            'timezone' => 'America/Vancouver',
            'unit_system' => 'metric',
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profile preferences')
            ->assertSee('America/Vancouver')
            ->assertSee('Metric')
            ->assertSee('Navigation distances remain nautical');
    }

    public function test_update_changes_only_the_authenticated_user_and_writes_value_free_audit(): void
    {
        $user = User::factory()->create([
            'name' => 'Original captain',
            'timezone' => 'America/Vancouver',
            'unit_system' => 'metric',
        ]);
        $other = User::factory()->create([
            'name' => 'Other captain',
            'timezone' => 'America/Toronto',
            'unit_system' => 'metric',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated captain',
                'timezone' => 'America/Halifax',
                'unit_system' => 'imperial',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Profile preferences saved.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated captain',
            'timezone' => 'America/Halifax',
            'unit_system' => 'imperial',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $other->id,
            'name' => 'Other captain',
            'timezone' => 'America/Toronto',
            'unit_system' => 'metric',
        ]);

        $event = AuditEvent::query()
            ->where('actor_id', $user->id)
            ->where('action', 'account.profile_updated')
            ->sole();

        $this->assertSame((string) $user->id, $event->target_id);
        $this->assertNull($event->metadata);
    }

    public function test_profile_boundaries_accept_exact_limits_and_reject_values_beyond_them(): void
    {
        $user = User::factory()->create();
        $maximumName = str_repeat('N', 120);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $maximumName,
                'timezone' => 'UTC',
                'unit_system' => 'metric',
            ])
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $maximumName,
            'timezone' => 'UTC',
            'unit_system' => 'metric',
        ]);

        $this->patch(route('profile.update'), [
            'name' => str_repeat('N', 121),
            'timezone' => 'Mars/Olympus_Mons',
            'unit_system' => 'unsupported',
        ])->assertSessionHasErrors(['name', 'timezone', 'unit_system']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $maximumName,
            'timezone' => 'UTC',
            'unit_system' => 'metric',
        ]);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_unchanged_preferences_do_not_create_redundant_audit_activity(): void
    {
        $user = User::factory()->create([
            'name' => 'Current captain',
            'timezone' => 'America/Vancouver',
            'unit_system' => 'metric',
        ]);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Current captain',
                'timezone' => 'America/Vancouver',
                'unit_system' => 'metric',
            ])
            ->assertSessionHas('status', 'Profile preferences are already current.');

        $this->assertDatabaseMissing('audit_events', [
            'actor_id' => $user->id,
            'action' => 'account.profile_updated',
        ]);
    }

    public function test_unverified_user_cannot_access_profile_preferences(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Blocked update',
                'timezone' => 'UTC',
                'unit_system' => 'metric',
            ])
            ->assertRedirect(route('verification.notice'));

        $this->assertNotSame('Blocked update', $user->fresh()->name);
    }
}

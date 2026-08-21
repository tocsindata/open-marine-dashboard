<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTimePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_views_render_utc_deadlines_across_the_vancouver_spring_time_change(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Vancouver']);
        $other = User::factory()->create(['timezone' => 'America/Toronto']);

        $user->tasks()->create([
            'title' => 'Before spring change',
            'due_at' => CarbonImmutable::parse('2026-03-08 09:59:00', 'UTC'),
        ]);
        $user->tasks()->create([
            'title' => 'After spring change',
            'due_at' => CarbonImmutable::parse('2026-03-08 10:00:00', 'UTC'),
        ]);
        $other->tasks()->create([
            'title' => 'Foreign private task',
            'due_at' => CarbonImmutable::parse('2026-03-08 10:00:00', 'UTC'),
        ]);

        foreach ([route('tasks.index'), route('dashboard')] as $route) {
            $this->actingAs($user)
                ->get($route)
                ->assertOk()
                ->assertSee('Mar 8, 2026 1:59 am · UTC-08:00 · America/Vancouver')
                ->assertSee('Mar 8, 2026 3:00 am · UTC-07:00 · America/Vancouver')
                ->assertSee('datetime="2026-03-08T09:59:00Z"', false)
                ->assertSee('datetime="2026-03-08T10:00:00Z"', false)
                ->assertDontSee('Foreign private task');
        }
    }

    public function test_unique_local_task_time_is_converted_to_utc_and_rendered_for_the_owner(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Toronto']);

        $this->actingAs($user)
            ->post(route('tasks.store'), [
                'title' => 'Local input boundary',
                'priority' => 'normal',
                'due_at' => '2026-01-15T12:00',
                'due_time_mode' => 'local',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Local input boundary',
            'due_at' => '2026-01-15 17:00:00',
        ]);

        $this->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Interpret entered time as')
            ->assertSee('My time zone (America/Toronto)')
            ->assertSee('Local time is converted to UTC for storage.')
            ->assertSee('Jan 15, 2026 12:00 pm · UTC-05:00 · America/Toronto')
            ->assertSee('datetime="2026-01-15T17:00:00Z"', false);
    }

    public function test_nonexistent_local_task_time_is_rejected_without_writing(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Toronto']);

        $this->actingAs($user)
            ->from(route('tasks.index'))
            ->post(route('tasks.store'), [
                'title' => 'Clock gap',
                'priority' => 'normal',
                'due_at' => '2026-03-08T02:30',
                'due_time_mode' => 'local',
            ])
            ->assertRedirect(route('tasks.index'))
            ->assertSessionHasErrors([
                'due_at' => 'That local time does not exist in America/Toronto because the clock changes. Choose another local time or enter it as UTC.',
            ]);

        $this->assertDatabaseMissing('tasks', [
            'user_id' => $user->id,
            'title' => 'Clock gap',
        ]);
    }

    public function test_ambiguous_local_task_time_is_rejected_without_writing(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Toronto']);

        $this->actingAs($user)
            ->from(route('tasks.index'))
            ->post(route('tasks.store'), [
                'title' => 'Repeated hour',
                'priority' => 'normal',
                'due_at' => '2026-11-01T01:30',
                'due_time_mode' => 'local',
            ])
            ->assertRedirect(route('tasks.index'))
            ->assertSessionHasErrors([
                'due_at' => 'That local time occurs more than once in America/Toronto. Enter the deadline as UTC so the intended instant is explicit.',
            ]);

        $this->assertDatabaseMissing('tasks', [
            'user_id' => $user->id,
            'title' => 'Repeated hour',
        ]);
    }

    public function test_explicit_utc_mode_accepts_a_repeated_local_clock_value_without_shifting_it(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Toronto']);

        $this->actingAs($user)
            ->post(route('tasks.store'), [
                'title' => 'Explicit UTC instant',
                'priority' => 'normal',
                'due_at' => '2026-11-01T01:30',
                'due_time_mode' => 'utc',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Explicit UTC instant',
            'due_at' => '2026-11-01 01:30:00',
        ]);
    }

    public function test_task_time_input_requires_an_exact_valid_minute_but_no_deadline_remains_optional(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Toronto']);

        $this->actingAs($user)
            ->post(route('tasks.store'), [
                'title' => 'Invalid calendar date',
                'priority' => 'normal',
                'due_at' => '2026-02-30T12:00',
                'due_time_mode' => 'local',
            ])
            ->assertSessionHasErrors('due_at');

        $this->post(route('tasks.store'), [
            'title' => 'No deadline',
            'priority' => 'normal',
        ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'No deadline',
            'due_at' => null,
        ]);
    }

    public function test_shared_renderer_preserves_bc_permanent_pacific_time_and_account_activity_utc(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Vancouver']);

        foreach ([
            ['Before former fall rollback', '2026-11-01 08:59:00'],
            ['After former fall rollback', '2026-11-01 09:00:00'],
        ] as [$title, $dueAt]) {
            $user->tasks()->create([
                'title' => $title,
                'due_at' => CarbonImmutable::parse($dueAt, 'UTC'),
            ]);
        }

        $this->actingAs($user)
            ->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('datetime="2026-11-01T08:59:00Z"', false)
            ->assertSee('datetime="2026-11-01T09:00:00Z"', false)
            ->assertSee('Nov 1, 2026 1:59 am · UTC-07:00 · America/Vancouver')
            ->assertSee('Nov 1, 2026 2:00 am · UTC-07:00 · America/Vancouver')
            ->assertDontSee('UTC-08:00 · America/Vancouver');

        AuditEvent::create([
            'actor_id' => $user->id,
            'channel' => 'web',
            'action' => 'auth.login',
            'target_type' => User::class,
            'target_id' => (string) $user->id,
            'occurred_at' => CarbonImmutable::parse('2027-01-15 20:00:00', 'UTC'),
        ]);

        $this->get(route('profile.security.show'))
            ->assertOk()
            ->assertSee('2027-01-15 13:00 · UTC-07:00 · America/Vancouver')
            ->assertSee('datetime="2027-01-15T20:00:00Z"', false);
    }
}

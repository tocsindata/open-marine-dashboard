<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: tests/Feature/MarineCoreTest.php
 * Date: 2026-08-19
 * SPDX-License-Identifier: AGPL-3.0-only
 * Copyright (c) 2026 Tocsin Data.
 */

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\SourceImportStep;
use App\Models\User;
use App\Models\UserSourceCredential;
use App\Models\VesselPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class MarineCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_authenticated_session_is_invalidated_before_private_page(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->update(['is_active' => false]);

        $this->withSession([
            '_token' => 'known-token',
            'private-marker' => 'sensitive',
        ])->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your session has ended. Please sign in again.')
            ->assertSessionMissing('private-marker')
            ->assertSessionHas('_token', fn (string $token): bool => $token !== 'known-token');

        $this->assertGuest();
    }

    public function test_inactive_authenticated_session_receives_generic_json_unauthorized_response(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->update(['is_active' => false]);

        $this->actingAs($user)
            ->getJson(route('map.data'))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->assertGuest();
    }

    public function test_registration_requires_policy_acceptance_and_sends_verification(): void
    {
        Notification::fake();
        $this->post('/register', ['name' => 'Skipper', 'email' => 'skipper@example.test', 'password' => 'SafePassword123', 'password_confirmation' => 'SafePassword123'])
            ->assertSessionHasErrors(['terms', 'privacy']);
        $this->post('/register', ['name' => 'Skipper', 'email' => 'SKIPPER@example.test', 'password' => 'SafePassword123', 'password_confirmation' => 'SafePassword123', 'terms' => '1', 'privacy' => '1'])
            ->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', ['email' => 'skipper@example.test', 'policy_version' => '2026-08-19-v1']);
    }

    public function test_explicit_local_mode_auto_verifies_registration_without_smtp(): void
    {
        Notification::fake();
        $this->app->instance('env', 'local');
        config(['marine.local_auto_verify_email' => true]);

        $this->assertTrue($this->app->environment('local'));

        $this->withSession(['_token' => 'local-test-token'])
            ->post('/register', ['_token' => 'local-test-token', 'name' => 'Local Skipper', 'email' => 'local@example.test', 'password' => 'SafePassword123', 'password_confirmation' => 'SafePassword123', 'terms' => '1', 'privacy' => '1'])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull(User::where('email', 'local@example.test')->firstOrFail()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_terminal_command_verifies_one_exact_user_and_is_idempotent(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'verify@example.test']);

        $this->artisan('marine:verify-user', ['email' => 'VERIFY@example.test'])
            ->expectsConfirmation('Mark verify@example.test as verified?', 'yes')
            ->expectsOutput('verify@example.test has been verified.')
            ->assertSuccessful();

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'email.manually_verified',
            'target_id' => (string) $user->id,
            'channel' => 'terminal',
        ]);

        $this->artisan('marine:verify-user', ['email' => 'verify@example.test', '--yes' => true])
            ->expectsOutput('verify@example.test is already verified. No change was made.')
            ->assertSuccessful();
    }

    public function test_user_can_store_an_encrypted_paid_provider_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('profile.sources.store'), [
            'provider_key' => 'stormglass',
            'token' => 'secret-stormglass-token',
            'default_latitude' => 49.2827,
            'default_longitude' => -123.1207,
            'interval_minutes' => 60,
            'enabled' => 1,
        ])->assertRedirect();

        $raw = \DB::table('user_source_credentials')->where('user_id', $user->id)->first();
        $this->assertNotSame('secret-stormglass-token', $raw->encrypted_token);
        $this->assertSame('secret-stormglass-token', $user->sourceCredentials()->firstOrFail()->encrypted_token);
        $this->assertDatabaseHas('source_import_steps', [
            'provider_key' => 'stormglass',
            'user_id' => $user->id,
            'enabled' => true,
        ]);
    }

    public function test_paid_provider_failure_redacts_token_and_provider_response(): void
    {
        $user = User::factory()->create();
        $token = 'secret-user-funded-token';
        $credential = $user->sourceCredentials()->create([
            'provider_key' => 'worldtides',
            'encrypted_token' => $token,
            'token_hint' => 'oken',
            'default_latitude' => 49.2827,
            'default_longitude' => -123.1207,
            'interval_minutes' => 60,
            'enabled' => true,
        ]);

        Log::spy();
        Http::fake([
            'www.worldtides.info/*' => Http::response(
                ['error' => "Invalid token {$token}"],
                401,
            ),
        ]);

        $this->actingAs($user)
            ->post(route('profile.sources.test', 'worldtides'))
            ->assertRedirect()
            ->assertSessionHasErrors(['provider']);

        $credential->refresh();
        $this->assertSame('failed', $credential->last_test_result);
        $this->assertSame(
            'The provider connection test failed. Verify the token and settings, then try again.',
            $credential->last_test_message,
        );
        $this->assertStringNotContainsString($token, $credential->last_test_message);
        $this->assertStringNotContainsString($token, session('errors')->first('provider'));
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Marine provider credential test failed.'
                && $context === [
                    'provider_key' => 'worldtides',
                    'user_id' => (string) $user->id,
                    'exception_type' => RequestException::class,
                    'http_status' => 401,
                ]);
    }

    public function test_audit_metadata_rejects_restricted_fields_without_persisting_values(): void
    {
        foreach ([
            ['latitude' => 49.28],
            ['nested' => ['token' => 'private-provider-value']],
            ['response-body' => ['private' => 'provider payload']],
        ] as $metadata) {
            try {
                AuditEvent::create([
                    'channel' => 'test',
                    'action' => 'privacy.guard',
                    'metadata' => $metadata,
                    'occurred_at' => now(),
                ]);
                $this->fail('Restricted audit metadata was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Audit metadata contains a restricted field.', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_provider_credential_routes_preserve_owner_cross_user_missing_and_deleted_boundaries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $credential = $owner->sourceCredentials()->create([
            'provider_key' => 'worldtides',
            'encrypted_token' => 'private-owner-token',
            'token_hint' => 'oken',
            'default_latitude' => 49.2827,
            'default_longitude' => -123.1207,
            'interval_minutes' => 60,
            'enabled' => true,
        ]);

        Http::fake();

        $this->actingAs($other)
            ->post(route('profile.sources.test', 'worldtides'))
            ->assertNotFound();
        $this->actingAs($other)
            ->delete(route('profile.sources.destroy', 'worldtides'))
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('profile.sources.test', 'stormglass'))
            ->assertNotFound();
        $this->assertDatabaseHas('user_source_credentials', ['id' => $credential->id]);
        Http::assertNothingSent();

        $this->actingAs($owner)
            ->delete(route('profile.sources.destroy', 'worldtides'))
            ->assertRedirect();
        $this->assertDatabaseMissing('user_source_credentials', ['id' => $credential->id]);
        $this->actingAs($owner)
            ->post(route('profile.sources.test', 'worldtides'))
            ->assertNotFound();
    }

    public function test_source_tick_runs_exactly_one_due_step(): void
    {
        Http::fake([
            'api.weather.gc.ca/*' => Http::response(['features' => [
                ['id' => 'alert-1', 'properties' => ['headline' => 'Test alert']],
            ]]),
        ]);

        \DB::table('source_import_steps')->update(['enabled' => false]);
        \DB::table('source_import_steps')->insert([
            'id' => (string) str()->ulid(),
            'provider_key' => 'eccc_alerts',
            'scope_key' => 'test-one',
            'enabled' => true,
            'interval_minutes' => 5,
            'next_due_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('source_import_steps')->insert([
            'id' => (string) str()->ulid(),
            'provider_key' => 'eccc_alerts',
            'scope_key' => 'test-two',
            'enabled' => true,
            'interval_minutes' => 5,
            'next_due_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('marine:sources:tick')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame(1, \DB::table('source_import_steps')->where('last_result', 'success')->count());
        $this->assertDatabaseHas('source_records', ['provider_key' => 'eccc_alerts', 'external_id' => 'alert-1']);
    }

    public function test_vessel_policy_allows_owner_and_denies_other_user_for_route_bound_actions(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Private Vessel']);

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $vessel));
            $this->assertFalse(Gate::forUser($other)->allows($ability, $vessel));
        }

        $this->actingAs($other)->get(route('vessels.show', $vessel))->assertForbidden();
        $this->actingAs($other)->get(route('vessels.edit', $vessel))->assertForbidden();
        $this->actingAs($other)->put(route('vessels.update', $vessel))->assertForbidden();
        $this->actingAs($other)->delete(route('vessels.destroy', $vessel))->assertForbidden();

        $this->assertDatabaseHas('vessels', [
            'id' => $vessel->id,
            'name' => 'Private Vessel',
            'deleted_at' => null,
        ]);
        $this->actingAs($owner)->get(route('vessels.show', $vessel))
            ->assertOk()
            ->assertSee('Private Vessel');
    }

    public function test_task_policy_allows_owner_and_denies_other_user_completion(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task = $owner->tasks()->create(['title' => 'Private task']);

        $this->assertTrue(Gate::forUser($owner)->allows('complete', $task));
        $this->assertFalse(Gate::forUser($other)->allows('complete', $task));

        $this->actingAs($other)->patch(route('tasks.complete', $task))->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'open']);

        $this->actingAs($owner)->patch(route('tasks.complete', $task))->assertRedirect();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);
    }

    public function test_super_admin_command_creates_verified_active_account_without_web_route(): void
    {
        $this->artisan('marine:super-admin', ['email' => 'admin@example.test', '--name' => 'Marine Admin', '--verify-email' => true, '--activate' => true])
            ->expectsQuestion('Password (minimum 12 characters)', 'AnotherSafePassword123')
            ->expectsConfirmation('Create this account and grant super administrator privileges?', 'yes')
            ->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'admin@example.test', 'role' => 'super_admin', 'is_active' => true]);
        $this->get('/marine/super-admin')->assertNotFound();
    }

    public function test_map_data_only_returns_the_authenticated_users_vessel_positions(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owned = $owner->vessels()->create(['name' => 'Owned Vessel']);
        $foreign = $other->vessels()->create(['name' => 'Foreign Vessel']);
        $owned->positions()->create(['latitude' => 49.28, 'longitude' => -123.12, 'source' => 'manual', 'recorded_at' => now()]);
        $foreign->positions()->create(['latitude' => 48.42, 'longitude' => -123.36, 'source' => 'manual', 'recorded_at' => now()]);

        $response = $this->actingAs($owner)->getJson(route('map.data'));

        $response
            ->assertOk()
            ->assertHeader('Pragma', 'no-cache')
            ->assertJsonPath('vessels.0.name', 'Owned Vessel')
            ->assertJsonMissing(['name' => 'Foreign Vessel']);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_map_data_reports_current_stale_and_unavailable_source_states(): void
    {
        $user = User::factory()->create();
        $now = now();

        foreach ([
            ['provider_key' => 'eccc_alerts', 'external_id' => 'current-alert', 'record_type' => 'weather_alert', 'retrieved_at' => $now->copy()->subMinutes(5)],
            ['provider_key' => 'chs_stations', 'external_id' => 'stale-station', 'record_type' => 'water_level_station', 'retrieved_at' => $now->copy()->subMinutes(2161)],
        ] as $record) {
            $payload = json_encode(['id' => $record['external_id']], JSON_THROW_ON_ERROR);
            \DB::table('source_records')->insert($record + [
                'id' => (string) str()->ulid(),
                'user_id' => null,
                'payload' => $payload,
                'payload_hash' => hash('sha256', $payload),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->actingAs($user)->getJson(route('map.data'))
            ->assertOk()
            ->assertJsonPath('sources.eccc_alerts.status', 'current')
            ->assertJsonPath('sources.eccc_alerts.record_count', 1)
            ->assertJsonPath('sources.chs_stations.status', 'stale')
            ->assertJsonPath('sources.usgs_earthquakes.status', 'unavailable')
            ->assertJsonPath('sources.vesselfinder_livedata.status', 'unavailable');
    }

    public function test_position_recording_preserves_owner_cross_user_missing_and_deleted_boundaries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Private Position']);
        $position = [
            'vessel_id' => $vessel->id,
            'latitude' => 49.28,
            'longitude' => -123.12,
        ];

        $this->actingAs($other)
            ->post(route('map.position.store'), $position)
            ->assertSessionHasErrors('vessel_id');
        $this->actingAs($owner)
            ->post(route('map.position.store'), $position)
            ->assertRedirect();
        $this->assertDatabaseCount('vessel_positions', 1);
        $this->assertSame(
            [
                'position_id' => VesselPosition::query()->sole()->id,
                'source' => 'manual',
                'location_forecasts_enabled' => false,
            ],
            AuditEvent::query()->where('action', 'vessel.position_recorded')->sole()->metadata,
        );

        $this->actingAs($owner)->post(route('map.position.store'), [
            ...$position,
            'vessel_id' => (string) str()->ulid(),
        ])->assertSessionHasErrors('vessel_id');
        $this->assertDatabaseCount('vessel_positions', 1);

        $this->actingAs($owner)
            ->delete(route('vessels.destroy', $vessel))
            ->assertRedirect();
        $this->actingAs($owner)
            ->post(route('map.position.store'), $position)
            ->assertSessionHasErrors('vessel_id');
        $this->assertDatabaseCount('vessel_positions', 1);
    }

    public function test_position_and_provider_credential_policies_and_scopes_enforce_owner_boundary(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownedVessel = $owner->vessels()->create(['name' => 'Owned Position']);
        $foreignVessel = $other->vessels()->create(['name' => 'Foreign Position']);
        $ownedPosition = $ownedVessel->positions()->create([
            'latitude' => 49.28,
            'longitude' => -123.12,
            'source' => 'manual',
            'recorded_at' => now(),
        ]);
        $foreignPosition = $foreignVessel->positions()->create([
            'latitude' => 48.42,
            'longitude' => -123.36,
            'source' => 'manual',
            'recorded_at' => now(),
        ]);
        $ownedCredential = $owner->sourceCredentials()->create([
            'provider_key' => 'stormglass',
            'encrypted_token' => 'private-owner-token',
            'token_hint' => 'oken',
            'default_latitude' => 49.2827,
            'default_longitude' => -123.1207,
            'interval_minutes' => 60,
            'enabled' => true,
        ]);
        $foreignCredential = $other->sourceCredentials()->create([
            'provider_key' => 'stormglass',
            'encrypted_token' => 'private-other-token',
            'token_hint' => 'oken',
            'default_latitude' => 48.4284,
            'default_longitude' => -123.3656,
            'interval_minutes' => 60,
            'enabled' => true,
        ]);

        $this->assertEqualsCanonicalizing(
            [$ownedPosition->id],
            VesselPosition::query()->ownedBy($owner)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$ownedCredential->id],
            UserSourceCredential::query()->ownedBy($owner)->pluck('id')->all(),
        );

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $ownedPosition));
            $this->assertFalse(Gate::forUser($other)->allows($ability, $ownedPosition));
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $ownedCredential));
            $this->assertFalse(Gate::forUser($other)->allows($ability, $ownedCredential));
        }

        $ownedVessel->update(['status' => 'archived']);
        $ownedVessel->delete();
        $ownedCredential->delete();

        $this->assertFalse(VesselPosition::query()->ownedBy($owner)->whereKey($ownedPosition->getKey())->exists());
        $this->assertFalse(UserSourceCredential::query()->ownedBy($owner)->whereKey($ownedCredential->getKey())->exists());
        $this->assertFalse(Gate::forUser($owner)->allows('view', $ownedPosition));
        $this->assertTrue(VesselPosition::query()->whereKey($foreignPosition->getKey())->exists());
        $this->assertTrue(UserSourceCredential::query()->whereKey($foreignCredential->getKey())->exists());
    }

    public function test_map_data_includes_timestamped_weather_and_marine_forecasts(): void
    {
        $user = User::factory()->create();
        $now = now();

        foreach (['open_meteo_weather' => 'weather_forecast', 'open_meteo_marine' => 'marine_forecast'] as $provider => $type) {
            $payload = json_encode([
                'id' => 'vancouver',
                'location_name' => 'Vancouver / English Bay',
                'requested_latitude' => 49.285,
                'requested_longitude' => -123.19,
                'forecast' => ['current' => ['time' => $now->toIso8601String()]],
            ], JSON_THROW_ON_ERROR);
            \DB::table('source_records')->insert([
                'id' => (string) str()->ulid(),
                'provider_key' => $provider,
                'user_id' => null,
                'external_id' => 'vancouver',
                'record_type' => $type,
                'payload' => $payload,
                'payload_hash' => hash('sha256', $payload),
                'retrieved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->actingAs($user)->getJson(route('map.data'))
            ->assertOk()
            ->assertJsonPath('sources.open_meteo_weather.status', 'current')
            ->assertJsonPath('sources.open_meteo_marine.status', 'current')
            ->assertJsonPath('weather_forecasts.0.location_name', 'Vancouver / English Bay')
            ->assertJsonPath('marine_forecasts.0.location_name', 'Vancouver / English Bay')
            ->assertJsonPath('weather_forecasts.0._marine_retrieved_at', $now->toIso8601String());
    }

    public function test_targeted_forced_tick_imports_one_forecast_location(): void
    {
        Http::fake([
            'marine-api.open-meteo.com/*' => Http::response([
                'current' => ['time' => now()->toIso8601String(), 'wave_height' => 1.2],
                'current_units' => ['wave_height' => 'm'],
                'hourly' => ['time' => []],
            ]),
        ]);

        \DB::table('source_import_steps')->update(['enabled' => false]);
        \DB::table('source_import_steps')
            ->where('provider_key', 'open_meteo_marine')
            ->where('scope_key', 'vancouver')
            ->update(['enabled' => true, 'next_due_at' => now()->addDay()]);

        $this->artisan('marine:sources:tick', [
            '--provider' => 'open_meteo_marine',
            '--scope' => 'vancouver',
            '--force' => true,
        ])->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseHas('source_records', [
            'provider_key' => 'open_meteo_marine',
            'external_id' => 'vancouver',
            'record_type' => 'marine_forecast',
        ]);
    }

    public function test_position_report_schedules_vessel_relative_forecasts(): void
    {
        $user = User::factory()->create();
        $vessel = $user->vessels()->create(['name' => 'Forecast Vessel']);

        $this->actingAs($user)->post(route('map.position.store'), [
            'vessel_id' => $vessel->id,
            'latitude' => 49.1,
            'longitude' => -123.2,
            'enable_location_forecasts' => '1',
        ])->assertRedirect();

        foreach (['open_meteo_weather', 'open_meteo_marine'] as $provider) {
            $step = SourceImportStep::query()
                ->where('provider_key', $provider)
                ->where('scope_key', "vessel:{$vessel->id}")
                ->where('user_id', $user->id)
                ->firstOrFail();

            $this->assertTrue($step->enabled);
            $this->assertNotNull($step->consent_granted_at);
            $this->assertNull($step->consent_revoked_at);
        }
    }

    public function test_position_report_does_not_schedule_external_forecasts_without_consent(): void
    {
        $user = User::factory()->create();
        $vessel = $user->vessels()->create(['name' => 'Private Forecast Vessel']);

        $this->actingAs($user)->post(route('map.position.store'), [
            'vessel_id' => $vessel->id,
            'latitude' => 49.1234567,
            'longitude' => -123.2345678,
        ])->assertRedirect();

        $this->assertDatabaseMissing('source_import_steps', [
            'scope_key' => "vessel:{$vessel->id}",
            'user_id' => $user->id,
            'enabled' => true,
        ]);
    }

    public function test_position_report_revokes_vessel_forecast_consent(): void
    {
        $user = User::factory()->create();
        $vessel = $user->vessels()->create(['name' => 'Revoked Forecast Vessel']);
        $position = [
            'vessel_id' => $vessel->id,
            'latitude' => 49.1,
            'longitude' => -123.2,
        ];

        $this->actingAs($user)->post(route('map.position.store'), $position + [
            'enable_location_forecasts' => '1',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('map.position.store'), $position)->assertRedirect();

        foreach (['open_meteo_weather', 'open_meteo_marine'] as $provider) {
            $step = SourceImportStep::query()
                ->where('provider_key', $provider)
                ->where('scope_key', "vessel:{$vessel->id}")
                ->where('user_id', $user->id)
                ->firstOrFail();

            $this->assertFalse($step->enabled);
            $this->assertNotNull($step->consent_revoked_at);
        }
    }

    public function test_source_failures_do_not_persist_request_urls_or_coordinates(): void
    {
        Http::fake([
            'api.open-meteo.com/*' => Http::response(['reason' => 'test failure'], 500),
        ]);

        \DB::table('source_import_steps')->update(['enabled' => false]);
        $step = SourceImportStep::query()
            ->where('provider_key', 'open_meteo_weather')
            ->where('scope_key', 'vancouver')
            ->firstOrFail();
        $step->update(['enabled' => true, 'next_due_at' => now()->addDay()]);

        $this->artisan('marine:sources:tick', [
            '--provider' => 'open_meteo_weather',
            '--scope' => 'vancouver',
            '--force' => true,
        ])->assertFailed();

        $step->refresh();
        $this->assertSame('Provider request failed (HTTP 500).', $step->last_message);
        $this->assertStringNotContainsString('49.285', (string) $step->last_message);
        $this->assertStringNotContainsString('api.open-meteo.com', (string) $step->last_message);
    }

    public function test_only_super_admin_can_view_source_diagnostics(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $inactiveAdmin = User::factory()->create(['role' => 'super_admin', 'is_active' => false]);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);

        $this->actingAs($user)->get(route('admin.source-diagnostics'))->assertForbidden();
        $this->actingAs($inactiveAdmin)
            ->get(route('admin.source-diagnostics'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
        $this->actingAs($admin)->get(route('admin.source-diagnostics'))
            ->assertOk()
            ->assertSee('Source diagnostics')
            ->assertDontSee('encrypted_token');
    }
}

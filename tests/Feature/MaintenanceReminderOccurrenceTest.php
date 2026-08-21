<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\MaintenanceReminderOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceReminderOccurrenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_opens_each_eligible_in_app_occurrence_exactly_once(): void
    {
        $this->travelTo('2026-08-20 12:00:00 UTC');

        $owner = User::factory()->create(['timezone' => 'UTC']);
        $vessel = $owner->vessels()->create(['name' => 'Reminder Vessel']);
        $equipment = $vessel->equipmentItems()->create([
            'category' => 'propulsion',
            'name' => 'Main engine',
            'status' => 'active',
            'meter_hours' => '1090.0',
        ]);

        $calendar = $vessel->maintenanceSchedules()->create([
            'name' => 'Calendar boundary reminder',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);
        $annual = $vessel->maintenanceSchedules()->create([
            'name' => 'Annual lead reminder',
            'basis' => 'annual_date',
            'annual_month' => 9,
            'annual_day' => 1,
            'reminder_enabled' => true,
            'reminder_lead_days' => 12,
            'status' => 'active',
        ]);
        $meter = $vessel->maintenanceSchedules()->create([
            'equipment_item_id' => $equipment->id,
            'name' => 'Meter lead reminder',
            'basis' => 'meter_hours',
            'interval_hours' => '100.0',
            'baseline_hours' => '1000.0',
            'reminder_enabled' => true,
            'reminder_lead_hours' => '10.0',
            'status' => 'active',
        ]);
        $vessel->maintenanceSchedules()->create([
            'name' => 'Not eligible yet',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-08-01',
            'reminder_enabled' => true,
            'reminder_lead_days' => 10,
            'status' => 'active',
        ]);
        $vessel->maintenanceSchedules()->create([
            'name' => 'Disabled at due boundary',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => false,
            'status' => 'active',
        ]);
        $vessel->maintenanceSchedules()->create([
            'name' => 'Paused at due boundary',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'paused',
        ]);

        $this->artisan('marine:maintenance-reminders:sync')
            ->expectsOutput('Opened 3 in-app maintenance reminder occurrence(s).')
            ->assertSuccessful();
        $this->artisan('marine:maintenance-reminders:sync')
            ->expectsOutput('Opened 0 in-app maintenance reminder occurrence(s).')
            ->assertSuccessful();

        $this->assertDatabaseCount('maintenance_reminder_occurrences', 3);
        $this->assertDatabaseHas('maintenance_reminder_occurrences', [
            'maintenance_schedule_id' => $calendar->id,
            'reference_type' => 'calendar_days',
            'reference_value' => '2026-08-20',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('maintenance_reminder_occurrences', [
            'maintenance_schedule_id' => $annual->id,
            'reference_type' => 'annual_date',
            'reference_value' => '2026-09-01',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('maintenance_reminder_occurrences', [
            'maintenance_schedule_id' => $meter->id,
            'reference_type' => 'meter_hours',
            'reference_value' => '1100.0',
            'status' => 'open',
        ]);
        $this->assertSame(
            3,
            AuditEvent::query()
                ->where('action', 'maintenance_reminder.opened')
                ->whereNull('actor_id')
                ->whereNull('metadata')
                ->count(),
        );
    }

    public function test_owner_can_view_and_idempotently_acknowledge_only_their_open_occurrence(): void
    {
        $this->travelTo('2026-08-20 12:00:00 UTC');

        $owner = User::factory()->create(['timezone' => 'UTC']);
        $other = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Private Reminder Vessel']);
        $schedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Private calendar reminder',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);

        $this->artisan('marine:maintenance-reminders:sync')->assertSuccessful();
        $occurrence = MaintenanceReminderOccurrence::query()->sole();

        $this->assertEqualsCanonicalizing(
            [$occurrence->id],
            MaintenanceReminderOccurrence::query()->ownedBy($owner)->pluck('id')->all(),
        );
        $this->assertSame([], MaintenanceReminderOccurrence::query()->ownedBy($other)->pluck('id')->all());

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.index', $vessel))
            ->assertOk()
            ->assertSee('In-app maintenance reminder.')
            ->assertSee('Reference date: 2026-08-20.')
            ->assertSee('No email, push, SMS, or safety alert was sent.')
            ->assertSee('Acknowledge reminder for Private calendar reminder');

        $this->actingAs($other)
            ->post(route('maintenance.reminders.acknowledge', $occurrence->id))
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('maintenance.reminders.acknowledge', (string) str()->ulid()))
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('maintenance.reminders.acknowledge', $occurrence->id))
            ->assertRedirect(route('vessels.maintenance.index', $vessel))
            ->assertSessionHas('status', 'Maintenance reminder acknowledged.');

        $occurrence->refresh();
        $this->assertSame('acknowledged', $occurrence->status);
        $this->assertNotNull($occurrence->acknowledged_at);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'maintenance_reminder.acknowledged',
            'target_type' => MaintenanceReminderOccurrence::class,
            'target_id' => $occurrence->id,
            'metadata' => null,
        ]);

        $this->actingAs($owner)
            ->post(route('maintenance.reminders.acknowledge', $occurrence->id))
            ->assertRedirect(route('vessels.maintenance.index', $vessel))
            ->assertSessionHas('status', 'Maintenance reminder was already inactive.');
        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('action', 'maintenance_reminder.acknowledged')
                ->where('target_id', $occurrence->id)
                ->count(),
        );
        $this->actingAs($owner)
            ->get(route('vessels.maintenance.index', $vessel))
            ->assertOk()
            ->assertDontSee('In-app maintenance reminder.');

        $dismissedOccurrence = $schedule->reminderOccurrences()->create([
            'reference_type' => 'calendar_days',
            'reference_value' => '2026-09-19',
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->actingAs($other)
            ->post(route('maintenance.reminders.dismiss', $dismissedOccurrence->id))
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('maintenance.reminders.dismiss', (string) str()->ulid()))
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('maintenance.reminders.dismiss', $dismissedOccurrence->id))
            ->assertRedirect(route('vessels.maintenance.index', $vessel))
            ->assertSessionHas('status', 'Maintenance reminder dismissed. No completion was recorded.');

        $dismissedOccurrence->refresh();
        $this->assertSame('dismissed', $dismissedOccurrence->status);
        $this->assertNotNull($dismissedOccurrence->dismissed_at);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'maintenance_reminder.dismissed',
            'target_type' => MaintenanceReminderOccurrence::class,
            'target_id' => $dismissedOccurrence->id,
            'metadata' => null,
        ]);
        $this->actingAs($owner)
            ->post(route('maintenance.reminders.dismiss', $dismissedOccurrence->id))
            ->assertSessionHas('status', 'Maintenance reminder was already inactive.');
        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('action', 'maintenance_reminder.dismissed')
                ->where('target_id', $dismissedOccurrence->id)
                ->count(),
        );

        $archivedOccurrence = $schedule->reminderOccurrences()->create([
            'reference_type' => 'calendar_days',
            'reference_value' => '2026-10-19',
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $this->actingAs($owner)->delete(route('vessels.destroy', $vessel))->assertRedirect();
        $this->actingAs($owner)
            ->post(route('maintenance.reminders.acknowledge', $archivedOccurrence->id))
            ->assertNotFound();

        $this->artisan('marine:maintenance-reminders:sync')->assertSuccessful();
        $archivedOccurrence->refresh();
        $this->assertSame('suppressed', $archivedOccurrence->status);
        $this->assertNotNull($archivedOccurrence->suppressed_at);
    }

    public function test_sync_suppresses_reopens_and_supersedes_occurrences_without_deleting_history(): void
    {
        $this->travelTo('2026-08-20 12:00:00 UTC');

        $owner = User::factory()->create(['timezone' => 'UTC']);
        $vessel = $owner->vessels()->create(['name' => 'Lifecycle Vessel']);
        $schedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Lifecycle reminder',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);

        $this->artisan('marine:maintenance-reminders:sync')->assertSuccessful();
        $occurrence = MaintenanceReminderOccurrence::query()->sole();

        $schedule->update([
            'reminder_enabled' => false,
            'reminder_lead_days' => null,
        ]);
        $this->artisan('marine:maintenance-reminders:sync')->assertSuccessful();

        $occurrence->refresh();
        $this->assertSame('suppressed', $occurrence->status);
        $this->assertNotNull($occurrence->suppressed_at);
        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('action', 'maintenance_reminder.suppressed')
                ->where('target_id', $occurrence->id)
                ->count(),
        );

        $schedule->update([
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
        ]);
        $this->artisan('marine:maintenance-reminders:sync')->assertSuccessful();

        $occurrence->refresh();
        $this->assertSame('open', $occurrence->status);
        $this->assertNull($occurrence->suppressed_at);
        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('action', 'maintenance_reminder.reopened')
                ->where('target_id', $occurrence->id)
                ->count(),
        );

        $schedule->completions()->create([
            'completed_on' => '2026-08-20',
        ]);
        $this->artisan('marine:maintenance-reminders:sync')->assertSuccessful();
        $this->artisan('marine:maintenance-reminders:sync')->assertSuccessful();

        $occurrence->refresh();
        $this->assertSame('superseded', $occurrence->status);
        $this->assertNotNull($occurrence->superseded_at);
        $this->assertDatabaseCount('maintenance_reminder_occurrences', 1);
        $this->assertSame(
            1,
            AuditEvent::query()
                ->where('action', 'maintenance_reminder.superseded')
                ->where('target_id', $occurrence->id)
                ->count(),
        );
    }

    public function test_owner_can_view_fixed_size_paginated_history_without_cross_user_or_archived_vessel_disclosure(): void
    {
        $this->travelTo('2026-08-20 12:00:00 UTC');

        $owner = User::factory()->create(['timezone' => 'America/Vancouver']);
        $other = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'History Vessel']);
        $schedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Owner lifecycle schedule',
            'basis' => 'meter_hours',
            'interval_hours' => '10.0',
            'baseline_hours' => '1000.0',
            'reminder_enabled' => true,
            'reminder_lead_hours' => '0.0',
            'status' => 'active',
        ]);
        $foreignVessel = $other->vessels()->create(['name' => 'Foreign History Vessel']);
        $foreignSchedule = $foreignVessel->maintenanceSchedules()->create([
            'name' => 'Foreign private schedule',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);
        $foreignSchedule->reminderOccurrences()->create([
            'reference_type' => 'calendar_days',
            'reference_value' => '2026-08-20',
            'status' => 'dismissed',
            'opened_at' => now(),
            'dismissed_at' => now(),
        ]);

        $statuses = ['open', 'acknowledged', 'dismissed', 'suppressed', 'superseded'];
        for ($index = 0; $index < 26; $index++) {
            $status = $statuses[$index % count($statuses)];
            $openedAt = now()->subMinutes(25 - $index);
            $attributes = [
                'reference_type' => 'meter_hours',
                'reference_value' => sprintf('%d.0', 1000 + $index),
                'status' => $status,
                'opened_at' => $openedAt,
            ];

            if ($status !== 'open') {
                $attributes[$status.'_at'] = $openedAt->copy()->addMinute();
            }

            $schedule->reminderOccurrences()->create($attributes);
        }

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', $vessel))
            ->assertOk()
            ->assertSee('Reminder history · History Vessel')
            ->assertSee('Page 1 of 2')
            ->assertSee('1025.0 h')
            ->assertDontSee('1000.0 h')
            ->assertSee('2026-08-20 05:00 · UTC-07:00 · America/Vancouver')
            ->assertSee('Acknowledged')
            ->assertSee('Dismissed')
            ->assertSee('Suppressed')
            ->assertSee('Superseded')
            ->assertSee('Deletion and export are unavailable until retention rules are approved.')
            ->assertDontSee('Foreign private schedule');

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', ['vessel' => $vessel, 'page' => 2]))
            ->assertOk()
            ->assertSee('Page 2 of 2')
            ->assertSee('1000.0 h')
            ->assertDontSee('1025.0 h')
            ->assertDontSee('Foreign private schedule');

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', $foreignVessel))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', (string) str()->ulid()))
            ->assertNotFound();

        $this->actingAs($owner)->delete(route('vessels.destroy', $vessel))->assertRedirect();
        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', $vessel->id))
            ->assertNotFound();
    }

    public function test_owner_can_strictly_filter_history_with_query_preserving_pagination(): void
    {
        $this->travelTo('2026-08-20 12:00:00 UTC');

        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Filter History Vessel']);
        $meterSchedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Dismissed meter history',
            'basis' => 'meter_hours',
            'interval_hours' => '10.0',
            'baseline_hours' => '1000.0',
            'reminder_enabled' => true,
            'reminder_lead_hours' => '0.0',
            'status' => 'active',
        ]);
        $calendarSchedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Acknowledged calendar history',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);
        $annualSchedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Suppressed annual history',
            'basis' => 'annual_date',
            'annual_month' => 8,
            'annual_day' => 20,
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);
        $otherVesselSchedule = $owner->vessels()
            ->create(['name' => 'Other owned history vessel'])
            ->maintenanceSchedules()
            ->create([
                'name' => 'Other vessel private history',
                'basis' => 'meter_hours',
                'interval_hours' => '10.0',
                'baseline_hours' => '1000.0',
                'reminder_enabled' => true,
                'reminder_lead_hours' => '0.0',
                'status' => 'active',
            ]);
        $otherVesselSchedule->reminderOccurrences()->create([
            'reference_type' => 'meter_hours',
            'reference_value' => '9999.0',
            'status' => 'dismissed',
            'opened_at' => now()->addMinutes(5),
            'dismissed_at' => now()->addMinutes(6),
        ]);

        for ($index = 0; $index < 26; $index++) {
            $openedAt = now()->subMinutes($index);
            $meterSchedule->reminderOccurrences()->create([
                'reference_type' => 'meter_hours',
                'reference_value' => sprintf('%d.0', 2000 + $index),
                'status' => 'dismissed',
                'opened_at' => $openedAt,
                'dismissed_at' => $openedAt->copy()->addMinute(),
            ]);
        }

        $calendarSchedule->reminderOccurrences()->create([
            'reference_type' => 'calendar_days',
            'reference_value' => '2026-08-20',
            'status' => 'acknowledged',
            'opened_at' => now()->addMinute(),
            'acknowledged_at' => now()->addMinutes(2),
        ]);
        $annualSchedule->reminderOccurrences()->create([
            'reference_type' => 'annual_date',
            'reference_value' => '2026-08-20',
            'status' => 'suppressed',
            'opened_at' => now()->addMinutes(3),
            'suppressed_at' => now()->addMinutes(4),
        ]);

        $filtered = $this->actingAs($owner)->get(route('vessels.maintenance.reminders.history', [
            'vessel' => $vessel,
            'state' => 'dismissed',
            'reference_type' => 'meter_hours',
        ]));

        $filtered
            ->assertOk()
            ->assertSee('All records by lifecycle state')
            ->assertSee('Open: 0')
            ->assertSee('Acknowledged: 1')
            ->assertSee('Dismissed: 26')
            ->assertSee('Suppressed: 1')
            ->assertSee('Superseded: 0')
            ->assertSee('All records by reference type')
            ->assertSee('Calendar-day: 1')
            ->assertSee('Annual-date: 1')
            ->assertSee('Meter-hour: 26')
            ->assertSee('26 matching lifecycle records.')
            ->assertSee('Page 1 of 2')
            ->assertSee('Dismissed meter history')
            ->assertDontSee('Acknowledged calendar history')
            ->assertDontSee('Suppressed annual history')
            ->assertDontSee('Other vessel private history')
            ->assertSee('Active filters:')
            ->assertSee('state Dismissed; reference Meter-hour.')
            ->assertSee(route('vessels.maintenance.reminders.history', [
                'vessel' => $vessel,
                'state' => 'acknowledged',
            ]), false)
            ->assertSee(route('vessels.maintenance.reminders.history', [
                'vessel' => $vessel,
                'reference_type' => 'annual_date',
            ]), false);
        $this->assertStringContainsString('state=dismissed', $filtered->getContent());
        $this->assertStringContainsString('reference_type=meter_hours', $filtered->getContent());

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', [
                'vessel' => $vessel,
                'state' => 'dismissed',
                'reference_type' => 'meter_hours',
                'page' => 2,
            ]))
            ->assertOk()
            ->assertSee('Page 2 of 2')
            ->assertSee('2025.0 h')
            ->assertDontSee('Acknowledged calendar history');

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', [
                'vessel' => $vessel,
                'state' => 'acknowledged',
            ]))
            ->assertOk()
            ->assertSee('Acknowledged calendar history')
            ->assertDontSee('Dismissed meter history');

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', [
                'vessel' => $vessel,
                'reference_type' => 'annual_date',
            ]))
            ->assertOk()
            ->assertSee('Suppressed annual history')
            ->assertDontSee('Acknowledged calendar history');

        foreach (['open', 'acknowledged', 'dismissed', 'suppressed', 'superseded'] as $state) {
            $this->actingAs($owner)
                ->get(route('vessels.maintenance.reminders.history', [
                    'vessel' => $vessel,
                    'state' => $state,
                ]))
                ->assertOk();
        }

        foreach (['calendar_days', 'annual_date', 'meter_hours'] as $referenceType) {
            $this->actingAs($owner)
                ->get(route('vessels.maintenance.reminders.history', [
                    'vessel' => $vessel,
                    'reference_type' => $referenceType,
                ]))
                ->assertOk();
        }

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', [
                'vessel' => $vessel,
                'state' => 'unknown',
            ]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', [
                'vessel' => $vessel,
                'reference_type' => 'unknown',
            ]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', $vessel).'?state%5B%5D=open')
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('vessels.maintenance.reminders.history', $vessel).'?reference_type%5B%5D=meter_hours')
            ->assertNotFound();
    }

    public function test_owner_has_a_fixed_size_cross_vessel_open_inbox_and_dashboard_count(): void
    {
        $this->travelTo('2026-08-20 12:00:00 UTC');

        $owner = User::factory()->create(['timezone' => 'America/Vancouver']);
        $other = User::factory()->create();
        $firstVessel = $owner->vessels()->create(['name' => 'Inbox Vessel One']);
        $secondVessel = $owner->vessels()->create(['name' => 'Inbox Vessel Two']);
        $emptyVessel = $owner->vessels()->create(['name' => 'No open reminders vessel']);
        $firstSchedule = $firstVessel->maintenanceSchedules()->create([
            'name' => 'Owner inbox schedule',
            'basis' => 'meter_hours',
            'interval_hours' => '10.0',
            'baseline_hours' => '1000.0',
            'reminder_enabled' => true,
            'reminder_lead_hours' => '0.0',
            'status' => 'active',
        ]);
        $secondSchedule = $secondVessel->maintenanceSchedules()->create([
            'name' => 'Second vessel schedule',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);

        $openOccurrences = collect();
        for ($index = 0; $index < 27; $index++) {
            $schedule = $index === 26 ? $secondSchedule : $firstSchedule;
            $referenceType = $schedule->is($secondSchedule) ? 'calendar_days' : 'meter_hours';
            $openOccurrences->push($schedule->reminderOccurrences()->create([
                'reference_type' => $referenceType,
                'reference_value' => $referenceType === 'calendar_days'
                    ? '2026-08-20'
                    : sprintf('%d.0', 1000 + $index),
                'status' => 'open',
                'opened_at' => now()->subMinutes(27 - $index),
            ]));
        }

        $firstSchedule->reminderOccurrences()->create([
            'reference_type' => 'calendar_days',
            'reference_value' => '2026-08-19',
            'status' => 'dismissed',
            'opened_at' => now()->subDay(),
            'dismissed_at' => now(),
        ]);

        $archivedVessel = $owner->vessels()->create(['name' => 'Archived hidden vessel']);
        $archivedSchedule = $archivedVessel->maintenanceSchedules()->create([
            'name' => 'Archived hidden schedule',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);
        $archivedSchedule->reminderOccurrences()->create([
            'reference_type' => 'calendar_days',
            'reference_value' => '2026-08-20',
            'status' => 'open',
            'opened_at' => now(),
        ]);
        $archivedVessel->update(['status' => 'archived']);
        $archivedVessel->delete();

        $inactiveVessel = $owner->vessels()->create([
            'name' => 'Inactive hidden vessel',
            'status' => 'archived',
        ]);
        $inactiveSchedule = $inactiveVessel->maintenanceSchedules()->create([
            'name' => 'Inactive hidden schedule',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);
        $inactiveSchedule->reminderOccurrences()->create([
            'reference_type' => 'calendar_days',
            'reference_value' => '2026-08-20',
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $foreignVessel = $other->vessels()->create(['name' => 'Foreign hidden vessel']);
        $foreignSchedule = $foreignVessel->maintenanceSchedules()->create([
            'name' => 'Foreign hidden schedule',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-07-21',
            'reminder_enabled' => true,
            'reminder_lead_days' => 0,
            'status' => 'active',
        ]);
        $foreignSchedule->reminderOccurrences()->create([
            'reference_type' => 'calendar_days',
            'reference_value' => '2026-08-20',
            'status' => 'open',
            'opened_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Open maintenance reminders')
            ->assertSee('27 open across active vessels.')
            ->assertSee('View all Calendar-day open maintenance reminders: 1')
            ->assertSee('View all Annual-date open maintenance reminders: 0')
            ->assertSee('View all Meter-hour open maintenance reminders: 26')
            ->assertSee(route('maintenance.reminders.index', ['reference_type' => 'calendar_days']))
            ->assertSee(route('maintenance.reminders.index', ['reference_type' => 'annual_date']))
            ->assertSee(route('maintenance.reminders.index', ['reference_type' => 'meter_hours']))
            ->assertSee('View 26 open reminders')
            ->assertSee('View 1 open reminder')
            ->assertSee('View 0 open reminders')
            ->assertSee('Calendar-day: 0')
            ->assertSee('Annual-date: 0')
            ->assertSee('Meter-hour: 26')
            ->assertSee('Calendar-day: 1')
            ->assertSee('View Meter-hour open maintenance reminders for Inbox Vessel One: 26')
            ->assertSee('View Calendar-day open maintenance reminders for Inbox Vessel Two: 1')
            ->assertSee('View Annual-date open maintenance reminders for No open reminders vessel: 0')
            ->assertSee(route('maintenance.reminders.index', [
                'vessel' => $firstVessel->id,
                'reference_type' => 'meter_hours',
            ]))
            ->assertSee(route('maintenance.reminders.index', [
                'vessel' => $secondVessel->id,
                'reference_type' => 'calendar_days',
            ]))
            ->assertSee(route('maintenance.reminders.index', [
                'vessel' => $emptyVessel->id,
                'reference_type' => 'annual_date',
            ]))
            ->assertSee('vessel='.$firstVessel->id, false)
            ->assertSee('vessel='.$secondVessel->id, false)
            ->assertSee('vessel='.$emptyVessel->id, false)
            ->assertDontSee('Foreign hidden vessel')
            ->assertDontSee('Archived hidden vessel')
            ->assertDontSee('Inactive hidden vessel');

        $this->actingAs($owner)
            ->get(route('vessels.index'))
            ->assertOk()
            ->assertSee('View 26 open reminders')
            ->assertSee('View 1 open reminder')
            ->assertSee('View 0 open reminders')
            ->assertSee('Calendar-day: 0')
            ->assertSee('Annual-date: 0')
            ->assertSee('Meter-hour: 26')
            ->assertSee('Calendar-day: 1')
            ->assertSee('View Meter-hour open maintenance reminders for Inbox Vessel One: 26')
            ->assertSee('View Calendar-day open maintenance reminders for Inbox Vessel Two: 1')
            ->assertSee('View Annual-date open maintenance reminders for No open reminders vessel: 0')
            ->assertSee(route('maintenance.reminders.index', [
                'vessel' => $firstVessel->id,
                'reference_type' => 'meter_hours',
            ]))
            ->assertSee(route('maintenance.reminders.index', [
                'vessel' => $secondVessel->id,
                'reference_type' => 'calendar_days',
            ]))
            ->assertSee(route('maintenance.reminders.index', [
                'vessel' => $emptyVessel->id,
                'reference_type' => 'annual_date',
            ]))
            ->assertSee('vessel='.$firstVessel->id, false)
            ->assertSee('vessel='.$secondVessel->id, false)
            ->assertSee('vessel='.$emptyVessel->id, false)
            ->assertDontSee('Foreign hidden vessel')
            ->assertDontSee('Inactive hidden vessel')
            ->assertDontSee('vessel='.$archivedVessel->id, false);

        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index'))
            ->assertOk()
            ->assertSee('27 matching of 27 open reminders across active vessels.')
            ->assertSee('View all Calendar-day open maintenance reminders: 1')
            ->assertSee('View all Annual-date open maintenance reminders: 0')
            ->assertSee('View all Meter-hour open maintenance reminders: 26')
            ->assertSee(route('maintenance.reminders.index', ['reference_type' => 'calendar_days']))
            ->assertSee(route('maintenance.reminders.index', ['reference_type' => 'annual_date']))
            ->assertSee(route('maintenance.reminders.index', ['reference_type' => 'meter_hours']))
            ->assertSee('Page 1 of 2')
            ->assertSee('1000.0 h')
            ->assertSee('1024.0 h')
            ->assertDontSee('1025.0 h')
            ->assertSee('Inbox Vessel One')
            ->assertDontSee('Foreign hidden schedule')
            ->assertDontSee('Archived hidden schedule')
            ->assertDontSee('Inactive hidden schedule')
            ->assertSee('No email, push, SMS, webhook, escalation, or safety alert was sent.');

        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Page 2 of 2')
            ->assertSee('1025.0 h')
            ->assertSee('Reference date: 2026-08-20.')
            ->assertSee('Inbox Vessel One')
            ->assertSee('Inbox Vessel Two')
            ->assertDontSee('1000.0 h')
            ->assertDontSee('Foreign hidden schedule');

        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index', [
                'vessel' => $firstVessel->id,
                'reference_type' => 'meter_hours',
            ]))
            ->assertOk()
            ->assertSee('26 matching of 27 open reminders across active vessels.')
            ->assertSee('vessel '.$firstVessel->name.'; reference Meter-hour.')
            ->assertSee('Page 1 of 2')
            ->assertSee('reference_type=meter_hours', false)
            ->assertSee('vessel='.$firstVessel->id, false)
            ->assertDontSee('Second vessel schedule');

        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index', [
                'vessel' => $secondVessel->id,
                'reference_type' => 'calendar_days',
            ]))
            ->assertOk()
            ->assertSee('1 matching of 27 open reminders across active vessels.')
            ->assertSee('vessel '.$secondVessel->name.'; reference Calendar-day.')
            ->assertSee('Reference date: 2026-08-20.')
            ->assertDontSee('Owner inbox schedule');

        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index', ['reference_type' => 'annual_date']))
            ->assertOk()
            ->assertSee('0 matching of 27 open reminders across active vessels.')
            ->assertSee('No open in-app maintenance reminders match the selected filters.');

        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index', ['vessel' => $foreignVessel->id]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index', ['vessel' => $archivedVessel->id]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index', ['vessel' => $inactiveVessel->id]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index', ['vessel' => (string) str()->ulid()]))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index', ['reference_type' => 'unknown']))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('maintenance.reminders.index').'?reference_type%5B0%5D=meter_hours')
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('maintenance.reminders.acknowledge', $openOccurrences[0]->id), [
                'return_to' => 'inbox',
                'return_vessel' => $firstVessel->id,
                'return_reference_type' => 'meter_hours',
            ])
            ->assertRedirect(route('maintenance.reminders.index', [
                'vessel' => $firstVessel->id,
                'reference_type' => 'meter_hours',
            ]))
            ->assertSessionHas('status', 'Maintenance reminder acknowledged.');

        $this->actingAs($owner)
            ->post(route('maintenance.reminders.dismiss', $openOccurrences[1]->id), [
                'return_to' => 'inbox',
            ])
            ->assertRedirect(route('maintenance.reminders.index'))
            ->assertSessionHas('status', 'Maintenance reminder dismissed. No completion was recorded.');

        $this->actingAs($owner)
            ->post(route('maintenance.reminders.acknowledge', $openOccurrences[2]->id), [
                'return_to' => 'https://example.test/not-allowed',
            ])
            ->assertRedirect(route('vessels.maintenance.index', $firstVessel));

        $this->actingAs($owner)
            ->post(route('maintenance.reminders.acknowledge', $openOccurrences[3]->id), [
                'return_to' => 'inbox',
                'return_vessel' => $foreignVessel->id,
                'return_reference_type' => 'unknown',
            ])
            ->assertRedirect(route('maintenance.reminders.index'));

        $this->assertSame('acknowledged', $openOccurrences[0]->fresh()->status);
        $this->assertSame('dismissed', $openOccurrences[1]->fresh()->status);
        $this->assertSame('acknowledged', $openOccurrences[2]->fresh()->status);
        $this->assertSame('acknowledged', $openOccurrences[3]->fresh()->status);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\MaintenanceCompletion;
use App\Models\MaintenanceCompletionReversal;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MaintenanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_view_update_and_pause_each_schedule_basis(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Maintenance Vessel']);
        $equipment = $vessel->equipmentItems()->create([
            'category' => 'propulsion',
            'name' => 'Main engine',
            'status' => 'active',
        ]);

        foreach ([
            ['name' => 'Engine service', 'basis' => 'meter_hours', 'interval_hours' => '100.0', 'baseline_hours' => '1250.0'],
            ['name' => 'Battery inspection', 'basis' => 'calendar_days', 'interval_days' => '90', 'baseline_on' => '2026-08-01'],
            ['name' => 'Spring commissioning', 'basis' => 'annual_date', 'annual_month' => '3', 'annual_day' => '15'],
        ] as $details) {
            $this->actingAs($owner)
                ->post(route('vessels.maintenance.store', $vessel), [
                    ...$details,
                    'equipment_item_id' => $equipment->id,
                    'notes' => 'Owner-maintained reference.',
                ])
                ->assertRedirect(route('vessels.maintenance.index', $vessel))
                ->assertSessionHas('status', 'Maintenance schedule added.');
        }

        $engineSchedule = MaintenanceSchedule::query()->where('basis', 'meter_hours')->sole();
        $this->assertTrue(Gate::forUser($owner)->allows('view', $engineSchedule));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $engineSchedule));
        $this->actingAs($owner)
            ->get(route('vessels.maintenance.index', $vessel))
            ->assertOk()
            ->assertSee('Recurs every 100.0 meter h from 1250.0 h.')
            ->assertSee('Recurs every 90 calendar days from 2026-08-01.')
            ->assertSee('Recurs annually on 03-15')
            ->assertSee('do not replace manufacturer instructions');

        $this->actingAs($owner)
            ->patch(route('maintenance.update', $engineSchedule->id), [
                'name' => 'Paused engine service',
                'basis' => 'calendar_days',
                'interval_days' => '180',
                'baseline_on' => '2026-08-20',
                'status' => 'paused',
            ])
            ->assertRedirect(route('vessels.maintenance.index', $vessel))
            ->assertSessionHas('status', 'Maintenance schedule updated.');

        $engineSchedule->refresh();
        $this->assertSame('calendar_days', $engineSchedule->basis);
        $this->assertSame(180, $engineSchedule->interval_days);
        $this->assertNull($engineSchedule->interval_hours);
        $this->assertNull($engineSchedule->baseline_hours);
        $this->assertSame('2026-08-20', $engineSchedule->baseline_on?->format('Y-m-d'));
        $this->assertSame('paused', $engineSchedule->status);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'maintenance_schedule.updated',
            'target_id' => $engineSchedule->id,
            'metadata' => null,
        ]);
    }

    public function test_schedule_routes_scope_and_equipment_selection_preserve_owner_boundaries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Owner Vessel']);
        $foreignVessel = $other->vessels()->create(['name' => 'Foreign Vessel']);
        $foreignEquipment = $foreignVessel->equipmentItems()->create([
            'category' => 'generator',
            'name' => 'Foreign generator',
            'status' => 'active',
        ]);
        $schedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Owner schedule',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-08-01',
            'status' => 'active',
        ]);

        $this->assertEqualsCanonicalizing(
            [$schedule->id],
            MaintenanceSchedule::query()->ownedBy($owner)->pluck('id')->all(),
        );
        $this->assertFalse(Gate::forUser($other)->allows('view', $schedule));

        $this->actingAs($other)
            ->get(route('vessels.maintenance.index', $vessel))
            ->assertForbidden();
        $this->actingAs($other)
            ->get(route('maintenance.edit', $schedule->id))
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('vessels.maintenance.store', $vessel), [
                'name' => 'Foreign equipment attempt',
                'basis' => 'calendar_days',
                'interval_days' => 30,
                'baseline_on' => '2026-08-01',
                'equipment_item_id' => $foreignEquipment->id,
            ])
            ->assertSessionHasErrors('equipment_item_id');
        $this->actingAs($owner)
            ->get(route('maintenance.edit', (string) str()->ulid()))
            ->assertNotFound();

        $this->actingAs($owner)->delete(route('vessels.destroy', $vessel))->assertRedirect();
        $this->assertFalse(MaintenanceSchedule::query()->ownedBy($owner)->whereKey($schedule->id)->exists());
        $this->actingAs($owner)->get(route('maintenance.edit', $schedule->id))->assertNotFound();
    }

    public function test_owner_can_record_private_completion_history_with_meter_boundaries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Completion Vessel']);
        $schedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Engine service',
            'basis' => 'meter_hours',
            'interval_hours' => '100.0',
            'baseline_hours' => '1000.0',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('maintenance.completions.store', $schedule->id), [
                'completed_on' => '2024-02-29',
                'meter_hours' => '1100.0',
                'notes' => 'Owner-recorded completion.',
            ])
            ->assertRedirect(route('vessels.maintenance.index', $vessel))
            ->assertSessionHas('status', 'Maintenance completion recorded.');

        $completion = $schedule->completions()->sole();
        $this->assertSame('2024-02-29', $completion->completed_on?->format('Y-m-d'));
        $this->assertSame('1100.0', $completion->meter_hours);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'maintenance_completion.created',
            'target_id' => $completion->id,
            'metadata' => null,
        ]);
        $this->actingAs($owner)
            ->get(route('vessels.maintenance.index', $vessel))
            ->assertOk()
            ->assertSee('2024-02-29')
            ->assertSee('1100.0 h')
            ->assertSee('Owner-recorded completion.');

        $this->actingAs($owner)
            ->post(route('maintenance.completions.store', $schedule->id), [
                'completed_on' => '2026-02-29',
                'meter_hours' => '1000000000.0',
            ])
            ->assertSessionHasErrors(['completed_on', 'meter_hours']);
        $this->actingAs($owner)
            ->post(route('maintenance.completions.store', $schedule->id), [
                'completed_on' => '2026-08-21',
            ])
            ->assertSessionHasErrors('meter_hours');
        $this->actingAs($other)
            ->post(route('maintenance.completions.store', $schedule->id), [
                'completed_on' => '2026-08-21',
                'meter_hours' => '1200.0',
            ])
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('maintenance.completions.store', (string) str()->ulid()), [
                'completed_on' => '2026-08-21',
                'meter_hours' => '1200.0',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('maintenance_completions', 1);
    }

    public function test_owner_can_append_one_private_reversal_without_rewriting_completion(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Correction Vessel']);
        $schedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Engine service',
            'basis' => 'meter_hours',
            'interval_hours' => '100.0',
            'baseline_hours' => '1000.0',
            'status' => 'active',
        ]);
        $completion = $schedule->completions()->create([
            'completed_on' => '2026-08-20',
            'meter_hours' => '1100.0',
            'notes' => 'Original completion remains.',
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('reverse', $completion));
        $this->assertFalse(Gate::forUser($other)->allows('reverse', $completion));

        $this->actingAs($owner)
            ->post(route('maintenance.completions.reversal.store', $completion->id), [
                'reason' => 'Meter reading copied incorrectly.',
            ])
            ->assertRedirect(route('vessels.maintenance.index', $vessel))
            ->assertSessionHas('status', 'Maintenance completion reversed. Record a corrected completion separately if needed.');

        $reversal = $completion->reversal()->sole();
        $this->assertSame('Meter reading copied incorrectly.', $reversal->reason);
        $this->assertSame('2026-08-20', $completion->fresh()->completed_on?->format('Y-m-d'));
        $this->assertSame('1100.0', $completion->fresh()->meter_hours);
        $this->assertSame('Original completion remains.', $completion->fresh()->notes);
        $this->assertEqualsCanonicalizing(
            [$reversal->id],
            MaintenanceCompletionReversal::query()->ownedBy($owner)->pluck('id')->all(),
        );
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'maintenance_completion.reversed',
            'target_type' => MaintenanceCompletionReversal::class,
            'target_id' => $reversal->id,
            'metadata' => null,
        ]);
        $this->actingAs($owner)
            ->get(route('vessels.maintenance.index', $vessel))
            ->assertOk()
            ->assertSee('Reversed. Original record kept.')
            ->assertSee('Meter reading copied incorrectly.')
            ->assertSee('Record a corrected completion separately.');

        $this->actingAs($owner)
            ->post(route('maintenance.completions.reversal.store', $completion->id), [
                'reason' => 'A conflicting second reversal.',
            ])
            ->assertSessionHasErrors('reason');
        $this->actingAs($other)
            ->post(route('maintenance.completions.reversal.store', $completion->id), [
                'reason' => 'Foreign reversal attempt.',
            ])
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('maintenance.completions.reversal.store', (string) str()->ulid()), [
                'reason' => 'Missing completion attempt.',
            ])
            ->assertNotFound();

        $boundaryCompletion = $schedule->completions()->create([
            'completed_on' => '2026-08-21',
            'meter_hours' => '1200.0',
        ]);
        $this->actingAs($owner)
            ->post(route('maintenance.completions.reversal.store', $boundaryCompletion->id), [
                'reason' => '   ',
            ])
            ->assertSessionHasErrors('reason');
        $this->actingAs($owner)
            ->post(route('maintenance.completions.reversal.store', $boundaryCompletion->id), [
                'reason' => str_repeat('R', 501),
            ])
            ->assertSessionHasErrors('reason');
        $this->actingAs($owner)
            ->post(route('maintenance.completions.reversal.store', $boundaryCompletion->id), [
                'reason' => str_repeat('R', 500),
            ])
            ->assertRedirect(route('vessels.maintenance.index', $vessel))
            ->assertSessionDoesntHaveErrors();

        $archivedCompletion = $schedule->completions()->create([
            'completed_on' => '2026-08-22',
            'meter_hours' => '1300.0',
        ]);
        $this->actingAs($owner)->delete(route('vessels.destroy', $vessel))->assertRedirect();
        $this->actingAs($owner)
            ->post(route('maintenance.completions.reversal.store', $archivedCompletion->id), [
                'reason' => 'Archived vessel attempt.',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('maintenance_completions', 3);
        $this->assertDatabaseCount('maintenance_completion_reversals', 2);
        $this->assertSame(2, MaintenanceCompletion::query()->whereHas('reversal')->count());
        $this->assertSame(2, AuditEvent::query()->where('action', 'maintenance_completion.reversed')->count());
    }

    public function test_owner_can_record_and_clear_bounded_source_provenance_without_audit_values(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Reference Vessel']);

        $this->actingAs($owner)
            ->post(route('vessels.maintenance.store', $vessel), [
                'name' => 'Manufacturer-backed service',
                'basis' => 'calendar_days',
                'interval_days' => '90',
                'baseline_on' => '2026-08-01',
                'source_kind' => 'manufacturer_manual',
                'source_title' => 'Engine service manual',
                'source_reference' => 'MAN-100',
                'source_revision' => 'Rev 4',
                'source_section' => 'Section 8.2',
                'source_published_on' => '2024-02-29',
                'source_url' => 'https://manufacturer.example/manuals/engine-service.pdf',
            ])
            ->assertRedirect(route('vessels.maintenance.index', $vessel))
            ->assertSessionDoesntHaveErrors();

        $schedule = MaintenanceSchedule::query()->sole();
        $this->assertSame('manufacturer_manual', $schedule->source_kind);
        $this->assertSame('Engine service manual', $schedule->source_title);
        $this->assertSame('MAN-100', $schedule->source_reference);
        $this->assertSame('Rev 4', $schedule->source_revision);
        $this->assertSame('Section 8.2', $schedule->source_section);
        $this->assertSame('2024-02-29', $schedule->source_published_on?->format('Y-m-d'));
        $this->assertSame('https://manufacturer.example/manuals/engine-service.pdf', $schedule->source_url);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'maintenance_schedule.created',
            'target_id' => $schedule->id,
            'metadata' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.index', $vessel))
            ->assertOk()
            ->assertSee('Manufacturer manual')
            ->assertSee('Engine service manual')
            ->assertSee('MAN-100')
            ->assertSee('revision Rev 4')
            ->assertSee('Section 8.2')
            ->assertSee('published 2024-02-29')
            ->assertSee('This owner-entered citation is not authenticated')
            ->assertSee('href="https://manufacturer.example/manuals/engine-service.pdf"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('referrerpolicy="no-referrer"', false);

        $this->actingAs($owner)
            ->patch(route('maintenance.update', $schedule->id), [
                'name' => 'Reference cleared',
                'basis' => 'calendar_days',
                'interval_days' => '90',
                'baseline_on' => '2026-08-01',
                'status' => 'active',
            ])
            ->assertRedirect(route('vessels.maintenance.index', $vessel))
            ->assertSessionDoesntHaveErrors();

        $schedule->refresh();
        $this->assertNull($schedule->source_kind);
        $this->assertNull($schedule->source_title);
        $this->assertNull($schedule->source_reference);
        $this->assertNull($schedule->source_revision);
        $this->assertNull($schedule->source_section);
        $this->assertNull($schedule->source_published_on);
        $this->assertNull($schedule->source_url);

        $urlPrefix = 'https://docs.example/';
        $maximumUrl = $urlPrefix.str_repeat('a', 2048 - strlen($urlPrefix));
        $this->actingAs($owner)
            ->post(route('vessels.maintenance.store', $vessel), [
                'name' => 'Exact source boundaries',
                'basis' => 'annual_date',
                'annual_month' => '3',
                'annual_day' => '15',
                'source_kind' => 'manufacturer_technical_notice',
                'source_title' => str_repeat('T', 160),
                'source_reference' => str_repeat('N', 160),
                'source_revision' => str_repeat('R', 80),
                'source_section' => str_repeat('S', 120),
                'source_published_on' => '2024-02-29',
                'source_url' => $maximumUrl,
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        foreach ([
            [['source_kind' => 'unverified', 'source_title' => 'Unknown kind'], 'source_kind'],
            [['source_title' => 'Title without type'], 'source_kind'],
            [['source_kind' => 'manufacturer_manual'], 'source_title'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => '   '], 'source_title'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => str_repeat('T', 161)], 'source_title'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => 'Manual', 'source_reference' => str_repeat('N', 161)], 'source_reference'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => 'Manual', 'source_revision' => str_repeat('R', 81)], 'source_revision'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => 'Manual', 'source_section' => str_repeat('S', 121)], 'source_section'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => 'Manual', 'source_published_on' => '2026-02-29'], 'source_published_on'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => 'Manual', 'source_url' => 'http://manufacturer.example/manual'], 'source_url'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => 'Manual', 'source_url' => 'https://manufacturer.example/manual?token=value'], 'source_url'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => 'Manual', 'source_url' => 'https://manufacturer.example/manual#section'], 'source_url'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => 'Manual', 'source_url' => 'https://user@manufacturer.example/manual'], 'source_url'],
            [['source_kind' => 'manufacturer_manual', 'source_title' => 'Manual', 'source_url' => $maximumUrl.'a'], 'source_url'],
        ] as [$sourceDetails, $field]) {
            $this->actingAs($owner)
                ->post(route('vessels.maintenance.store', $vessel), [
                    'name' => 'Rejected source reference',
                    'basis' => 'calendar_days',
                    'interval_days' => '30',
                    'baseline_on' => '2026-08-01',
                    ...$sourceDetails,
                ])
                ->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('maintenance_schedules', 2);
        $this->assertSame(
            3,
            AuditEvent::query()
                ->whereIn('action', ['maintenance_schedule.created', 'maintenance_schedule.updated'])
                ->whereNull('metadata')
                ->count(),
        );
    }

    public function test_owner_can_store_and_clear_bounded_reminder_preferences_without_delivery_claims(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Reminder Preference Vessel']);

        foreach ([
            [
                'name' => 'Due-boundary calendar preference',
                'basis' => 'calendar_days',
                'interval_days' => '30',
                'baseline_on' => '2026-08-01',
                'reminder_enabled' => '1',
                'reminder_lead_days' => '0',
            ],
            [
                'name' => 'Maximum annual preference',
                'basis' => 'annual_date',
                'annual_month' => '3',
                'annual_day' => '15',
                'reminder_enabled' => '1',
                'reminder_lead_days' => '3650',
            ],
            [
                'name' => 'Maximum meter preference',
                'basis' => 'meter_hours',
                'interval_hours' => '100.0',
                'baseline_hours' => '1000.0',
                'reminder_enabled' => '1',
                'reminder_lead_hours' => '999999999.9',
            ],
        ] as $details) {
            $this->actingAs($owner)
                ->post(route('vessels.maintenance.store', $vessel), $details)
                ->assertRedirect()
                ->assertSessionDoesntHaveErrors();
        }

        $calendar = MaintenanceSchedule::query()
            ->where('name', 'Due-boundary calendar preference')
            ->sole();
        $this->assertTrue($calendar->reminder_enabled);
        $this->assertSame(0, $calendar->reminder_lead_days);
        $this->assertNull($calendar->reminder_lead_hours);

        $meter = MaintenanceSchedule::query()
            ->where('name', 'Maximum meter preference')
            ->sole();
        $this->assertTrue($meter->reminder_enabled);
        $this->assertSame('999999999.9', $meter->reminder_lead_hours);
        $this->assertNull($meter->reminder_lead_days);

        $this->actingAs($owner)
            ->get(route('vessels.maintenance.index', $vessel))
            ->assertOk()
            ->assertSee('Stored reminder preference: 0 calendar days before due.')
            ->assertSee('Stored reminder preference: 3650 calendar days before due.')
            ->assertSee('Stored reminder preference: 999999999.9 meter h before due.')
            ->assertSee('Preference only — no reminder is scheduled or sent.');

        foreach ([
            [['basis' => 'calendar_days', 'interval_days' => '30', 'baseline_on' => '2026-08-01', 'reminder_enabled' => '1'], 'reminder_lead_days'],
            [['basis' => 'calendar_days', 'interval_days' => '30', 'baseline_on' => '2026-08-01', 'reminder_enabled' => '1', 'reminder_lead_days' => '-1'], 'reminder_lead_days'],
            [['basis' => 'calendar_days', 'interval_days' => '30', 'baseline_on' => '2026-08-01', 'reminder_enabled' => '1', 'reminder_lead_days' => '3651'], 'reminder_lead_days'],
            [['basis' => 'calendar_days', 'interval_days' => '30', 'baseline_on' => '2026-08-01', 'reminder_enabled' => '1', 'reminder_lead_days' => '1', 'reminder_lead_hours' => '1.0'], 'reminder_lead_hours'],
            [['basis' => 'meter_hours', 'interval_hours' => '100.0', 'baseline_hours' => '1000.0', 'reminder_enabled' => '1'], 'reminder_lead_hours'],
            [['basis' => 'meter_hours', 'interval_hours' => '100.0', 'baseline_hours' => '1000.0', 'reminder_enabled' => '1', 'reminder_lead_hours' => '-0.1'], 'reminder_lead_hours'],
            [['basis' => 'meter_hours', 'interval_hours' => '100.0', 'baseline_hours' => '1000.0', 'reminder_enabled' => '1', 'reminder_lead_hours' => '1000000000.0'], 'reminder_lead_hours'],
            [['basis' => 'meter_hours', 'interval_hours' => '100.0', 'baseline_hours' => '1000.0', 'reminder_enabled' => '1', 'reminder_lead_hours' => '1.23'], 'reminder_lead_hours'],
            [['basis' => 'meter_hours', 'interval_hours' => '100.0', 'baseline_hours' => '1000.0', 'reminder_enabled' => '1', 'reminder_lead_hours' => '1.0', 'reminder_lead_days' => '1'], 'reminder_lead_days'],
        ] as [$details, $field]) {
            $this->actingAs($owner)
                ->post(route('vessels.maintenance.store', $vessel), [
                    'name' => 'Rejected reminder preference',
                    ...$details,
                ])
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($owner)
            ->patch(route('maintenance.update', $meter->id), [
                'name' => 'Reminder preference disabled',
                'basis' => 'meter_hours',
                'interval_hours' => '100.0',
                'baseline_hours' => '1000.0',
                'reminder_enabled' => '0',
                'reminder_lead_days' => '12',
                'reminder_lead_hours' => '25.0',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $meter->refresh();
        $this->assertFalse($meter->reminder_enabled);
        $this->assertNull($meter->reminder_lead_days);
        $this->assertNull($meter->reminder_lead_hours);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'maintenance_schedule.updated',
            'target_id' => $meter->id,
            'metadata' => null,
        ]);
        $this->assertDatabaseCount('maintenance_schedules', 3);
    }

    public function test_schedule_validation_preserves_exact_recurrence_boundaries(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Boundary Vessel']);

        foreach ([
            ['name' => 'Minimum days', 'basis' => 'calendar_days', 'interval_days' => '1', 'baseline_on' => '2024-02-29'],
            ['name' => 'Maximum days', 'basis' => 'calendar_days', 'interval_days' => '3650', 'baseline_on' => '2026-08-21'],
            ['name' => 'Minimum hours', 'basis' => 'meter_hours', 'interval_hours' => '0.1', 'baseline_hours' => '0'],
            ['name' => 'Maximum hours', 'basis' => 'meter_hours', 'interval_hours' => '999999999.9', 'baseline_hours' => '999999999.9'],
            ['name' => 'Leap years only', 'basis' => 'annual_date', 'annual_month' => '2', 'annual_day' => '29', 'leap_day_policy' => 'leap_year_only'],
            ['name' => 'February rollover', 'basis' => 'annual_date', 'annual_month' => '2', 'annual_day' => '29', 'leap_day_policy' => 'february_28'],
            ['name' => 'March rollover', 'basis' => 'annual_date', 'annual_month' => '2', 'annual_day' => '29', 'leap_day_policy' => 'march_1'],
        ] as $details) {
            $this->actingAs($owner)
                ->post(route('vessels.maintenance.store', $vessel), $details)
                ->assertRedirect()
                ->assertSessionDoesntHaveErrors();
        }

        foreach ([
            [['name' => 'Zero days', 'basis' => 'calendar_days', 'interval_days' => '0', 'baseline_on' => '2026-08-21'], 'interval_days'],
            [['name' => 'Too many days', 'basis' => 'calendar_days', 'interval_days' => '3651', 'baseline_on' => '2026-08-21'], 'interval_days'],
            [['name' => 'Zero hours', 'basis' => 'meter_hours', 'interval_hours' => '0', 'baseline_hours' => '0'], 'interval_hours'],
            [['name' => 'Too many hours', 'basis' => 'meter_hours', 'interval_hours' => '1000000000.0', 'baseline_hours' => '0'], 'interval_hours'],
            [['name' => 'Too precise', 'basis' => 'meter_hours', 'interval_hours' => '1.23', 'baseline_hours' => '0'], 'interval_hours'],
            [['name' => 'Invalid annual date', 'basis' => 'annual_date', 'annual_month' => '2', 'annual_day' => '30'], 'annual_day'],
            [['name' => 'Missing leap rule', 'basis' => 'annual_date', 'annual_month' => '2', 'annual_day' => '29'], 'leap_day_policy'],
            [['name' => 'Unknown leap rule', 'basis' => 'annual_date', 'annual_month' => '2', 'annual_day' => '29', 'leap_day_policy' => 'automatic'], 'leap_day_policy'],
            [['name' => 'Rule on ordinary date', 'basis' => 'annual_date', 'annual_month' => '3', 'annual_day' => '15', 'leap_day_policy' => 'march_1'], 'leap_day_policy'],
            [['name' => 'Rule on calendar basis', 'basis' => 'calendar_days', 'interval_days' => '30', 'baseline_on' => '2026-08-21', 'leap_day_policy' => 'march_1'], 'leap_day_policy'],
        ] as [$details, $field]) {
            $this->actingAs($owner)
                ->post(route('vessels.maintenance.store', $vessel), $details)
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($owner)
            ->post(route('vessels.maintenance.store', $vessel), [
                'name' => 'Invalid baseline date',
                'basis' => 'calendar_days',
                'interval_days' => '30',
                'baseline_on' => '2026-02-29',
            ])
            ->assertSessionHasErrors('baseline_on');
        $this->actingAs($owner)
            ->post(route('vessels.maintenance.store', $vessel), [
                'name' => 'Over maximum baseline',
                'basis' => 'meter_hours',
                'interval_hours' => '100.0',
                'baseline_hours' => '1000000000.0',
            ])
            ->assertSessionHasErrors('baseline_hours');

        $marchRollover = MaintenanceSchedule::query()
            ->where('leap_day_policy', 'march_1')
            ->sole();
        $this->actingAs($owner)
            ->patch(route('maintenance.update', $marchRollover->id), [
                'name' => 'Ordinary annual date',
                'basis' => 'annual_date',
                'annual_month' => '3',
                'annual_day' => '15',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
        $this->assertNull($marchRollover->fresh()->leap_day_policy);

        $this->assertDatabaseCount('maintenance_schedules', 7);
    }
}

<?php

namespace Tests\Feature;

use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Support\MaintenanceDueState;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceDueStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_state_preserves_exact_boundaries_and_excludes_reversals(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Calendar Vessel']);
        $schedule = $vessel->maintenanceSchedules()->create([
            'name' => 'Calendar service',
            'basis' => 'calendar_days',
            'interval_days' => 20,
            'baseline_on' => '2026-08-01',
            'status' => 'active',
        ]);

        $this->assertSame('not_due', $this->evaluate($schedule, '2026-08-20')->status);
        $this->assertSame('due', $this->evaluate($schedule, '2026-08-21')->status);
        $this->assertSame('overdue', $this->evaluate($schedule, '2026-08-22')->status);

        $completion = $schedule->completions()->create(['completed_on' => '2026-08-20']);
        $state = $this->evaluate($schedule, '2026-08-22');
        $this->assertSame('not_due', $state->status);
        $this->assertSame('Next reference date: 2026-09-09.', $state->detail);

        $completion->reversal()->create(['reason' => 'Incorrect completion.']);
        $this->assertSame('overdue', $this->evaluate($schedule, '2026-08-22')->status);

        $schedule->completions()->create(['completed_on' => '2026-08-23']);
        $this->assertSame('unavailable', $this->evaluate($schedule, '2026-08-22')->status);
    }

    public function test_meter_state_uses_exact_tenths_and_excludes_reversals(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Meter Vessel']);
        $equipment = $vessel->equipmentItems()->create([
            'category' => 'propulsion',
            'name' => 'Main engine',
            'meter_hours' => '1099.9',
            'status' => 'active',
        ]);
        $schedule = $vessel->maintenanceSchedules()->create([
            'equipment_item_id' => $equipment->id,
            'name' => 'Engine service',
            'basis' => 'meter_hours',
            'interval_hours' => '100.0',
            'baseline_hours' => '1000.0',
            'status' => 'active',
        ]);

        $this->assertSame('not_due', $this->evaluate($schedule, '2026-08-21')->status);

        $equipment->update(['meter_hours' => '1100.0']);
        $this->assertSame('due', $this->evaluate($schedule, '2026-08-21')->status);

        $equipment->update(['meter_hours' => '1100.1']);
        $this->assertSame('overdue', $this->evaluate($schedule, '2026-08-21')->status);

        $completion = $schedule->completions()->create([
            'completed_on' => '2026-08-20',
            'meter_hours' => '1100.0',
        ]);
        $equipment->update(['meter_hours' => '1199.9']);
        $state = $this->evaluate($schedule, '2026-08-21');
        $this->assertSame('not_due', $state->status);
        $this->assertSame(
            'Next reference meter: 1200.0 h; recorded current meter: 1199.9 h.',
            $state->detail,
        );

        $equipment->update(['meter_hours' => '1200.0']);
        $this->assertSame('due', $this->evaluate($schedule, '2026-08-21')->status);

        $completion->reversal()->create(['reason' => 'Incorrect meter reading.']);
        $this->assertSame('overdue', $this->evaluate($schedule, '2026-08-21')->status);

        $equipment->update(['meter_hours' => '999.9']);
        $this->assertSame('unavailable', $this->evaluate($schedule, '2026-08-21')->status);
    }

    public function test_annual_state_uses_creation_completion_and_explicit_leap_day_rules(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Annual Vessel']);

        Carbon::setTestNow('2026-01-01 12:00:00 UTC');
        $regular = $vessel->maintenanceSchedules()->create([
            'name' => 'Spring service',
            'basis' => 'annual_date',
            'annual_month' => 3,
            'annual_day' => 15,
            'status' => 'active',
        ]);
        $february = $vessel->maintenanceSchedules()->create([
            'name' => 'February rollover',
            'basis' => 'annual_date',
            'annual_month' => 2,
            'annual_day' => 29,
            'leap_day_policy' => 'february_28',
            'status' => 'active',
        ]);
        $march = $vessel->maintenanceSchedules()->create([
            'name' => 'March rollover',
            'basis' => 'annual_date',
            'annual_month' => 2,
            'annual_day' => 29,
            'leap_day_policy' => 'march_1',
            'status' => 'active',
        ]);

        Carbon::setTestNow('2025-01-01 12:00:00 UTC');
        $leapYearOnly = $vessel->maintenanceSchedules()->create([
            'name' => 'Leap years only',
            'basis' => 'annual_date',
            'annual_month' => 2,
            'annual_day' => 29,
            'leap_day_policy' => 'leap_year_only',
            'status' => 'active',
        ]);
        Carbon::setTestNow();

        $this->assertSame('not_due', $this->evaluate($regular, '2026-03-14')->status);
        $this->assertSame('due', $this->evaluate($regular, '2026-03-15')->status);
        $this->assertSame('overdue', $this->evaluate($regular, '2026-03-16')->status);

        $completion = $regular->completions()->create(['completed_on' => '2026-03-16']);
        $state = $this->evaluate($regular, '2026-03-16');
        $this->assertSame('not_due', $state->status);
        $this->assertSame('Next annual reference date: 2027-03-15.', $state->detail);

        $completion->reversal()->create(['reason' => 'Incorrect annual completion.']);
        $this->assertSame('overdue', $this->evaluate($regular, '2026-03-16')->status);

        $this->assertSame('not_due', $this->evaluate($february, '2026-02-27')->status);
        $this->assertSame('due', $this->evaluate($february, '2026-02-28')->status);
        $this->assertSame('overdue', $this->evaluate($february, '2026-03-01')->status);

        $this->assertSame('not_due', $this->evaluate($march, '2026-02-28')->status);
        $this->assertSame('due', $this->evaluate($march, '2026-03-01')->status);

        $state = $this->evaluate($leapYearOnly, '2026-02-28');
        $this->assertSame('not_due', $state->status);
        $this->assertSame('Next annual reference date: 2028-02-29.', $state->detail);
    }

    public function test_unsupported_or_inconsistent_inputs_fail_closed(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Unavailable Vessel']);

        $annual = $vessel->maintenanceSchedules()->create([
            'name' => 'Leap-day reference',
            'basis' => 'annual_date',
            'annual_month' => 2,
            'annual_day' => 29,
            'status' => 'active',
        ]);
        $this->assertSame('unavailable', $this->evaluate($annual, '2026-08-21')->status);
        $this->assertStringContainsString('February 29', $this->evaluate($annual, '2026-08-21')->detail);

        $annual->update(['status' => 'paused']);
        $this->assertSame('paused', $this->evaluate($annual, '2026-08-21')->status);

        $meter = $vessel->maintenanceSchedules()->create([
            'name' => 'Unlinked meter reference',
            'basis' => 'meter_hours',
            'interval_hours' => '100.0',
            'baseline_hours' => '1000.0',
            'status' => 'active',
        ]);
        $this->assertSame('unavailable', $this->evaluate($meter, '2026-08-21')->status);

        $calendar = $vessel->maintenanceSchedules()->create([
            'name' => 'Conflicting calendar reference',
            'basis' => 'calendar_days',
            'interval_days' => 30,
            'baseline_on' => '2026-08-01',
            'status' => 'active',
        ]);
        $calendar->completions()->create(['completed_on' => '2026-07-31']);
        $this->assertSame('unavailable', $this->evaluate($calendar, '2026-08-21')->status);
    }

    public function test_owner_page_uses_owner_local_date_and_preserves_isolation(): void
    {
        Carbon::setTestNow('2026-08-21 06:30:00 UTC');

        try {
            $owner = User::factory()->create(['timezone' => 'America/Vancouver']);
            $other = User::factory()->create();
            $vessel = $owner->vessels()->create(['name' => 'Local Date Vessel']);
            $vessel->maintenanceSchedules()->create([
                'name' => 'Local calendar service',
                'basis' => 'calendar_days',
                'interval_days' => 19,
                'baseline_on' => '2026-08-01',
                'status' => 'active',
            ]);

            $this->actingAs($owner)
                ->get(route('vessels.maintenance.index', $vessel))
                ->assertOk()
                ->assertSee('Reference status: Due.')
                ->assertSee('Next reference date: 2026-08-20.')
                ->assertSee('do not replace manufacturer instructions');

            $this->actingAs($other)
                ->get(route('vessels.maintenance.index', $vessel))
                ->assertForbidden();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function evaluate(MaintenanceSchedule $schedule, string $today): MaintenanceDueState
    {
        $schedule->unsetRelations();

        return MaintenanceDueState::evaluate(
            $schedule,
            new DateTimeImmutable($today, new DateTimeZone('UTC')),
        );
    }
}

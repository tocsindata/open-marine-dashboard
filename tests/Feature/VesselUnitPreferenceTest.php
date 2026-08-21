<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VesselUnitPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_imperial_owner_sees_dual_unit_length_and_beam_while_draft_remains_metric(): void
    {
        $owner = User::factory()->create(['unit_system' => 'imperial']);
        $other = User::factory()->create(['unit_system' => 'metric']);

        $this->actingAs($owner)
            ->post(route('vessels.store'), [
                'name' => 'Reference Dimensions',
                'length_m' => '12.50',
                'beam_m' => '3.20',
                'draft_m' => '1.75',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $vessel = $owner->vessels()->sole();

        $this->assertDatabaseHas('vessels', [
            'id' => $vessel->id,
            'owner_id' => $owner->id,
            'length_m' => 12.5,
            'beam_m' => 3.2,
            'draft_m' => 1.75,
        ]);

        $this->get(route('vessels.show', $vessel))
            ->assertOk()
            ->assertSee('≈ 41.01 ft (12.50 m)')
            ->assertSee('≈ 10.50 ft (3.20 m)')
            ->assertSee('1.75 m')
            ->assertDontSee('≈ 5.74 ft')
            ->assertSee('Draft remains in metres because it is a clearance and safety value.');

        $this->get(route('vessels.edit', $vessel))
            ->assertOk()
            ->assertSee('Length (m)')
            ->assertSee('Beam (m)')
            ->assertSee('Draft (m)')
            ->assertSee('Enter and edit all vessel dimensions in metres.');

        $this->actingAs($other)
            ->get(route('vessels.show', $vessel))
            ->assertForbidden();
    }

    public function test_metric_owner_sees_metric_reference_dimensions_without_imperial_conversion(): void
    {
        $owner = User::factory()->create(['unit_system' => 'metric']);
        $vessel = $owner->vessels()->create([
            'name' => 'Metric Dimensions',
            'length_m' => '12.50',
            'beam_m' => '3.20',
            'draft_m' => '1.75',
        ]);

        $this->actingAs($owner)
            ->get(route('vessels.show', $vessel))
            ->assertOk()
            ->assertSee('12.50 m')
            ->assertSee('3.20 m')
            ->assertSee('1.75 m')
            ->assertDontSee('≈ 41.01 ft')
            ->assertDontSee('≈ 10.50 ft');
    }
}

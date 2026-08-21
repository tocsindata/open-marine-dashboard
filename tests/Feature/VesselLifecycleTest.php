<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class VesselLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_discover_and_restore_an_archived_vessel(): void
    {
        $owner = User::factory()->create(['timezone' => 'America/Vancouver']);
        $vessel = $owner->vessels()->create(['name' => 'Restorable Vessel']);

        $vessel->update(['status' => 'archived']);
        $vessel->delete();

        $this->assertTrue(Gate::forUser($owner)->allows('restore', $vessel));

        $this->actingAs($owner)
            ->get(route('vessels.index'))
            ->assertOk()
            ->assertSee('Archived vessels')
            ->assertSee('Restorable Vessel')
            ->assertSee('Permanent deletion is unavailable');

        $this->actingAs($owner)
            ->patch(route('vessels.restore', $vessel->id))
            ->assertRedirect(route('vessels.show', $vessel->id))
            ->assertSessionHas('status', 'Vessel restored.');

        $this->assertDatabaseHas('vessels', [
            'id' => $vessel->id,
            'owner_id' => $owner->id,
            'status' => 'active',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'vessel.restored',
            'target_id' => $vessel->id,
        ]);
        $this->actingAs($owner)
            ->get(route('vessels.show', $vessel->id))
            ->assertOk()
            ->assertSee('Restorable Vessel');
    }

    public function test_archived_vessel_discovery_is_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owned = $owner->vessels()->create(['name' => 'Owner Archive']);
        $foreign = $other->vessels()->create(['name' => 'Foreign Archive']);

        foreach ([$owned, $foreign] as $vessel) {
            $vessel->update(['status' => 'archived']);
            $vessel->delete();
        }

        $this->actingAs($owner)
            ->get(route('vessels.index'))
            ->assertOk()
            ->assertSee('Owner Archive')
            ->assertDontSee('Foreign Archive');
    }

    public function test_restore_hides_foreign_active_and_missing_vessel_identifiers(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = $other->vessels()->create(['name' => 'Foreign Restore']);
        $active = $owner->vessels()->create(['name' => 'Already Active']);

        $foreign->update(['status' => 'archived']);
        $foreign->delete();

        $this->assertFalse(Gate::forUser($owner)->allows('restore', $foreign));

        foreach ([$foreign->id, $active->id, (string) str()->ulid()] as $vesselId) {
            $this->actingAs($owner)
                ->patch(route('vessels.restore', $vesselId))
                ->assertNotFound();
        }

        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseHas('vessels', [
            'id' => $foreign->id,
            'status' => 'archived',
        ]);
        $this->assertDatabaseHas('vessels', [
            'id' => $active->id,
            'status' => 'active',
            'deleted_at' => null,
        ]);
        $this->assertNotNull(
            $other->vessels()->withTrashed()->whereKey($foreign->id)->firstOrFail()->deleted_at,
        );
    }
}

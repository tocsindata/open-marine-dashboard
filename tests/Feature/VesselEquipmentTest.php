<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VesselEquipmentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class VesselEquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_view_update_and_retire_equipment(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Equipment Vessel']);

        $this->actingAs($owner)
            ->post(route('vessels.equipment.store', $vessel), [
                'category' => 'propulsion',
                'name' => 'Port engine',
                'manufacturer' => 'Example Marine',
                'model' => 'D4',
                'serial_number' => 'OWNER-ONLY-SERIAL',
                'installed_on' => '2024-05-10',
                'last_serviced_on' => '2026-07-15',
                'warranty_expires_on' => '2029-05-10',
                'meter_hours' => '1250.5',
                'notes' => 'Owner-maintained service reference.',
            ])
            ->assertRedirect(route('vessels.equipment.index', $vessel))
            ->assertSessionHas('status', 'Equipment item added.');

        $equipmentItem = VesselEquipmentItem::query()->sole();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $equipmentItem));
        $this->assertTrue(Gate::forUser($owner)->allows('update', $equipmentItem));
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'vessel_equipment.created',
            'target_id' => $equipmentItem->id,
            'metadata' => null,
        ]);
        $this->actingAs($owner)
            ->get(route('vessels.equipment.index', $vessel))
            ->assertOk()
            ->assertSee('Port engine')
            ->assertSee('OWNER-ONLY-SERIAL')
            ->assertSee('Installed on: 2024-05-10')
            ->assertSee('Last serviced on: 2026-07-15')
            ->assertSee('Warranty expires on: 2029-05-10')
            ->assertSee('Current meter hours: 1250.5 h')
            ->assertSee('not a service interval')
            ->assertSee('Verify physical labels');
        $this->actingAs($owner)
            ->get(route('equipment.edit', $equipmentItem->id))
            ->assertOk()
            ->assertSee('Edit Port engine');

        $this->actingAs($owner)
            ->patch(route('equipment.update', $equipmentItem->id), [
                'category' => 'propulsion',
                'name' => 'Retired port engine',
                'manufacturer' => 'Example Marine',
                'model' => 'D4',
                'serial_number' => 'OWNER-ONLY-SERIAL',
                'installed_on' => '2024-05-10',
                'last_serviced_on' => '2026-08-20',
                'warranty_expires_on' => '2029-05-10',
                'meter_hours' => '1301.0',
                'status' => 'retired',
                'notes' => 'Retained for service history.',
            ])
            ->assertRedirect(route('vessels.equipment.index', $vessel))
            ->assertSessionHas('status', 'Equipment item updated.');

        $this->assertDatabaseHas('vessel_equipment_items', [
            'id' => $equipmentItem->id,
            'vessel_id' => $vessel->id,
            'name' => 'Retired port engine',
            'status' => 'retired',
        ]);
        $equipmentItem->refresh();
        $this->assertSame('2026-08-20', $equipmentItem->last_serviced_on?->format('Y-m-d'));
        $this->assertSame('1301.0', $equipmentItem->meter_hours);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $owner->id,
            'action' => 'vessel_equipment.updated',
            'target_id' => $equipmentItem->id,
            'metadata' => null,
        ]);
    }

    public function test_equipment_routes_and_scope_preserve_owner_missing_and_archived_boundaries(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Private Equipment Vessel']);
        $foreignVessel = $other->vessels()->create(['name' => 'Foreign Equipment Vessel']);
        $equipmentItem = $vessel->equipmentItems()->create([
            'category' => 'battery',
            'name' => 'House battery bank',
            'status' => 'active',
        ]);
        $foreignEquipmentItem = $foreignVessel->equipmentItems()->create([
            'category' => 'navigation',
            'name' => 'Foreign chartplotter',
            'status' => 'active',
        ]);

        $this->assertEqualsCanonicalizing(
            [$equipmentItem->id],
            VesselEquipmentItem::query()->ownedBy($owner)->pluck('id')->all(),
        );
        $this->assertFalse(Gate::forUser($other)->allows('view', $equipmentItem));
        $this->assertFalse(Gate::forUser($other)->allows('update', $equipmentItem));

        $this->actingAs($other)
            ->get(route('vessels.equipment.index', $vessel))
            ->assertForbidden();
        $this->actingAs($other)
            ->post(route('vessels.equipment.store', $vessel), [
                'category' => 'other',
                'name' => 'Cross-user attempt',
            ])
            ->assertForbidden();
        $this->actingAs($other)
            ->get(route('equipment.edit', $equipmentItem->id))
            ->assertNotFound();
        $this->actingAs($other)
            ->patch(route('equipment.update', $equipmentItem->id), [
                'category' => 'other',
                'name' => 'Cross-user attempt',
                'status' => 'retired',
            ])
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('equipment.edit', (string) str()->ulid()))
            ->assertNotFound();

        $this->assertDatabaseHas('vessel_equipment_items', [
            'id' => $equipmentItem->id,
            'name' => 'House battery bank',
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->delete(route('vessels.destroy', $vessel))
            ->assertRedirect();
        $this->actingAs($owner)
            ->get(route('vessels.equipment.index', $vessel->id))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('equipment.edit', $equipmentItem->id))
            ->assertNotFound();

        $this->assertFalse(
            VesselEquipmentItem::query()->ownedBy($owner)->whereKey($equipmentItem->id)->exists(),
        );
        $this->assertTrue(
            VesselEquipmentItem::query()->whereKey($foreignEquipmentItem->id)->exists(),
        );
    }

    public function test_service_metadata_preserves_calendar_precision_and_exact_meter_boundaries(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Service Metadata Vessel']);

        $this->actingAs($owner)
            ->post(route('vessels.equipment.store', $vessel), [
                'category' => 'generator',
                'name' => 'Generator',
                'installed_on' => '2024-02-29',
                'last_serviced_on' => '2026-08-21',
                'warranty_expires_on' => '2027-01-01',
                'meter_hours' => '0',
            ])
            ->assertRedirect();

        $equipmentItem = VesselEquipmentItem::query()->sole();
        $this->assertSame('2024-02-29', $equipmentItem->installed_on?->format('Y-m-d'));
        $this->assertSame('0.0', $equipmentItem->meter_hours);

        $this->actingAs($owner)
            ->patch(route('equipment.update', $equipmentItem->id), [
                'category' => 'generator',
                'name' => 'Generator',
                'installed_on' => '2024-02-29',
                'last_serviced_on' => '2026-08-21',
                'warranty_expires_on' => '2027-01-01',
                'meter_hours' => '999999999.9',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertSame('999999999.9', $equipmentItem->refresh()->meter_hours);

        $this->actingAs($owner)
            ->patch(route('equipment.update', $equipmentItem->id), [
                'category' => 'generator',
                'name' => 'Generator',
                'installed_on' => '2026-02-29',
                'last_serviced_on' => '2026-8-21',
                'warranty_expires_on' => 'not-a-date',
                'meter_hours' => '-0.1',
                'status' => 'active',
            ])
            ->assertSessionHasErrors([
                'installed_on',
                'last_serviced_on',
                'warranty_expires_on',
                'meter_hours',
            ]);

        $this->actingAs($owner)
            ->patch(route('equipment.update', $equipmentItem->id), [
                'category' => 'generator',
                'name' => 'Generator',
                'meter_hours' => '1000000000.0',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('meter_hours');

        $this->actingAs($owner)
            ->patch(route('equipment.update', $equipmentItem->id), [
                'category' => 'generator',
                'name' => 'Generator',
                'meter_hours' => '1.23',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('meter_hours');

        $this->assertSame('999999999.9', $equipmentItem->refresh()->meter_hours);
    }

    public function test_equipment_validation_preserves_exact_name_and_allowlist_boundaries(): void
    {
        $owner = User::factory()->create();
        $vessel = $owner->vessels()->create(['name' => 'Validation Vessel']);
        $maximumName = str_repeat('E', 120);

        $this->actingAs($owner)
            ->post(route('vessels.equipment.store', $vessel), [
                'category' => 'safety',
                'name' => $maximumName,
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('vessel_equipment_items', ['name' => $maximumName]);

        $this->actingAs($owner)
            ->post(route('vessels.equipment.store', $vessel), [
                'category' => 'unapproved-category',
                'name' => str_repeat('E', 121),
            ])
            ->assertSessionHasErrors(['category', 'name']);
        $this->assertDatabaseCount('vessel_equipment_items', 1);

        $equipmentItem = VesselEquipmentItem::query()->sole();
        $this->actingAs($owner)
            ->patch(route('equipment.update', $equipmentItem->id), [
                'category' => 'safety',
                'name' => $maximumName,
                'status' => 'deleted',
            ])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('vessel_equipment_items', [
            'id' => $equipmentItem->id,
            'status' => 'active',
        ]);
    }
}

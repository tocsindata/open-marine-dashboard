<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Vessel;
use App\Models\VesselEquipmentItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VesselEquipmentController extends Controller
{
    private const CATEGORIES = [
        'propulsion' => 'Propulsion / engine',
        'generator' => 'Generator',
        'battery' => 'Battery',
        'tank' => 'Tank',
        'electrical' => 'Electrical',
        'navigation' => 'Navigation',
        'communications' => 'Communications',
        'safety' => 'Safety',
        'plumbing' => 'Plumbing',
        'other' => 'Other',
    ];

    private const STATUSES = [
        'active' => 'Active',
        'retired' => 'Retired',
    ];

    public function index(Request $request, Vessel $vessel): View
    {
        Gate::authorize('view', $vessel);

        return view('equipment.index', [
            'vessel' => $vessel,
            'equipmentItems' => $vessel->equipmentItems()
                ->orderBy('status')
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'equipmentItem' => new VesselEquipmentItem,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request, Vessel $vessel): RedirectResponse
    {
        Gate::authorize('update', $vessel);

        $details = $request->validate($this->detailRules());
        $details['status'] = 'active';
        $equipmentItem = $vessel->equipmentItems()->create($details);
        $this->audit($request, 'vessel_equipment.created', $equipmentItem);

        return redirect()
            ->route('vessels.equipment.index', $vessel)
            ->with('status', 'Equipment item added.');
    }

    public function edit(Request $request, string $equipment): View
    {
        $equipmentItem = $this->ownedEquipment($request, $equipment);
        Gate::authorize('update', $equipmentItem);

        return view('equipment.edit', [
            'equipmentItem' => $equipmentItem,
            'categories' => self::CATEGORIES,
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, string $equipment): RedirectResponse
    {
        $equipmentItem = $this->ownedEquipment($request, $equipment);
        Gate::authorize('update', $equipmentItem);

        $details = $request->validate([
            ...$this->detailRules(),
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
        ]);
        $equipmentItem->update($details);
        $this->audit($request, 'vessel_equipment.updated', $equipmentItem);

        return redirect()
            ->route('vessels.equipment.index', $equipmentItem->vessel_id)
            ->with('status', 'Equipment item updated.');
    }

    private function detailRules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'name' => ['required', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'installed_on' => ['nullable', 'date_format:Y-m-d'],
            'last_serviced_on' => ['nullable', 'date_format:Y-m-d'],
            'warranty_expires_on' => ['nullable', 'date_format:Y-m-d'],
            'meter_hours' => ['nullable', 'numeric', 'decimal:0,1', 'between:0,999999999.9'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function ownedEquipment(Request $request, string $equipment): VesselEquipmentItem
    {
        return VesselEquipmentItem::query()
            ->ownedBy($request->user())
            ->with('vessel')
            ->whereKey($equipment)
            ->firstOrFail();
    }

    private function audit(Request $request, string $action, VesselEquipmentItem $equipmentItem): void
    {
        AuditEvent::create([
            'actor_id' => $request->user()->id,
            'channel' => 'web',
            'action' => $action,
            'target_type' => VesselEquipmentItem::class,
            'target_id' => $equipmentItem->id,
            'occurred_at' => now(),
        ]);
    }
}

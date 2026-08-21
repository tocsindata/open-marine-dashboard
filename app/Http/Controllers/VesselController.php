<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Http/Controllers/VesselController.php
 * Date: 2026-08-19
 * Copyright:
 *   Copyright (c) 2026 Tocsin Data / Global Incident Map.
 *   All rights reserved.
 */

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VesselController extends Controller
{
    public function index(Request $request): View
    {
        return view('vessels.index', [
            'vessels' => $request->user()
                ->vessels()
                ->where('vessels.status', 'active')
                ->withCount([
                    'maintenanceReminderOccurrences as open_reminder_count' => fn (Builder $occurrences): Builder => $occurrences->where('maintenance_reminder_occurrences.status', 'open'),
                    'maintenanceReminderOccurrences as open_calendar_day_reminder_count' => fn (Builder $occurrences): Builder => $occurrences
                        ->where('maintenance_reminder_occurrences.status', 'open')
                        ->where('maintenance_reminder_occurrences.reference_type', 'calendar_days'),
                    'maintenanceReminderOccurrences as open_annual_date_reminder_count' => fn (Builder $occurrences): Builder => $occurrences
                        ->where('maintenance_reminder_occurrences.status', 'open')
                        ->where('maintenance_reminder_occurrences.reference_type', 'annual_date'),
                    'maintenanceReminderOccurrences as open_meter_hour_reminder_count' => fn (Builder $occurrences): Builder => $occurrences
                        ->where('maintenance_reminder_occurrences.status', 'open')
                        ->where('maintenance_reminder_occurrences.reference_type', 'meter_hours'),
                ])
                ->latest()
                ->get(),
            'archivedVessels' => $request->user()->vessels()->onlyTrashed()->latest('deleted_at')->get(),
        ]);
    }

    public function create(): View
    {
        return view('vessels.form', ['vessel' => new Vessel]);
    }

    public function show(Request $request, Vessel $vessel): View
    {
        Gate::authorize('view', $vessel);

        return view('vessels.show', compact('vessel'));
    }

    public function edit(Request $request, Vessel $vessel): View
    {
        Gate::authorize('update', $vessel);

        return view('vessels.form', compact('vessel'));
    }

    public function store(Request $request): RedirectResponse
    {
        $vessel = $request->user()->vessels()->create($this->validated($request));
        $this->audit($request, 'vessel.created', $vessel);

        return redirect()->route('vessels.show', $vessel)->with('status', 'Vessel created.');
    }

    public function update(Request $request, Vessel $vessel): RedirectResponse
    {
        Gate::authorize('update', $vessel);
        $vessel->update($this->validated($request));
        $this->audit($request, 'vessel.updated', $vessel);

        return redirect()->route('vessels.show', $vessel)->with('status', 'Vessel updated.');
    }

    public function destroy(Request $request, Vessel $vessel): RedirectResponse
    {
        Gate::authorize('delete', $vessel);
        $vessel->update(['status' => 'archived']);
        $vessel->delete();
        $this->audit($request, 'vessel.archived', $vessel);

        return redirect()->route('vessels.index')->with('status', 'Vessel archived.');
    }

    public function restore(Request $request, string $vessel): RedirectResponse
    {
        $archivedVessel = $request->user()
            ->vessels()
            ->onlyTrashed()
            ->whereKey($vessel)
            ->firstOrFail();

        Gate::authorize('restore', $archivedVessel);
        $archivedVessel->restore();
        $archivedVessel->update(['status' => 'active']);
        $this->audit($request, 'vessel.restored', $archivedVessel);

        return redirect()->route('vessels.show', $archivedVessel)->with('status', 'Vessel restored.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'], 'type' => ['nullable', 'string', 'max:64'],
            'make' => ['nullable', 'string', 'max:80'], 'model' => ['nullable', 'string', 'max:80'],
            'model_year' => ['nullable', 'integer', 'between:1800,'.(date('Y') + 1)], 'home_port' => ['nullable', 'string', 'max:120'],
            'length_m' => ['nullable', 'numeric', 'between:0.1,500'], 'beam_m' => ['nullable', 'numeric', 'between:0.1,100'],
            'draft_m' => ['nullable', 'numeric', 'between:0,50'], 'registration_number' => ['nullable', 'string', 'max:80'],
            'mmsi' => ['nullable', 'regex:/^[0-9]{9}$/'], 'call_sign' => ['nullable', 'string', 'max:32'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function audit(Request $request, string $action, Vessel $vessel): void
    {
        AuditEvent::create(['actor_id' => $request->user()->id, 'channel' => 'web', 'action' => $action, 'target_type' => Vessel::class, 'target_id' => $vessel->id, 'occurred_at' => now()]);
    }
}

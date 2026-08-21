<?php

/**
 * Project: Marine
 * Framework: Laravel 12
 * File: app/Http/Controllers/DashboardController.php
 * Date: 2026-08-19
 * SPDX-License-Identifier: AGPL-3.0-only
 * Copyright (c) 2026 Tocsin Data.
 */

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $vessels = $user->vessels()
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
            ->orderBy('name')
            ->get();

        return view('dashboard', [
            'vessels' => $vessels,
            'openTasks' => $user->tasks()->where('status', 'open')->orderBy('due_at')->limit(8)->get(),
            'openReminderCount' => $vessels->sum('open_reminder_count'),
            'openReminderCountsByType' => [
                'calendar_days' => $vessels->sum('open_calendar_day_reminder_count'),
                'annual_date' => $vessels->sum('open_annual_date_reminder_count'),
                'meter_hours' => $vessels->sum('open_meter_hour_reminder_count'),
            ],
        ]);
    }
}

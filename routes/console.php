<?php

use App\Models\MaintenanceSchedule;
use App\Support\MaintenanceReminderOccurrenceSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('marine:maintenance-reminders:sync', function () {
    $created = 0;
    $sync = app(MaintenanceReminderOccurrenceSync::class);

    MaintenanceSchedule::query()
        ->where(function (Builder $query): void {
            $query
                ->where(function (Builder $active): void {
                    $active
                        ->where('status', 'active')
                        ->where('reminder_enabled', true)
                        ->whereHas('vessel');
                })
                ->orWhereHas(
                    'reminderOccurrences',
                    fn (Builder $occurrences): Builder => $occurrences->where('status', 'open'),
                );
        })
        ->with([
            'vessel' => fn ($vessels) => $vessels->withTrashed()->with('owner'),
            'equipmentItem',
            'completions.reversal',
        ])
        ->lazyById(100)
        ->each(function (MaintenanceSchedule $schedule) use (&$created, $sync): void {
            $vessel = $schedule->vessel;
            $owner = $vessel?->owner;

            if ($owner === null) {
                return;
            }

            $today = now($owner->timezone ?: 'UTC')->startOfDay()->toImmutable();
            $occurrence = $sync->sync($schedule, $today, ! $vessel->trashed());

            if ($occurrence?->wasRecentlyCreated) {
                $created += 1;
            }
        });

    $this->info("Opened {$created} in-app maintenance reminder occurrence(s).");

    return 0;
})->purpose('Open or reconcile private in-app maintenance reminders without outbound delivery');

Schedule::command('marine:maintenance-reminders:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('marine:sources:tick')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();

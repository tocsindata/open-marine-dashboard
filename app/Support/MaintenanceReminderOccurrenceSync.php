<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\MaintenanceReminderOccurrence;
use App\Models\MaintenanceSchedule;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MaintenanceReminderOccurrenceSync
{
    public function sync(
        MaintenanceSchedule $schedule,
        DateTimeInterface $today,
        bool $vesselActive = true,
    ): ?MaintenanceReminderOccurrence {
        $state = $vesselActive ? MaintenanceDueState::evaluate($schedule, $today) : null;

        return DB::transaction(function () use ($schedule, $state): ?MaintenanceReminderOccurrence {
            $now = now();
            $referenceType = $state?->referenceType;
            $referenceValue = $state?->referenceValue;
            $eligible = $state?->reminderEligible === true
                && $referenceType !== null
                && $referenceValue !== null;

            $schedule->reminderOccurrences()
                ->whereIn('status', ['open', 'suppressed'])
                ->lockForUpdate()
                ->get()
                ->each(function (MaintenanceReminderOccurrence $occurrence) use (
                    $eligible,
                    $now,
                    $referenceType,
                    $referenceValue,
                ): void {
                    if ($referenceType === null || $referenceValue === null) {
                        $this->transition($occurrence, 'suppressed', 'suppressed_at', 'suppressed', $now);

                        return;
                    }

                    $matchesCurrentReference = $occurrence->reference_type === $referenceType
                        && $occurrence->reference_value === $referenceValue;

                    if (! $matchesCurrentReference) {
                        $this->transition($occurrence, 'superseded', 'superseded_at', 'superseded', $now);

                        return;
                    }

                    if (! $eligible) {
                        $this->transition($occurrence, 'suppressed', 'suppressed_at', 'suppressed', $now);

                        return;
                    }

                    if ($occurrence->status === 'suppressed') {
                        $occurrence->update([
                            'status' => 'open',
                            'suppressed_at' => null,
                        ]);
                        $this->audit($occurrence, 'reopened', $now);
                    }
                });

            if (! $eligible) {
                return null;
            }

            $existing = MaintenanceReminderOccurrence::query()
                ->where('maintenance_schedule_id', $schedule->id)
                ->where('reference_type', $referenceType)
                ->where('reference_value', $referenceValue)
                ->first();

            if ($existing !== null) {
                $existing->wasRecentlyCreated = false;

                return $existing;
            }

            $id = (string) Str::ulid();
            $created = DB::table('maintenance_reminder_occurrences')->insertOrIgnore([
                'id' => $id,
                'maintenance_schedule_id' => $schedule->id,
                'reference_type' => $referenceType,
                'reference_value' => $referenceValue,
                'status' => 'open',
                'opened_at' => $now,
                'acknowledged_at' => null,
                'suppressed_at' => null,
                'dismissed_at' => null,
                'superseded_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $occurrence = MaintenanceReminderOccurrence::query()
                ->where('maintenance_schedule_id', $schedule->id)
                ->where('reference_type', $referenceType)
                ->where('reference_value', $referenceValue)
                ->firstOrFail();
            $occurrence->wasRecentlyCreated = $created === 1;

            if ($occurrence->wasRecentlyCreated) {
                $this->audit($occurrence, 'opened', $now);
            }

            return $occurrence;
        });
    }

    private function transition(
        MaintenanceReminderOccurrence $occurrence,
        string $status,
        string $timestamp,
        string $action,
        DateTimeInterface $now,
    ): void {
        if ($occurrence->status === $status) {
            return;
        }

        $occurrence->update([
            'status' => $status,
            $timestamp => $now,
        ]);
        $this->audit($occurrence, $action, $now);
    }

    private function audit(
        MaintenanceReminderOccurrence $occurrence,
        string $action,
        DateTimeInterface $occurredAt,
    ): void {
        AuditEvent::create([
            'actor_id' => null,
            'channel' => 'system',
            'action' => "maintenance_reminder.{$action}",
            'target_type' => MaintenanceReminderOccurrence::class,
            'target_id' => $occurrence->id,
            'occurred_at' => $occurredAt,
        ]);
    }
}

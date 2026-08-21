<?php

namespace App\Support;

use App\Models\MaintenanceCompletion;
use App\Models\MaintenanceSchedule;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final readonly class MaintenanceDueState
{
    private function __construct(
        public string $status,
        public string $label,
        public string $detail,
        public ?string $referenceType = null,
        public ?string $referenceValue = null,
        public bool $reminderEligible = false,
    ) {}

    public static function evaluate(MaintenanceSchedule $schedule, DateTimeInterface $today): self
    {
        if ($schedule->status === 'paused') {
            return self::state('paused', 'Paused', 'This schedule is paused; no due comparison is shown.');
        }

        $schedule->loadMissing(['equipmentItem', 'completions.reversal']);

        $completion = $schedule->completions
            ->reject(fn (MaintenanceCompletion $item): bool => $item->reversal !== null)
            ->sortByDesc(fn (MaintenanceCompletion $item): string => implode('|', [
                $item->completed_on?->format('Y-m-d') ?? '',
                $item->created_at?->format('Y-m-d H:i:s.u') ?? '',
                (string) $item->getKey(),
            ]))
            ->first();

        return match ($schedule->basis) {
            'calendar_days' => self::calendarState($schedule, $completion, $today),
            'meter_hours' => self::meterState($schedule, $completion, $today),
            'annual_date' => self::annualState($schedule, $completion, $today),
            default => self::state('unavailable', 'Unavailable', 'The recurrence basis is not supported.'),
        };
    }

    private static function calendarState(
        MaintenanceSchedule $schedule,
        ?MaintenanceCompletion $completion,
        DateTimeInterface $today,
    ): self {
        $baseline = self::date($schedule->baseline_on?->format('Y-m-d'));
        $interval = filter_var($schedule->interval_days, FILTER_VALIDATE_INT);
        $todayValue = $today->format('Y-m-d');

        if ($baseline === null || $interval === false || $interval < 1) {
            return self::state('unavailable', 'Unavailable', 'A valid starting date and calendar-day interval are required.');
        }

        $anchor = $baseline;

        if ($completion !== null) {
            $completedOn = self::date($completion->completed_on?->format('Y-m-d'));

            if ($completedOn === null || $completedOn < $baseline || $completedOn > $todayValue) {
                return self::state(
                    'unavailable',
                    'Unavailable',
                    'The latest effective completion date conflicts with the starting date or the owner-local current date.',
                );
            }

            $anchor = $completedOn;
        }

        $anchorDate = DateTimeImmutable::createFromFormat('!Y-m-d', $anchor, new DateTimeZone('UTC'));
        $next = $anchorDate === false ? null : $anchorDate->modify("+{$interval} days")->format('Y-m-d');

        if ($next === null) {
            return self::state('unavailable', 'Unavailable', 'The next reference date could not be calculated.');
        }

        return self::comparisonState(
            $todayValue,
            $next,
            "Next reference date: {$next}.",
            'calendar_days',
            $next,
            self::calendarReminderEligible($schedule, $todayValue, $next),
        );
    }

    /**
     * Annual schedules stay fixed to their chosen month/day. Creation is the
     * first eligible anchor, effective completions advance the next occurrence,
     * and February 29 never rolls over without the owner's explicit policy.
     */
    private static function annualState(
        MaintenanceSchedule $schedule,
        ?MaintenanceCompletion $completion,
        DateTimeInterface $today,
    ): self {
        $month = filter_var($schedule->annual_month, FILTER_VALIDATE_INT);
        $day = filter_var($schedule->annual_day, FILTER_VALIDATE_INT);
        $policy = $schedule->leap_day_policy;
        $isLeapDay = $month === 2 && $day === 29;

        if (
            $month === false
            || $day === false
            || ! checkdate($month, $day, 2000)
        ) {
            return self::state('unavailable', 'Unavailable', 'A valid annual month and day are required.');
        }

        if ($isLeapDay && ! in_array($policy, ['leap_year_only', 'february_28', 'march_1'], true)) {
            return self::state(
                'unavailable',
                'Unavailable',
                'February 29 schedules require an explicit non-leap-year rule.',
            );
        }

        if (! $isLeapDay && $policy !== null) {
            return self::state(
                'unavailable',
                'Unavailable',
                'Only February 29 schedules may define a non-leap-year rule.',
            );
        }

        $timeZone = $today->getTimezone();

        if ($schedule->created_at === null || $timeZone === false) {
            return self::state('unavailable', 'Unavailable', 'A valid schedule creation date is required.');
        }

        $createdOn = DateTimeImmutable::createFromInterface($schedule->created_at)
            ->setTimezone($timeZone)
            ->format('Y-m-d');
        $anchor = $createdOn;
        $includeAnchor = true;

        if ($completion !== null) {
            $completedOn = self::date($completion->completed_on?->format('Y-m-d'));

            if (
                $completedOn === null
                || $completedOn < $createdOn
                || $completedOn > $today->format('Y-m-d')
            ) {
                return self::state(
                    'unavailable',
                    'Unavailable',
                    'The latest effective completion conflicts with the schedule creation date or the owner-local current date.',
                );
            }

            $anchor = $completedOn;
            $includeAnchor = false;
        }

        $next = self::nextAnnualDate($month, $day, $policy, $anchor, $includeAnchor);

        if ($next === null) {
            return self::state('unavailable', 'Unavailable', 'The next annual reference date could not be calculated.');
        }

        $todayValue = $today->format('Y-m-d');

        return self::comparisonState(
            $todayValue,
            $next,
            "Next annual reference date: {$next}.",
            'annual_date',
            $next,
            self::calendarReminderEligible($schedule, $todayValue, $next),
        );
    }

    private static function nextAnnualDate(
        int $month,
        int $day,
        ?string $policy,
        string $anchor,
        bool $includeAnchor,
    ): ?string {
        $anchorYear = (int) substr($anchor, 0, 4);

        // Eight years covers the largest Gregorian leap-year gap across a skipped century year.
        for ($offset = 0; $offset <= 8; $offset += 1) {
            $candidate = self::annualDate($anchorYear + $offset, $month, $day, $policy);

            if (
                $candidate !== null
                && ($includeAnchor ? $candidate >= $anchor : $candidate > $anchor)
            ) {
                return $candidate;
            }
        }

        return null;
    }

    private static function annualDate(int $year, int $month, int $day, ?string $policy): ?string
    {
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        if ($month !== 2 || $day !== 29) {
            return null;
        }

        return match ($policy) {
            'february_28' => sprintf('%04d-02-28', $year),
            'march_1' => sprintf('%04d-03-01', $year),
            'leap_year_only' => null,
            default => null,
        };
    }

    private static function meterState(
        MaintenanceSchedule $schedule,
        ?MaintenanceCompletion $completion,
        DateTimeInterface $today,
    ): self {
        $equipment = $schedule->equipmentItem;

        if (
            $equipment === null
            || $equipment->status !== 'active'
            || (string) $equipment->vessel_id !== (string) $schedule->vessel_id
        ) {
            return self::state(
                'unavailable',
                'Unavailable',
                'An active equipment record from this vessel is required for a meter-hour comparison.',
            );
        }

        $baseline = self::tenths($schedule->baseline_hours);
        $interval = self::tenths($schedule->interval_hours);
        $current = self::tenths($equipment->meter_hours);

        if ($baseline === null || $interval === null || $interval < 1 || $current === null) {
            return self::state(
                'unavailable',
                'Unavailable',
                'Valid starting, interval, and current equipment meter readings are required.',
            );
        }

        $anchor = $baseline;

        if ($completion !== null) {
            $completedOn = self::date($completion->completed_on?->format('Y-m-d'));
            $completedAt = self::tenths($completion->meter_hours);

            if (
                $completedOn === null
                || $completedOn > $today->format('Y-m-d')
                || $completedAt === null
                || $completedAt < $baseline
            ) {
                return self::state(
                    'unavailable',
                    'Unavailable',
                    'The latest effective completion conflicts with the starting reading or the owner-local current date.',
                );
            }

            $anchor = $completedAt;
        }

        if ($current < $anchor) {
            return self::state(
                'unavailable',
                'Unavailable',
                'The current equipment meter is below the effective starting or completion reading.',
            );
        }

        $next = $anchor + $interval;
        $detail = sprintf(
            'Next reference meter: %s h; recorded current meter: %s h.',
            self::formatTenths($next),
            self::formatTenths($current),
        );

        return self::comparisonState(
            $current,
            $next,
            $detail,
            'meter_hours',
            self::formatTenths($next),
            self::meterReminderEligible($schedule, $current, $next),
        );
    }

    private static function calendarReminderEligible(
        MaintenanceSchedule $schedule,
        string $today,
        string $next,
    ): bool {
        $lead = filter_var($schedule->reminder_lead_days, FILTER_VALIDATE_INT);

        if (! $schedule->reminder_enabled || $lead === false || $lead < 0 || $lead > 3650) {
            return false;
        }

        $nextDate = DateTimeImmutable::createFromFormat('!Y-m-d', $next, new DateTimeZone('UTC'));
        $threshold = $nextDate === false
            ? null
            : $nextDate->modify("-{$lead} days")->format('Y-m-d');

        return $threshold !== null && $today >= $threshold;
    }

    private static function meterReminderEligible(
        MaintenanceSchedule $schedule,
        int $current,
        int $next,
    ): bool {
        $lead = self::tenths($schedule->reminder_lead_hours);

        if (! $schedule->reminder_enabled || $lead === null || $lead > 9999999999) {
            return false;
        }

        return $current >= $next - $lead;
    }

    private static function comparisonState(
        int|string $current,
        int|string $next,
        string $detail,
        string $referenceType,
        string $referenceValue,
        bool $reminderEligible,
    ): self {
        if ($current < $next) {
            return self::state('not_due', 'Not due', $detail, $referenceType, $referenceValue, $reminderEligible);
        }

        if ($current === $next) {
            return self::state('due', 'Due', $detail, $referenceType, $referenceValue, $reminderEligible);
        }

        return self::state('overdue', 'Overdue', $detail, $referenceType, $referenceValue, $reminderEligible);
    }

    private static function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private static function tenths(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        if (! preg_match('/^(?<whole>\d+)(?:\.(?<fraction>\d))?$/D', (string) $value, $matches)) {
            return null;
        }

        return ((int) $matches['whole'] * 10) + (int) ($matches['fraction'] ?? 0);
    }

    private static function formatTenths(int $value): string
    {
        return sprintf('%d.%d', intdiv($value, 10), $value % 10);
    }

    private static function state(
        string $status,
        string $label,
        string $detail,
        ?string $referenceType = null,
        ?string $referenceValue = null,
        bool $reminderEligible = false,
    ): self {
        return new self($status, $label, $detail, $referenceType, $referenceValue, $reminderEligible);
    }
}

<ul aria-label="Open maintenance reminder breakdown for {{ $vessel->name }}">
    <li>
        <a
            href="{{ route('maintenance.reminders.index', ['vessel' => $vessel->id, 'reference_type' => 'calendar_days']) }}"
            aria-label="View Calendar-day open maintenance reminders for {{ $vessel->name }}: {{ $vessel->open_calendar_day_reminder_count }}"
        >Calendar-day: {{ $vessel->open_calendar_day_reminder_count }}</a>
    </li>
    <li>
        <a
            href="{{ route('maintenance.reminders.index', ['vessel' => $vessel->id, 'reference_type' => 'annual_date']) }}"
            aria-label="View Annual-date open maintenance reminders for {{ $vessel->name }}: {{ $vessel->open_annual_date_reminder_count }}"
        >Annual-date: {{ $vessel->open_annual_date_reminder_count }}</a>
    </li>
    <li>
        <a
            href="{{ route('maintenance.reminders.index', ['vessel' => $vessel->id, 'reference_type' => 'meter_hours']) }}"
            aria-label="View Meter-hour open maintenance reminders for {{ $vessel->name }}: {{ $vessel->open_meter_hour_reminder_count }}"
        >Meter-hour: {{ $vessel->open_meter_hour_reminder_count }}</a>
    </li>
</ul>

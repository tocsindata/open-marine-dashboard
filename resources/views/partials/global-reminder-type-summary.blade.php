<ul aria-label="Open maintenance reminders across all active vessels by reference type">
    <li>
        <a
            href="{{ route('maintenance.reminders.index', ['reference_type' => 'calendar_days']) }}"
            aria-label="View all Calendar-day open maintenance reminders: {{ $counts['calendar_days'] }}"
        >Calendar-day: {{ $counts['calendar_days'] }}</a>
    </li>
    <li>
        <a
            href="{{ route('maintenance.reminders.index', ['reference_type' => 'annual_date']) }}"
            aria-label="View all Annual-date open maintenance reminders: {{ $counts['annual_date'] }}"
        >Annual-date: {{ $counts['annual_date'] }}</a>
    </li>
    <li>
        <a
            href="{{ route('maintenance.reminders.index', ['reference_type' => 'meter_hours']) }}"
            aria-label="View all Meter-hour open maintenance reminders: {{ $counts['meter_hours'] }}"
        >Meter-hour: {{ $counts['meter_hours'] }}</a>
    </li>
</ul>

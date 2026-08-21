<div class="formgrid">
    <label for="maintenance-name">Schedule name
        <input id="maintenance-name" name="name" maxlength="120" required value="{{ old('name', $schedule->name) }}">
    </label>
    <label for="maintenance-equipment">Equipment
        <select id="maintenance-equipment" name="equipment_item_id">
            <option value="">Whole vessel / no equipment selected</option>
            @foreach($equipmentItems as $item)
                <option value="{{ $item->id }}" @selected(old('equipment_item_id', $schedule->equipment_item_id) === $item->id)>{{ $item->name }}</option>
            @endforeach
        </select>
    </label>
    <label for="maintenance-basis">Recurrence basis
        <select id="maintenance-basis" name="basis" required aria-describedby="maintenance-guidance">
            @foreach($bases as $value => $label)
                <option value="{{ $value }}" @selected(old('basis', $schedule->basis) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label for="maintenance-interval-hours">Meter-hours interval (h)
        <input id="maintenance-interval-hours" type="number" name="interval_hours" min="0.1" max="999999999.9" step="0.1" inputmode="decimal" value="{{ old('interval_hours', $schedule->interval_hours) }}">
    </label>
    <label for="maintenance-interval-days">Calendar-day interval
        <input id="maintenance-interval-days" type="number" name="interval_days" min="1" max="3650" step="1" inputmode="numeric" value="{{ old('interval_days', $schedule->interval_days) }}">
    </label>
    <label for="maintenance-baseline-hours">Starting meter hours (h)
        <input id="maintenance-baseline-hours" type="number" name="baseline_hours" min="0" max="999999999.9" step="0.1" inputmode="decimal" value="{{ old('baseline_hours', $schedule->baseline_hours) }}">
    </label>
    <label for="maintenance-baseline-on">Starting calendar date
        <input id="maintenance-baseline-on" type="date" name="baseline_on" value="{{ old('baseline_on', $schedule->baseline_on?->format('Y-m-d')) }}">
    </label>
    <label for="maintenance-annual-month">Annual month (1–12)
        <input id="maintenance-annual-month" type="number" name="annual_month" min="1" max="12" step="1" inputmode="numeric" value="{{ old('annual_month', $schedule->annual_month) }}">
    </label>
    <label for="maintenance-annual-day">Annual day (1–31)
        <input id="maintenance-annual-day" type="number" name="annual_day" min="1" max="31" step="1" inputmode="numeric" value="{{ old('annual_day', $schedule->annual_day) }}">
    </label>
    <label for="maintenance-leap-day-policy">February 29 rule
        <select id="maintenance-leap-day-policy" name="leap_day_policy" aria-describedby="maintenance-leap-day-guidance">
            <option value="">Select only for February 29</option>
            @foreach($leapDayPolicies as $value => $label)
                <option value="{{ $value }}" @selected(old('leap_day_policy', $schedule->leap_day_policy) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label for="maintenance-source-kind">Recorded source type
        <select id="maintenance-source-kind" name="source_kind" aria-describedby="maintenance-source-guidance">
            <option value="">No source reference recorded</option>
            @foreach($sourceKinds as $value => $label)
                <option value="{{ $value }}" @selected(old('source_kind', $schedule->source_kind) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label for="maintenance-source-title">Document title
        <input id="maintenance-source-title" name="source_title" maxlength="160" value="{{ old('source_title', $schedule->source_title) }}">
    </label>
    <label for="maintenance-source-reference">Document or bulletin number
        <input id="maintenance-source-reference" name="source_reference" maxlength="160" value="{{ old('source_reference', $schedule->source_reference) }}">
    </label>
    <label for="maintenance-source-revision">Revision
        <input id="maintenance-source-revision" name="source_revision" maxlength="80" value="{{ old('source_revision', $schedule->source_revision) }}">
    </label>
    <label for="maintenance-source-section">Page or section
        <input id="maintenance-source-section" name="source_section" maxlength="120" value="{{ old('source_section', $schedule->source_section) }}">
    </label>
    <label for="maintenance-source-published-on">Document publication date
        <input id="maintenance-source-published-on" type="date" name="source_published_on" value="{{ old('source_published_on', $schedule->source_published_on?->format('Y-m-d')) }}">
    </label>
    <label for="maintenance-source-url">Public HTTPS document URL
        <input id="maintenance-source-url" type="url" name="source_url" maxlength="2048" inputmode="url" value="{{ old('source_url', $schedule->source_url) }}">
    </label>
    <label for="maintenance-reminder-enabled">
        <input type="hidden" name="reminder_enabled" value="0">
        <input id="maintenance-reminder-enabled" type="checkbox" name="reminder_enabled" value="1" @checked((bool) old('reminder_enabled', $schedule->reminder_enabled)) aria-describedby="maintenance-reminder-guidance">
        Store a reminder preference
    </label>
    <label for="maintenance-reminder-lead-days">Calendar-day reminder lead
        <input id="maintenance-reminder-lead-days" type="number" name="reminder_lead_days" min="0" max="3650" step="1" inputmode="numeric" value="{{ old('reminder_lead_days', $schedule->reminder_lead_days) }}" aria-describedby="maintenance-reminder-guidance">
    </label>
    <label for="maintenance-reminder-lead-hours">Meter-hours reminder lead (h)
        <input id="maintenance-reminder-lead-hours" type="number" name="reminder_lead_hours" min="0" max="999999999.9" step="0.1" inputmode="decimal" value="{{ old('reminder_lead_hours', $schedule->reminder_lead_hours) }}" aria-describedby="maintenance-reminder-guidance">
    </label>
</div>
<p id="maintenance-guidance">Choose one recurrence basis and complete only its matching interval and starting value. Meter-hour schedules require starting meter hours; calendar-day schedules require a starting calendar date; annual schedules begin with the first selected month/day on or after creation.</p>
<p id="maintenance-source-guidance">Source metadata is optional and owner-entered. Record the exact document, revision, and section you checked. The application does not authenticate the publisher, verify that a document applies to this equipment, or turn a citation into manufacturer authority. Public links must use HTTPS and contain no login, token, query string, or fragment.</p>
<p id="maintenance-reminder-guidance">This stores a private preference only. The application does not schedule or send email, push, SMS, or in-app reminders yet. Use calendar days for calendar-day and annual schedules, or meter hours for meter-hour schedules. A lead of zero means at the due boundary. Disabling the preference clears both lead values.</p>
<p id="maintenance-leap-day-guidance">For February 29 only, explicitly choose whether non-leap years wait for the next leap year, use February 28, or use March 1. Existing February 29 schedules remain unavailable until edited. These owner-maintained references do not replace manufacturer instructions or inspection requirements.</p>
<label for="maintenance-notes">Private notes
    <textarea id="maintenance-notes" name="notes" rows="4" maxlength="5000">{{ old('notes', $schedule->notes) }}</textarea>
</label>

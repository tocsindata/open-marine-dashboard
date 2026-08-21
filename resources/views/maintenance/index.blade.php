@extends('layouts.app', ['title' => 'Maintenance · '.$vessel->name])

@section('content')
<div class="appbar">
    <div>
        <p class="eyebrow">Private maintenance definitions</p>
        <h1>Maintenance · {{ $vessel->name }}</h1>
    </div>
    <div>
        <a href="{{ route('vessels.maintenance.reminders.history', $vessel) }}">View reminder lifecycle history</a>
        <a href="{{ route('vessels.show', $vessel) }}">Back to vessel</a>
    </div>
</div>

<section class="panel" aria-labelledby="add-maintenance-heading">
    <h2 id="add-maintenance-heading">Add schedule</h2>
    @include('partials.errors')
    <form method="post" action="{{ route('vessels.maintenance.store', $vessel) }}">
        @csrf
        @include('maintenance.fields')
        <button class="button">Add schedule</button>
    </form>
</section>

<section class="panel" aria-labelledby="maintenance-list-heading">
    <h2 id="maintenance-list-heading">Recorded schedules</h2>
    <p><small>Reference statuses use owner-entered dates and meter readings. They do not replace manufacturer instructions, inspections, or service requirements.</small></p>
    @forelse($schedules as $item)
        @php($dueState = \App\Support\MaintenanceDueState::evaluate($item, $today))
        <article>
            <p class="eyebrow">{{ ucfirst($item->status) }}</p>
            <h3>{{ $item->name }}</h3>
            <p>Equipment: {{ $item->equipmentItem?->name ?? 'Whole vessel / none selected' }}</p>
            @if($item->basis === 'meter_hours')
                <p>Recurs every {{ $item->interval_hours }} meter h from {{ $item->baseline_hours }} h.</p>
            @elseif($item->basis === 'calendar_days')
                <p>Recurs every {{ $item->interval_days }} calendar days from {{ $item->baseline_on?->format('Y-m-d') }}.</p>
            @else
                <p>Recurs annually on {{ sprintf('%02d-%02d', $item->annual_month, $item->annual_day) }} (month-day).</p>
                @if($item->annual_month === 2 && $item->annual_day === 29)
                    <p>Non-leap-year rule: {{ $leapDayPolicies[$item->leap_day_policy] ?? 'Not selected — reference status unavailable' }}.</p>
                @endif
            @endif
            <p><strong>Reference status: {{ $dueState->label }}.</strong> {{ $dueState->detail }}</p>
            @if($item->reminder_enabled)
                @if($item->basis === 'meter_hours')
                    <p>Stored reminder preference: {{ $item->reminder_lead_hours }} meter h before due.</p>
                @else
                    <p>Stored reminder preference: {{ $item->reminder_lead_days }} calendar days before due.</p>
                @endif
                <p><small>Preference only — no reminder is scheduled or sent.</small></p>
            @else
                <p>Stored reminder preference: disabled.</p>
            @endif
            @foreach($item->reminderOccurrences as $reminder)
                <div class="notice" aria-labelledby="maintenance-reminder-{{ $reminder->id }}">
                    <p id="maintenance-reminder-{{ $reminder->id }}">
                        <strong>In-app maintenance reminder.</strong>
                        @if($reminder->reference_type === 'meter_hours')
                            Reference meter: {{ $reminder->reference_value }} h.
                        @else
                            Reference date: {{ $reminder->reference_value }}.
                        @endif
                    </p>
                    <p><small>This occurrence is private and in-app only. No email, push, SMS, or safety alert was sent.</small></p>
                    <form method="post" action="{{ route('maintenance.reminders.acknowledge', $reminder->id) }}">
                        @csrf
                        <button class="button">Acknowledge reminder for {{ $item->name }}</button>
                    </form>
                    <form method="post" action="{{ route('maintenance.reminders.dismiss', $reminder->id) }}">
                        @csrf
                        <button class="button">Dismiss reminder for {{ $item->name }}</button>
                    </form>
                    <p><small>Dismissing hides this occurrence without recording maintenance completion or deleting its private history.</small></p>
                </div>
            @endforeach
            @if($item->source_kind)
                <p>
                    Recorded source: {{ $sourceKinds[$item->source_kind] ?? 'Unknown source type' }} · {{ $item->source_title }}
                    @if($item->source_reference)
                        · {{ $item->source_reference }}
                    @endif
                    @if($item->source_revision)
                        · revision {{ $item->source_revision }}
                    @endif
                    @if($item->source_section)
                        · {{ $item->source_section }}
                    @endif
                    @if($item->source_published_on)
                        · published {{ $item->source_published_on->format('Y-m-d') }}
                    @endif
                </p>
                @if($item->source_url)
                    <p><a href="{{ $item->source_url }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">Open recorded source for {{ $item->name }} (external)</a></p>
                @endif
                <p><small>This owner-entered citation is not authenticated or confirmed to apply to this vessel or equipment.</small></p>
            @else
                <p>No manufacturer or dealer source reference recorded.</p>
            @endif
            <p>{{ $item->notes ?: 'No private notes recorded.' }}</p>
            <a href="{{ route('maintenance.edit', $item->id) }}">Edit {{ $item->name }}</a>
            <h4>Completion history</h4>
            @forelse($item->completions as $completion)
                <div>
                    <p>
                        {{ $completion->completed_on->format('Y-m-d') }}
                        @if($completion->meter_hours !== null)
                            · {{ $completion->meter_hours }} h
                        @endif
                        · {{ $completion->notes ?: 'No private notes recorded.' }}
                    </p>
                    @if($completion->reversal)
                        <p><strong>Reversed. Original record kept.</strong> Private reason: {{ $completion->reversal->reason }} Record a corrected completion separately.</p>
                    @else
                        <form method="post" action="{{ route('maintenance.completions.reversal.store', $completion->id) }}" aria-label="Reverse the {{ $completion->completed_on->format('Y-m-d') }} completion for {{ $item->name }}">
                            @csrf
                            <label for="reversal-reason-{{ $completion->id }}">Private reversal reason
                                <textarea id="reversal-reason-{{ $completion->id }}" name="reason" rows="2" maxlength="500" required aria-describedby="reversal-guidance-{{ $completion->id }}"></textarea>
                            </label>
                            <p id="reversal-guidance-{{ $completion->id }}"><small>This keeps the original completion and cannot be undone in the current interface. Record a corrected completion separately.</small></p>
                            <button class="button">Reverse completion</button>
                        </form>
                    @endif
                </div>
            @empty
                <p>No completions recorded.</p>
            @endforelse
            <form method="post" action="{{ route('maintenance.completions.store', $item->id) }}" aria-label="Record completion for {{ $item->name }}">
                @csrf
                <div class="formgrid">
                    <label for="completion-date-{{ $item->id }}">Completed on
                        <input id="completion-date-{{ $item->id }}" type="date" name="completed_on" required>
                    </label>
                    <label for="completion-hours-{{ $item->id }}">Meter hours (h){{ $item->basis === 'meter_hours' ? ' — required' : ' — optional' }}
                        <input id="completion-hours-{{ $item->id }}" type="number" name="meter_hours" min="0" max="999999999.9" step="0.1" inputmode="decimal" @required($item->basis === 'meter_hours')>
                    </label>
                </div>
                <label for="completion-notes-{{ $item->id }}">Private completion notes
                    <textarea id="completion-notes-{{ $item->id }}" name="notes" rows="3" maxlength="5000"></textarea>
                </label>
                <p><small>Completion dates are owner-recorded calendar dates. Verify work, meter readings, and service requirements independently.</small></p>
                <button class="button">Record completion</button>
            </form>
        </article>
    @empty
        <p>No maintenance schedules recorded for this vessel.</p>
    @endforelse
</section>
@endsection

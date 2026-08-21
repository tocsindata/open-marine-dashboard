@extends('layouts.app', ['title' => 'Maintenance reminders'])

@section('content')
<div class="appbar">
    <div>
        <p class="eyebrow">Private cross-vessel inbox</p>
        <h1>Maintenance reminders</h1>
    </div>
    <a href="{{ route('dashboard') }}">Back to dashboard</a>
</div>

<section class="panel" aria-labelledby="open-reminders-heading">
    <h2 id="open-reminders-heading">Open in-app occurrences</h2>

    <form method="get" action="{{ route('maintenance.reminders.index') }}">
        <fieldset>
            <legend>Filter open reminders</legend>
            <label for="reminder-vessel">Active vessel</label>
            <select id="reminder-vessel" name="vessel">
                <option value="">All active vessels</option>
                @foreach($activeVessels as $vessel)
                    <option value="{{ $vessel->id }}" @selected($selectedVessel?->id === $vessel->id)>{{ $vessel->name }}</option>
                @endforeach
            </select>

            <label for="reminder-reference-type">Reference type</label>
            <select id="reminder-reference-type" name="reference_type">
                <option value="">All reference types</option>
                @foreach($referenceTypes as $value => $label)
                    <option value="{{ $value }}" @selected($selectedReferenceType === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <button class="button">Apply filters</button>
            <a href="{{ route('maintenance.reminders.index') }}">Clear filters</a>
        </fieldset>
    </form>

    <p class="metric">{{ $filteredOpenReminderCount }}</p>
    <p>{{ $filteredOpenReminderCount }} matching of {{ $openReminderCount }} open reminders across active vessels. The oldest matching occurrences appear first, 25 per page.</p>
    @include('partials.global-reminder-type-summary', ['counts' => $openReminderCountsByType])
    @if($selectedVessel !== null || $selectedReferenceType !== null)
        <p><strong>Active filters:</strong> vessel {{ $selectedVessel?->name ?? 'all active vessels' }}; reference {{ $selectedReferenceType === null ? 'all types' : $referenceTypes[$selectedReferenceType] }}.</p>
    @endif
    <p><small>This private inbox is generated only from owner-entered maintenance references. No email, push, SMS, webhook, escalation, or safety alert was sent. Acknowledging or dismissing is not maintenance completion.</small></p>

    @forelse($occurrences as $occurrence)
        <article aria-labelledby="reminder-inbox-{{ $occurrence->id }}">
            <p class="eyebrow">Open · {{ $occurrence->schedule->vessel->name }}</p>
            <h3 id="reminder-inbox-{{ $occurrence->id }}">{{ $occurrence->schedule->name }}</h3>
            @if($occurrence->reference_type === 'meter_hours')
                <p>Reference meter: {{ $occurrence->reference_value }} h.</p>
            @elseif(in_array($occurrence->reference_type, ['calendar_days', 'annual_date'], true))
                <p>Reference date: {{ $occurrence->reference_value }}.</p>
            @else
                <p>Reference unavailable.</p>
            @endif
            <p>Opened @include('partials.user-time', ['time' => $occurrence->opened_at])</p>
            <form method="post" action="{{ route('maintenance.reminders.acknowledge', $occurrence->id) }}">
                @csrf
                <input type="hidden" name="return_to" value="inbox">
                @if($selectedVessel !== null)
                    <input type="hidden" name="return_vessel" value="{{ $selectedVessel->id }}">
                @endif
                @if($selectedReferenceType !== null)
                    <input type="hidden" name="return_reference_type" value="{{ $selectedReferenceType }}">
                @endif
                <button class="button">Acknowledge {{ $occurrence->schedule->name }} for {{ $occurrence->schedule->vessel->name }}</button>
            </form>
            <form method="post" action="{{ route('maintenance.reminders.dismiss', $occurrence->id) }}">
                @csrf
                <input type="hidden" name="return_to" value="inbox">
                @if($selectedVessel !== null)
                    <input type="hidden" name="return_vessel" value="{{ $selectedVessel->id }}">
                @endif
                @if($selectedReferenceType !== null)
                    <input type="hidden" name="return_reference_type" value="{{ $selectedReferenceType }}">
                @endif
                <button class="button">Dismiss {{ $occurrence->schedule->name }} for {{ $occurrence->schedule->vessel->name }}</button>
            </form>
            <p><small>Dismissal hides this occurrence without recording maintenance completion or deleting its lifecycle history.</small></p>
            <a href="{{ route('vessels.maintenance.reminders.history', $occurrence->schedule->vessel_id) }}">View reminder history for {{ $occurrence->schedule->vessel->name }}</a>
        </article>
    @empty
        <p>No open in-app maintenance reminders match the selected filters.</p>
    @endforelse

    @if($occurrences->hasPages())
        <nav aria-label="Open reminder inbox pages">
            @if($occurrences->onFirstPage())
                <span aria-disabled="true">Previous</span>
            @else
                <a href="{{ $occurrences->previousPageUrl() }}" rel="prev">Previous</a>
            @endif
            <span aria-current="page">Page {{ $occurrences->currentPage() }} of {{ $occurrences->lastPage() }}</span>
            @if($occurrences->hasMorePages())
                <a href="{{ $occurrences->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span aria-disabled="true">Next</span>
            @endif
        </nav>
    @endif
</section>
@endsection

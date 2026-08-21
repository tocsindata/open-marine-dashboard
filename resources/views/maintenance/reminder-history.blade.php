@extends('layouts.app', ['title' => 'Reminder history · '.$vessel->name])

@section('content')
<div class="appbar">
    <div>
        <p class="eyebrow">Private maintenance reminder records</p>
        <h1>Reminder history · {{ $vessel->name }}</h1>
    </div>
    <a href="{{ route('vessels.maintenance.index', $vessel) }}">Back to maintenance</a>
</div>

<section class="panel" aria-labelledby="reminder-history-heading">
    <h2 id="reminder-history-heading">Lifecycle history</h2>
    <p>Only reminder occurrences for this owned, active vessel are shown. No email, push, SMS, webhook, or safety alert was sent.</p>
    <p><small>Acknowledged means you recorded seeing an occurrence. Dismissed hides it without recording maintenance completion. Suppressed occurrences may reopen for the same reference. Superseded occurrences are stale and remain historical. Deletion and export are unavailable until retention rules are approved.</small></p>

    <form method="get" action="{{ route('vessels.maintenance.reminders.history', $vessel) }}">
        <fieldset>
            <legend>Filter lifecycle history</legend>
            <label for="reminder-history-state">Lifecycle state</label>
            <select id="reminder-history-state" name="state">
                <option value="">All lifecycle states</option>
                @foreach($historyStates as $value => $label)
                    <option value="{{ $value }}" @selected($selectedState === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <label for="reminder-history-reference-type">Reference type</label>
            <select id="reminder-history-reference-type" name="reference_type">
                <option value="">All reference types</option>
                @foreach($referenceTypes as $value => $label)
                    <option value="{{ $value }}" @selected($selectedReferenceType === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <button class="button">Apply filters</button>
            <a href="{{ route('vessels.maintenance.reminders.history', $vessel) }}">Clear filters</a>
        </fieldset>
    </form>

    <div aria-labelledby="reminder-history-state-summary">
        <h3 id="reminder-history-state-summary">All records by lifecycle state</h3>
        <p>These vessel-wide counts include zero and do not inherit the current filters.</p>
        <ul>
            @foreach($historyStates as $value => $label)
                <li>
                    <a href="{{ route('vessels.maintenance.reminders.history', ['vessel' => $vessel, 'state' => $value]) }}" aria-label="{{ $label }} lifecycle records for {{ $vessel->name }}: {{ $historyCountsByState[$value] }}">
                        {{ $label }}: {{ $historyCountsByState[$value] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <div aria-labelledby="reminder-history-reference-summary">
        <h3 id="reminder-history-reference-summary">All records by reference type</h3>
        <p>Each link starts a new single-filter view for this owned vessel.</p>
        <ul>
            @foreach($referenceTypes as $value => $label)
                <li>
                    <a href="{{ route('vessels.maintenance.reminders.history', ['vessel' => $vessel, 'reference_type' => $value]) }}" aria-label="{{ $label }} reference records for {{ $vessel->name }}: {{ $historyCountsByReferenceType[$value] }}">
                        {{ $label }}: {{ $historyCountsByReferenceType[$value] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <p class="metric">{{ $occurrences->total() }}</p>
    <p>{{ $occurrences->total() }} matching lifecycle records. The newest matching records appear first, 25 per page.</p>
    @if($selectedState !== null || $selectedReferenceType !== null)
        <p><strong>Active filters:</strong> state {{ $selectedState === null ? 'all states' : $historyStates[$selectedState] }}; reference {{ $selectedReferenceType === null ? 'all types' : $referenceTypes[$selectedReferenceType] }}.</p>
    @endif

    @forelse($occurrences as $occurrence)
        @php
            $statusLabel = match ($occurrence->status) {
                'open' => 'Open',
                'acknowledged' => 'Acknowledged',
                'dismissed' => 'Dismissed',
                'suppressed' => 'Suppressed',
                'superseded' => 'Superseded',
                default => 'Unavailable',
            };
            $lifecycleTime = match ($occurrence->status) {
                'acknowledged' => $occurrence->acknowledged_at,
                'dismissed' => $occurrence->dismissed_at,
                'suppressed' => $occurrence->suppressed_at,
                'superseded' => $occurrence->superseded_at,
                default => null,
            };
        @endphp
        <article aria-labelledby="reminder-history-{{ $occurrence->id }}">
            <p class="eyebrow">{{ $statusLabel }}</p>
            <h3 id="reminder-history-{{ $occurrence->id }}">{{ $occurrence->schedule->name }}</h3>
            @if($occurrence->reference_type === 'meter_hours')
                <p>Reference meter: {{ $occurrence->reference_value }} h.</p>
            @elseif(in_array($occurrence->reference_type, ['calendar_days', 'annual_date'], true))
                <p>Reference date: {{ $occurrence->reference_value }}.</p>
            @else
                <p>Reference unavailable.</p>
            @endif
            <p>Opened @include('partials.user-time', ['time' => $occurrence->opened_at])</p>
            @if($lifecycleTime)
                <p>{{ $statusLabel }} @include('partials.user-time', ['time' => $lifecycleTime])</p>
            @endif
        </article>
    @empty
        <p>No reminder lifecycle history matches these filters for this vessel.</p>
    @endforelse

    @if($occurrences->hasPages())
        <nav aria-label="Reminder history pages">
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

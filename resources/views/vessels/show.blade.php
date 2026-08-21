@extends('layouts.app', ['title' => $vessel->name])

@section('content')
<div class="appbar">
    <div>
        <p class="eyebrow">{{ ucfirst($vessel->status) }}</p>
        <h1>{{ $vessel->name }}</h1>
    </div>
    <div>
        <a href="{{ route('vessels.equipment.index', $vessel) }}">Equipment</a>
        <a href="{{ route('vessels.maintenance.index', $vessel) }}">Maintenance</a>
        <a class="button" href="{{ route('vessels.edit', $vessel) }}">Edit</a>
    </div>
</div>

<section class="grid">
    <article>
        <h2>Identity</h2>
        <dl>
            <dt>Type</dt>
            <dd>{{ $vessel->type ?: 'Not recorded' }}</dd>
            <dt>Make/model</dt>
            <dd>{{ trim($vessel->make.' '.$vessel->model) ?: 'Not recorded' }}</dd>
            <dt>Registration</dt>
            <dd>{{ $vessel->registration_number ?: 'Not recorded' }}</dd>
            <dt>MMSI</dt>
            <dd>{{ $vessel->mmsi ?: 'Not recorded' }}</dd>
        </dl>
    </article>
    <article>
        <h2>Dimensions</h2>
        <dl>
            <dt>Length</dt>
            <dd>{{ \App\Support\UnitPresentation::referenceLength($vessel->length_m, auth()->user()->unit_system) ?: 'Not recorded' }}</dd>
            <dt>Beam</dt>
            <dd>{{ \App\Support\UnitPresentation::referenceLength($vessel->beam_m, auth()->user()->unit_system) ?: 'Not recorded' }}</dd>
            <dt>Draft</dt>
            <dd>{{ $vessel->draft_m !== null ? number_format((float) $vessel->draft_m, 2, '.', '').' m' : 'Not recorded' }}</dd>
        </dl>
        <p><small>Length and beam follow your display-unit preference. Imperial values are approximate and retain metres in parentheses. Draft remains in metres because it is a clearance and safety value.</small></p>
    </article>
    <article>
        <h2>Notes</h2>
        <p>{{ $vessel->notes ?: 'No notes recorded.' }}</p>
    </article>
</section>

<form method="post" action="{{ route('vessels.destroy', $vessel) }}" onsubmit="return confirm('Archive this vessel?')">
    @csrf
    @method('delete')
    <button class="danger">Archive vessel</button>
</form>
@endsection

@extends('layouts.app', ['title' => $vessel->exists ? 'Edit vessel' : 'Add vessel'])

@section('content')
<section class="panel">
    <h1>{{ $vessel->exists ? 'Edit vessel' : 'Add vessel' }}</h1>
    @include('partials.errors')
    <form method="post" action="{{ $vessel->exists ? route('vessels.update', $vessel) : route('vessels.store') }}">
        @csrf
        @if($vessel->exists)
            @method('put')
        @endif
        <div class="formgrid">
            @foreach(['name' => 'Vessel name', 'type' => 'Type', 'make' => 'Make', 'model' => 'Model', 'model_year' => 'Model year', 'home_port' => 'Home port', 'length_m' => 'Length (m)', 'beam_m' => 'Beam (m)', 'draft_m' => 'Draft (m)', 'registration_number' => 'Registration number', 'mmsi' => 'MMSI (9 digits)', 'call_sign' => 'Call sign'] as $field => $label)
                <label>{{ $label }}
                    <input name="{{ $field }}" value="{{ old($field, $vessel->$field) }}" {{ $field === 'name' ? 'required' : '' }} @if(in_array($field, ['length_m', 'beam_m', 'draft_m'], true)) aria-describedby="dimensions-help" @endif>
                </label>
            @endforeach
        </div>
        <p id="dimensions-help"><small>Enter and edit all vessel dimensions in metres. Your unit preference affects only the length and beam reference display; draft remains metric.</small></p>
        <label>Private notes
            <textarea name="notes" rows="5">{{ old('notes', $vessel->notes) }}</textarea>
        </label>
        <button class="button">Save vessel</button>
    </form>
</section>
@endsection

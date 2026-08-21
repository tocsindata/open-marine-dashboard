@extends('layouts.app', ['title' => 'Edit maintenance schedule'])

@section('content')
<section class="panel">
    <p><a href="{{ route('vessels.maintenance.index', $schedule->vessel_id) }}">Back to maintenance</a></p>
    <h1>Edit {{ $schedule->name }}</h1>
    @include('partials.errors')
    <form method="post" action="{{ route('maintenance.update', $schedule->id) }}">
        @csrf
        @method('PATCH')
        @include('maintenance.fields')
        <label for="maintenance-status">Status
            <select id="maintenance-status" name="status" required>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $schedule->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="button">Save schedule</button>
    </form>
</section>
@endsection

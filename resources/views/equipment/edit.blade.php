@extends('layouts.app', ['title' => 'Edit equipment'])

@section('content')
<section class="panel">
    <p><a href="{{ route('vessels.equipment.index', $equipmentItem->vessel_id) }}">Back to equipment</a></p>
    <h1>Edit {{ $equipmentItem->name }}</h1>
    <p id="equipment-guidance">Retiring an item preserves its private inventory record. Verify physical labels, manuals, service limits, and safety requirements before operating or maintaining equipment.</p>
    @include('partials.errors')
    <form method="post" action="{{ route('equipment.update', $equipmentItem->id) }}" aria-describedby="equipment-guidance">
        @csrf
        @method('PATCH')
        @include('equipment.fields')
        <label for="equipment-status">Status
            <select id="equipment-status" name="status" required>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $equipmentItem->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button class="button">Save equipment</button>
    </form>
</section>
@endsection

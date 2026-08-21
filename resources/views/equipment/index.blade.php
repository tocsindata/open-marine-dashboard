@extends('layouts.app', ['title' => 'Equipment · '.$vessel->name])

@section('content')
<div class="appbar">
    <div>
        <p class="eyebrow">Private vessel inventory</p>
        <h1>Equipment · {{ $vessel->name }}</h1>
    </div>
    <a href="{{ route('vessels.show', $vessel) }}">Back to vessel</a>
</div>

<section class="panel" aria-labelledby="add-equipment-heading">
    <h2 id="add-equipment-heading">Add equipment</h2>
    <p id="equipment-guidance">Use this inventory as an owner-maintained reference. Verify physical labels, manuals, service limits, and safety requirements before operating or maintaining equipment.</p>
    @include('partials.errors')
    <form method="post" action="{{ route('vessels.equipment.store', $vessel) }}" aria-describedby="equipment-guidance">
        @csrf
        @include('equipment.fields')
        <button class="button">Add equipment</button>
    </form>
</section>

<section class="panel" aria-labelledby="equipment-list-heading">
    <h2 id="equipment-list-heading">Recorded equipment</h2>
    @forelse($equipmentItems as $item)
        <article>
            <p class="eyebrow">{{ ucfirst($item->status) }} · {{ $categories[$item->category] }}</p>
            <h3>{{ $item->name }}</h3>
            <p>{{ collect([$item->manufacturer, $item->model])->filter()->join(' ') ?: 'Manufacturer and model not recorded' }}</p>
            <p>Serial number: {{ $item->serial_number ?: 'Not recorded' }}</p>
            <p>Installed on: {{ $item->installed_on?->format('Y-m-d') ?? 'Not recorded' }}</p>
            <p>Last serviced on: {{ $item->last_serviced_on?->format('Y-m-d') ?? 'Not recorded' }}</p>
            <p>Warranty expires on: {{ $item->warranty_expires_on?->format('Y-m-d') ?? 'Not recorded' }}</p>
            <p>Current meter hours: {{ $item->meter_hours !== null ? $item->meter_hours.' h' : 'Not recorded' }}</p>
            <p>{{ $item->notes ?: 'No private notes recorded.' }}</p>
            <a href="{{ route('equipment.edit', $item->id) }}">Edit {{ $item->name }}</a>
        </article>
    @empty
        <p>No equipment recorded for this vessel.</p>
    @endforelse
</section>
@endsection

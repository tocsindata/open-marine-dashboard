@extends('layouts.app', ['title' => 'Vessels'])

@section('content')
    <div class="appbar">
        <h1>Vessels</h1>
        <a class="button" href="{{ route('vessels.create') }}">Add vessel</a>
    </div>

    @if($vessels->isEmpty())
        <section class="panel">
            <h2>No active vessels</h2>
            <p>Add a vessel to begin keeping private operational records, or restore one from your archive below.</p>
        </section>
    @else
        <section class="grid" aria-label="Active vessels">
            @foreach($vessels as $vessel)
                <article>
                    <p class="eyebrow">{{ ucfirst($vessel->status) }}</p>
                    <h2><a href="{{ route('vessels.show', $vessel) }}">{{ $vessel->name }}</a></h2>
                    <p>{{ collect([$vessel->make, $vessel->model, $vessel->model_year])->filter()->join(' ') ?: 'Details not recorded' }}</p>
                    <p>{{ $vessel->home_port ?: 'Home port not recorded' }}</p>
                    <p><a href="{{ route('maintenance.reminders.index', ['vessel' => $vessel->id]) }}">View {{ $vessel->open_reminder_count }} open {{ \Illuminate\Support\Str::plural('reminder', $vessel->open_reminder_count) }}</a></p>
                    @include('partials.vessel-reminder-breakdown', ['vessel' => $vessel])
                </article>
            @endforeach
        </section>
    @endif

    <section class="panel" aria-labelledby="archived-vessels-heading">
        <h2 id="archived-vessels-heading">Archived vessels</h2>
        <p>Archiving is reversible and does not erase the vessel or its related records. Permanent deletion is unavailable until retention and backup-expiry rules are approved.</p>

        @if($archivedVessels->isEmpty())
            <p>No archived vessels.</p>
        @else
            <div class="grid">
                @foreach($archivedVessels as $vessel)
                    <article>
                        <p class="eyebrow">Archived</p>
                        <h3>{{ $vessel->name }}</h3>
                        <p>Archived @include('partials.user-time', ['time' => $vessel->deleted_at])</p>
                        <form method="post" action="{{ route('vessels.restore', $vessel->id) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit">Restore {{ $vessel->name }}</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection

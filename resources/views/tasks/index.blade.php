@extends('layouts.app', ['title' => 'Tasks'])

@section('content')
<div class="appbar">
    <h1>Tasks</h1>
    <a href="{{ route('dashboard') }}">Dashboard</a>
</div>

<section class="panel">
    <h2>Add task</h2>
    @include('partials.errors')
    <form method="post" action="{{ route('tasks.store') }}">
        @csrf
        <div class="formgrid">
            <label>Title
                <input name="title" required value="{{ old('title') }}">
            </label>
            <label>Vessel
                <select name="vessel_id">
                    <option value="">General</option>
                    @foreach($vessels as $vessel)
                        <option value="{{ $vessel->id }}">{{ $vessel->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Priority
                <select name="priority">
                    <option>normal</option>
                    <option>low</option>
                    <option>high</option>
                    <option>critical</option>
                </select>
            </label>
            <label for="due-at">Due date and time
                <input id="due-at" type="datetime-local" name="due_at" value="{{ old('due_at') }}" aria-describedby="due-at-help">
            </label>
            <label for="due-time-mode">Interpret entered time as
                <select id="due-time-mode" name="due_time_mode" aria-describedby="due-at-help">
                    <option value="local" @selected(old('due_time_mode', 'local') === 'local')>My time zone ({{ auth()->user()->timezone ?: config('app.timezone') }})</option>
                    <option value="utc" @selected(old('due_time_mode') === 'utc')>UTC</option>
                </select>
            </label>
        </div>
        <p id="due-at-help"><small>Local time is converted to UTC for storage. If a clock change makes the time missing or repeated, Marine asks you to choose another local time or enter the intended instant as UTC.</small></p>
        <label>Description
            <textarea name="description">{{ old('description') }}</textarea>
        </label>
        <button class="button">Add task</button>
    </form>
</section>

<section class="panel">
    <h2>Your tasks</h2>
    @forelse($tasks as $task)
        <div class="task">
            <div>
                <strong>{{ $task->title }}</strong><br>
                <small>
                    {{ $task->vessel?->name ?: 'General' }} · {{ ucfirst($task->priority) }}
                    @if($task->due_at)
                        · @include('partials.user-time', ['time' => $task->due_at, 'format' => 'M j, Y g:i a'])
                    @endif
                </small>
            </div>
            @if($task->status === 'open')
                <form method="post" action="{{ route('tasks.complete', $task) }}">
                    @csrf
                    @method('patch')
                    <button>Complete</button>
                </form>
            @else
                <span>
                    Completed
                    @if($task->completed_at)
                        · @include('partials.user-time', ['time' => $task->completed_at])
                    @endif
                </span>
            @endif
        </div>
    @empty
        <p>No tasks yet.</p>
    @endforelse
</section>
@endsection

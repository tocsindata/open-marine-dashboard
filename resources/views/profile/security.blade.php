@extends('layouts.app', ['title' => 'Account security'])

@section('content')
<div class="appbar">
    <div><p class="eyebrow">Account profile</p><h1>Account security</h1></div>
</div>

<div class="grid">
    <section class="panel" aria-labelledby="account-details-heading">
        <h2 id="account-details-heading">Account details</h2>
        <dl>
            <dt>Name</dt><dd>{{ auth()->user()->name }}</dd>
            <dt>Email</dt><dd>{{ auth()->user()->email }}</dd>
            <dt>Email status</dt><dd>{{ auth()->user()->hasVerifiedEmail() ? 'Verified' : 'Not verified' }}</dd>
        </dl>
        <p><a href="{{ route('profile.edit') }}">Edit profile preferences</a></p>
    </section>

    <section class="panel" aria-labelledby="session-heading">
        <h2 id="session-heading">Browser sessions</h2>
        <p>This browser is signed in. You can end every other browser session without ending this one.</p>
        <p>For your privacy, Marine does not display session identifiers, IP addresses, or browser details here.</p>

        <form method="post" action="{{ route('profile.security.sessions.destroy-others') }}">
            @csrf
            <label for="current-password">Current password
                <input id="current-password" name="password" type="password" autocomplete="current-password" required>
            </label>
            @error('password')<p class="error-text" role="alert">{{ $message }}</p>@enderror
            <button type="submit">End other browser sessions</button>
        </form>
    </section>
</div>

<section class="panel" aria-labelledby="security-activity-heading" style="margin-top:1rem">
    <h2 id="security-activity-heading">Recent security activity</h2>
    <p>Only activity recorded for your account is shown. Network addresses, session values, and request contents are not included.</p>

    @if ($activity->isEmpty())
        <p>No recent account security activity is available.</p>
    @else
        <ul>
            @foreach ($activity as $event)
                <li>
                    <strong>{{ $activityLabels[$event->action] }}</strong>
                    — @include('partials.user-time', ['time' => $event->occurred_at])
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection

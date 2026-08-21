@extends('layouts.app', ['title' => 'Marine data sources'])

@section('content')
<div class="appbar">
    <div><p class="eyebrow">Account profile</p><h1>Marine data sources</h1></div>
</div>

<section class="panel">
    <h2>Free shared sources</h2>
    <p>Marine enables approved public sources for all users. These do not require you to supply an API token. Imports run incrementally so one scheduler call processes no more than one due source step.</p>
    <ul>
        @foreach (collect(config('marine_sources.providers'))->where('tier', 'free_shared') as $provider)
            <li><strong>{{ $provider['name'] }}</strong> — enabled by default. <a href="{{ $provider['documentation'] }}" rel="noopener noreferrer">Provider documentation</a></li>
        @endforeach
    </ul>
</section>

<div class="grid" style="margin-top:1rem">
@foreach ($providers as $key => $provider)
    @php($credential = $credentials->get($key))
    <article class="panel">
        <p class="eyebrow">User-funded provider</p>
        <h2>{{ $provider['name'] }}</h2>
        <p>You obtain and pay for this token directly from the provider. Marine does not receive your provider password or payment details.</p>
        <p><a href="{{ $provider['token_url'] }}" rel="noopener noreferrer">Get a provider token</a> · <a href="{{ $provider['documentation'] }}" rel="noopener noreferrer">Documentation</a></p>

        <form method="post" action="{{ route('profile.sources.store') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="provider_key" value="{{ $key }}">

            <label>API token
                <input type="password" name="token" autocomplete="new-password" placeholder="{{ $credential ? 'Leave blank to keep token ending '.$credential->token_hint : 'Enter provider token' }}">
            </label>
            <p class="muted">Tokens are encrypted at rest and never displayed again after saving.</p>

            <label>Label
                <input name="label" maxlength="100" value="{{ old('label', $credential?->label) }}" placeholder="Optional account label">
            </label>

            <div class="formgrid">
                <label>Default latitude
                    <input name="default_latitude" type="number" step="0.0000001" min="-90" max="90" required value="{{ old('default_latitude', $credential?->default_latitude ?? '49.2827000') }}">
                </label>
                <label>Default longitude
                    <input name="default_longitude" type="number" step="0.0000001" min="-180" max="180" required value="{{ old('default_longitude', $credential?->default_longitude ?? '-123.1207000') }}">
                </label>
                <label>Refresh interval
                    <select name="interval_minutes">
                        @foreach ([15 => '15 minutes', 30 => '30 minutes', 60 => 'Hourly', 180 => 'Every 3 hours', 360 => 'Every 6 hours', 720 => 'Every 12 hours', 1440 => 'Daily'] as $minutes => $label)
                            <option value="{{ $minutes }}" @selected((int) old('interval_minutes', $credential?->interval_minutes ?? 60) === $minutes)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <input type="hidden" name="enabled" value="0">
            <label class="check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $credential?->enabled ?? true))> Enable scheduled imports</label>
            <button class="button">Save encrypted token</button>
        </form>

        @if ($credential)
            <hr>
            <p>Token: ••••{{ $credential->token_hint }}<br>
                Last test: {{ $credential->last_tested_at?->timezone(auth()->user()->timezone)->format('Y-m-d H:i T') ?? 'Not tested' }}
                @if ($credential->last_test_result) — {{ $credential->last_test_result }} @endif
            </p>
            @if ($credential->last_test_message)<p>{{ $credential->last_test_message }}</p>@endif
            <div class="actions">
                <form method="post" action="{{ route('profile.sources.test', $key) }}">@csrf<button>Test connection</button></form>
                <form method="post" action="{{ route('profile.sources.destroy', $key) }}" onsubmit="return confirm('Delete this provider token and its scheduled step?')">@csrf @method('DELETE')<button class="danger">Delete token</button></form>
            </div>
        @endif
    </article>
@endforeach
</div>
@endsection

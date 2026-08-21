@extends('layouts.app', ['title' => 'Profile preferences'])

@section('content')
<div class="appbar">
    <div><p class="eyebrow">Account profile</p><h1>Profile preferences</h1></div>
    <a class="button" href="{{ route('profile.security.show') }}">Account security</a>
</div>

<section class="panel narrow" aria-labelledby="profile-preferences-heading">
    <h2 id="profile-preferences-heading">Personal settings</h2>
    <p id="profile-preferences-help">These settings belong only to your account. Changing them does not change another user or vessel record.</p>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('PATCH')

        <label for="profile-name">Display name
            <input id="profile-name" name="name" maxlength="120" required autocomplete="name" value="{{ old('name', auth()->user()->name) }}" aria-describedby="profile-name-help">
        </label>
        <p id="profile-name-help">Shown only within your signed-in account workflow.</p>
        @error('name')<p class="error-text" role="alert">{{ $message }}</p>@enderror

        <label for="profile-timezone">Time zone
            <select id="profile-timezone" name="timezone" required aria-describedby="profile-timezone-help">
                @foreach ($timezones as $timezone)
                    <option value="{{ $timezone }}" @selected(old('timezone', auth()->user()->timezone ?: config('app.timezone')) === $timezone)>{{ $timezone }}</option>
                @endforeach
            </select>
        </label>
        <p id="profile-timezone-help">Dates and times in account views use this IANA time-zone identifier when that workflow supports local rendering.</p>
        @error('timezone')<p class="error-text" role="alert">{{ $message }}</p>@enderror

        <label for="profile-unit-system">Preferred measurement system
            <select id="profile-unit-system" name="unit_system" required aria-describedby="profile-unit-system-help">
                @foreach ($unitSystems as $value => $label)
                    <option value="{{ $value }}" @selected(old('unit_system', auth()->user()->unit_system) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <p id="profile-unit-system-help">This preference is saved for record entry and future display conversion. Navigation distances remain nautical and safety-sensitive source values keep explicit source units until conversion is implemented and tested.</p>
        @error('unit_system')<p class="error-text" role="alert">{{ $message }}</p>@enderror

        <button type="submit">Save profile preferences</button>
    </form>
</section>
@endsection

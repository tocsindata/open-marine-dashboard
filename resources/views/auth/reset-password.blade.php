@extends('layouts.app', ['title' => 'Reset password'])
@section('content')
<section class="panel narrow">
    <h1>Reset password</h1>
    <p id="password-help">Choose a password with at least 12 characters, including letters and numbers.</p>
    @include('partials.errors')
    <form method="post" action="{{ route('password.update') }}" aria-describedby="password-help">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email
            <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" autofocus>
        </label>
        <label>New password
            <input type="password" name="password" required minlength="12" autocomplete="new-password">
        </label>
        <label>Confirm new password
            <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
        </label>
        <button class="button" type="submit">Reset password</button>
    </form>
</section>
@endsection

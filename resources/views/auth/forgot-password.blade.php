@extends('layouts.app', ['title' => 'Forgot password'])
@section('content')
<section class="panel narrow">
    <h1>Forgot password</h1>
    <p id="recovery-help">Enter your account email. For privacy, the response is the same whether or not an account exists.</p>
    @include('partials.errors')
    @if (session('status'))
        <p class="notice" role="status">{{ session('status') }}</p>
    @endif
    <form method="post" action="{{ route('password.email') }}" aria-describedby="recovery-help">
        @csrf
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        </label>
        <button class="button" type="submit">Send reset link</button>
    </form>
    <p><a href="{{ route('login') }}">Return to log in</a></p>
</section>
@endsection

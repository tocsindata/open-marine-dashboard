@extends('layouts.app', ['title' => 'Verify email'])
@section('content')<section class="panel narrow"><h1>Verify your email</h1><p>Open the verification link sent to your email before accessing private vessel records.</p><form method="post" action="{{ route('verification.send') }}">@csrf<button class="button">Resend verification link</button></form></section>@endsection

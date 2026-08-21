@php
    $displayTimezone = auth()->user()->timezone ?: config('app.timezone');
    $displayTime = $time->copy()->timezone($displayTimezone);
    $utcTime = $time->copy()->utc();
@endphp
<time datetime="{{ $utcTime->format('Y-m-d\\TH:i:s\\Z') }}">{{ $displayTime->format($format ?? 'Y-m-d H:i') }} · UTC{{ $displayTime->format('P') }} · {{ $displayTimezone }}</time>

@extends('layouts.app', ['title' => 'Source diagnostics'])
@section('content')
<div class="appbar"><div><p class="eyebrow">Administration</p><h1>Source diagnostics</h1></div><a href="{{ route('dashboard') }}">Dashboard</a></div>
<p>This page contains operational metadata only. Provider tokens and imported payloads are never displayed.</p>
<section class="panel"><h2>Scheduled source steps</h2><div class="table-scroll"><table><thead><tr><th>Provider / scope</th><th>Owner</th><th>State</th><th>Last result</th><th>Last completed</th><th>Next due</th><th>Failures</th><th>Message</th></tr></thead><tbody>
@foreach($steps as $step)<tr><td><strong>{{ $step->provider_key }}</strong><br>{{ $step->scope_key }}</td><td>{{ $step->user?->email ?? 'Shared' }}</td><td>{{ $step->enabled ? 'Enabled' : 'Disabled' }}@if($step->locked_at)<br>Locked {{ $step->locked_at }}@endif</td><td>{{ $step->last_result ?? 'Never run' }}</td><td>{{ $step->last_completed_at ?? '—' }}</td><td>{{ $step->next_due_at ?? 'Due' }}</td><td>{{ $step->consecutive_failures }}</td><td>{{ $step->last_message ?? '—' }}</td></tr>@endforeach
</tbody></table></div></section>
<section class="panel diagnostics-runs"><h2>Latest 100 import runs</h2><div class="table-scroll"><table><thead><tr><th>Started</th><th>Provider</th><th>Result</th><th>HTTP</th><th>Seen / written</th><th>Message</th></tr></thead><tbody>
@foreach($runs as $run)<tr><td>{{ $run->started_at }}</td><td>{{ $run->provider_key }}</td><td>{{ $run->result }}</td><td>{{ $run->http_status ?? '—' }}</td><td>{{ $run->records_seen }} / {{ $run->records_written }}</td><td>{{ $run->message ?? '—' }}</td></tr>@endforeach
</tbody></table></div></section>
@endsection

@extends('emails._layout')
@section('content')
    <h1 style="font-size:20px;margin:0 0 12px">It works! 🎉</h1>
    <p>This test email was delivered through the sending server <strong>{{ $server->name }}</strong> ({{ $server->typeLabel() }}).</p>
    <p style="font-size:13px;color:#6b7280">Sent at {{ now()->toDayDateTimeString() }} UTC by {{ config('app.name') }}.</p>
@endsection

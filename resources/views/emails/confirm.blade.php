@extends('emails._layout')
@section('content')
    <h1 style="font-size:20px;margin:0 0 12px">Confirm your subscription</h1>
    <p>Please confirm that you want to receive emails from <strong>{{ $list->name }}</strong>.</p>
    <p style="margin:28px 0"><a href="{{ $url }}" style="background:{{ \App\Services\Branding::clientColor($list->workspace) }};color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:600;display:inline-block">Yes, subscribe me</a></p>
    <p style="font-size:13px;color:#6b7280">If you didn't request this, ignore this email and you won't be subscribed.</p>
@endsection
@section('footer'){{ $list->postalAddress() }}@endsection

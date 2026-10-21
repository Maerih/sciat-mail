@extends('emails._layout')
@section('content')
    <p><strong>{{ $subscriber->email }}</strong> unsubscribed from <strong>{{ $list->name }}</strong> on {{ now()->toDayDateTimeString() }} (UTC).</p>
@endsection

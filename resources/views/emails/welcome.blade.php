@extends('emails._layout')
@section('content')
    @if ($body)
        {!! \App\Sending\MergeTags::render($body, \App\Sending\MergeTags::subscriberValues($subscriber) + ['UNSUBSCRIBE_URL' => $unsubscribeUrl, 'LIST_NAME' => $list->name]) !!}
    @else
        <h1 style="font-size:20px;margin:0 0 12px">Welcome{{ $subscriber->field('FIRST_NAME') ? ', '.$subscriber->field('FIRST_NAME') : '' }}!</h1>
        <p>Thanks for subscribing to <strong>{{ $list->name }}</strong>. You'll hear from us soon.</p>
    @endif
@endsection
@section('footer'){{ $list->postalAddress() }} · <a href="{{ $unsubscribeUrl }}" style="color:#9ca3af">Unsubscribe</a>@endsection

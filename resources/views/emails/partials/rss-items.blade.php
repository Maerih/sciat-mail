<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
@foreach ($items as $item)
    <tr><td style="padding:16px 0;border-bottom:1px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif">
        @if ($item['image'])
            <a href="{{ $item['link'] }}"><img src="{{ $item['image'] }}" alt="" width="560" style="display:block;width:100%;max-width:560px;height:auto;border:0;border-radius:6px;margin-bottom:10px"></a>
        @endif
        <a href="{{ $item['link'] }}" style="font-size:18px;font-weight:bold;color:#111827;text-decoration:none">{{ $item['title'] }}</a>
        @if ($item['date'])<div style="font-size:12px;color:#9ca3af;margin-top:2px">{{ $item['date'] }}</div>@endif
        @if ($item['summary'])<p style="margin:8px 0 6px;font-size:14px;line-height:1.6;color:#4b5563">{{ $item['summary'] }}</p>@endif
        <a href="{{ $item['link'] }}" style="font-size:14px;font-weight:bold;color:#4f46e5;text-decoration:none">Read more &rarr;</a>
    </td></tr>
@endforeach
</table>

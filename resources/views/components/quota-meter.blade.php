@props(['label', 'used', 'limit'])
@php $pct = $limit ? min(100, round($used / max(1, $limit) * 100)) : null; @endphp
<div {{ $attributes }}>
    <div class="flex justify-between text-sm"><span class="text-gray-600">{{ $label }}</span>
        <span class="tabular-nums text-gray-900">{{ number_format($used) }}{{ $limit !== null ? ' / '.number_format($limit) : '' }}</span></div>
    @if ($limit !== null)
        <div class="mt-1.5 h-2 rounded-full bg-gray-100"><div @class(['h-2 rounded-full', 'bg-red-500' => $pct >= 100, 'bg-amber-500' => $pct >= 80 && $pct < 100, 'bg-brand-500' => $pct < 80]) style="width: {{ $pct }}%"></div></div>
    @else
        <p class="help">Unlimited</p>
    @endif
</div>

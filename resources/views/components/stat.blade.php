@props(['label', 'value', 'hint' => null, 'tone' => 'gray'])
<div class="card card-body">
    <div class="text-xs font-medium tracking-wide text-gray-500 uppercase">{{ $label }}</div>
    <div @class(['mt-1.5 text-2xl font-semibold tabular-nums', 'text-gray-900' => $tone === 'gray', 'text-red-600' => $tone === 'red', 'text-amber-600' => $tone === 'amber', 'text-emerald-600' => $tone === 'green'])>{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-gray-500">{{ $hint }}</div>
    @endif
</div>

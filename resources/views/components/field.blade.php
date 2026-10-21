@props(['name', 'label' => null, 'help' => null, 'type' => 'text', 'value' => null, 'required' => false])
@php $key = str_replace(['[', ']'], ['.', ''], $name); @endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="f-{{ $key }}" class="label">{{ $label }} @if ($required)<span class="text-red-500">*</span>@endif</label>
    @endif
    @if ($type === 'textarea')
        <textarea id="f-{{ $key }}" name="{{ $name }}" {{ $attributes->except('class')->merge(['class' => 'input', 'rows' => 4]) }}>{{ old($key, $value) }}</textarea>
    @else
        <input id="f-{{ $key }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($key, $value) }}" {{ $attributes->except('class')->merge(['class' => 'input']) }} @required($required)>
    @endif
    @if ($help)
        <p class="help">{!! $help !!}</p>
    @endif
    @error($key)
        <p class="error">{{ $message }}</p>
    @enderror
</div>

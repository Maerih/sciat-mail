@props(['name', 'label', 'help' => null, 'checked' => false])
<label class="flex cursor-pointer items-start gap-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) {{ $attributes->merge(['class' => 'mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500']) }}>
    <span>
        <span class="block text-sm font-medium text-gray-800">{{ $label }}</span>
        @if ($help)
            <span class="block text-xs text-gray-500">{{ $help }}</span>
        @endif
    </span>
</label>

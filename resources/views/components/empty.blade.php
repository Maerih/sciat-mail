@props(['title', 'icon' => 'template'])
<div class="px-6 py-14 text-center">
    <div class="mx-auto flex size-11 items-center justify-center rounded-full bg-brand-50 text-brand-600">
        <x-icon :name="$icon" class="size-5.5" />
    </div>
    <h3 class="mt-3 text-sm font-semibold text-gray-900">{{ $title }}</h3>
    @if ($slot->isNotEmpty())
        <div class="mt-1 text-sm text-gray-500">{{ $slot }}</div>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>

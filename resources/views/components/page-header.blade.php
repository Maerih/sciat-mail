@props(['title', 'subtitle' => null, 'back' => null])
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-1 inline-block text-xs font-medium text-gray-500 hover:text-gray-700">&larr; Back</a>
        @endif
        <h1 class="truncate text-xl font-semibold text-gray-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>

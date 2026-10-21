<x-layouts.guest :title="$title">
    <div class="card card-body p-8 text-center">
        <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><x-icon name="check" class="size-6" /></div>
        <h1 class="mt-4 text-lg font-semibold text-gray-900">{{ $title }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ $message }}</p>
    </div>
</x-layouts.guest>

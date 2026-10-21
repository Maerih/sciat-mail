@foreach (['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'error' => 'border-red-200 bg-red-50 text-red-800', 'info' => 'border-blue-200 bg-blue-50 text-blue-800'] as $key => $classes)
    @if (session($key))
        <div x-data="{ show: true }" x-show="show" class="mb-5 flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm {{ $classes }}">
            <span>{{ session($key) }}</span>
            <button type="button" @click="show = false" class="opacity-60 hover:opacity-100"><x-icon name="x" class="size-4" /></button>
        </div>
    @endif
@endforeach
@if ($errors->any() && ! session('error'))
    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-medium">Please fix the following:</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

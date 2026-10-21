<x-layouts.app :title="$strategy->exists ? 'Edit warmup strategy' : 'New warmup strategy'">
    <x-page-header :title="$strategy->exists ? $strategy->name : 'New warmup strategy'" :back="route('sending.warmup.index')" />
    <form method="POST" action="{{ $strategy->exists ? route('sending.warmup.update', $strategy) : route('sending.warmup.store') }}" class="card card-body max-w-2xl space-y-4">
        @csrf
        @if ($strategy->exists) @method('PUT') @endif
        <x-field name="name" label="Name" :value="$strategy->name" required />
        <div>
            <label class="label">Growth curve</label>
            <select name="curve" class="input">
                @foreach (\App\Models\WarmupStrategy::CURVES as $value => $label)<option value="{{ $value }}" @selected($strategy->curve === $value)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="initial_daily" type="number" min="1" label="Day 1 volume" :value="$strategy->initial_daily" required />
            <x-field name="target_daily" type="number" min="2" label="Target daily volume" :value="$strategy->target_daily" required />
            <x-field name="days" type="number" min="2" max="120" label="Duration (days)" :value="$strategy->days" required />
        </div>
        <div class="flex justify-end"><button class="btn-primary">Save strategy</button></div>
    </form>
</x-layouts.app>

<x-layouts.app :title="'Schedule · '.$campaign->name">
    @include('campaigns.wizard._layout')
    @php
        $tz = $campaign->workspace->timezone;
        $local = $campaign->scheduled_at?->copy()->setTimezone($tz);
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $ranges = old('ranges', $campaign->sending_window ?? collect(range(1, 5))->map(fn ($d) => ['weekday' => $d, 'start' => '09:00', 'end' => '17:00'])->all());
    @endphp
    <form method="POST" action="{{ route('campaigns.schedule.save', $campaign) }}" class="max-w-3xl space-y-6"
          x-data="{ when: @js(old('when', $local ? 'later' : 'now')), windowOn: @js((bool) old('window', $campaign->sending_window ? 1 : 0)), ranges: @js(array_values($ranges)),
                    preset(days, start, end) { this.ranges = days.map(d => ({ weekday: d, start, end })) } }">
        @csrf
        <div class="card card-body space-y-4">
            <h2 class="card-title">When should it go out?</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer gap-3 rounded-lg border p-4" :class="when === 'now' ? 'border-brand-500 bg-brand-50/50' : 'border-gray-200'">
                    <input type="radio" name="when" value="now" x-model="when" class="mt-0.5 text-brand-600"><span><span class="block text-sm font-medium">Send now</span><span class="text-xs text-gray-500">Starts right after launch</span></span></label>
                <label class="flex cursor-pointer gap-3 rounded-lg border p-4" :class="when === 'later' ? 'border-brand-500 bg-brand-50/50' : 'border-gray-200'">
                    <input type="radio" name="when" value="later" x-model="when" class="mt-0.5 text-brand-600"><span><span class="block text-sm font-medium">Schedule for later</span><span class="text-xs text-gray-500">Pick a date and time</span></span></label>
            </div>
            <div x-show="when === 'later'" x-cloak class="grid gap-3 sm:grid-cols-2">
                <x-field name="date" type="date" label="Date" :value="$local?->format('Y-m-d')" :min="now($tz)->format('Y-m-d')" />
                <x-field name="time" type="time" label="Time" :value="$local?->format('H:i') ?? '09:00'" />
                <p class="help sm:col-span-2">Times are in this client's timezone: <strong>{{ $tz }}</strong>.</p>
            </div>
        </div>

        <div class="card card-body space-y-4">
            <h2 class="card-title">Sending window</h2>
            <input type="hidden" name="window" :value="windowOn ? 1 : 0">
            <label class="flex items-start gap-3"><input type="radio" :checked="!windowOn" @change="windowOn = false" class="mt-0.5 text-brand-600"><span class="text-sm"><span class="block font-medium">Send at any time</span><span class="text-xs text-gray-500">No restriction, day or night.</span></span></label>
            <label class="flex items-start gap-3"><input type="radio" :checked="windowOn" @change="windowOn = true" class="mt-0.5 text-brand-600"><span class="text-sm"><span class="block font-medium">Only during these hours</span><span class="text-xs text-gray-500">Sending pauses outside the window and resumes at the next opening ({{ $tz }}).</span></span></label>
            <div x-show="windowOn" x-cloak class="space-y-3 border-l-2 border-gray-100 pl-6">
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary btn-sm" @click="preset([1,2,3,4,5], '09:00', '17:00')">Business hours</button>
                    <button type="button" class="btn-secondary btn-sm" @click="preset([1,2,3,4,5,6,7], '08:00', '22:00')">No nights</button>
                    <button type="button" class="btn-secondary btn-sm" @click="preset([1,2,3,4,5], '00:00', '23:59')">Weekdays only</button>
                </div>
                <template x-for="(r, i) in ranges" :key="i">
                    <div class="flex flex-wrap items-center gap-2">
                        <select class="input w-36" :name="`ranges[${i}][weekday]`" x-model.number="r.weekday">
                            @foreach ($days as $num => $day)<option value="{{ $num }}" :selected="r.weekday == {{ $num }}">{{ $day }}</option>@endforeach
                        </select>
                        <input type="time" class="input w-32" :name="`ranges[${i}][start]`" x-model="r.start"> –
                        <input type="time" class="input w-32" :name="`ranges[${i}][end]`" x-model="r.end">
                        <button type="button" class="btn-ghost btn-sm text-red-600" @click="ranges.splice(i, 1)"><x-icon name="x" class="size-4" /></button>
                    </div>
                </template>
                <button type="button" class="btn-secondary btn-sm" @click="ranges.push({ weekday: 1, start: '09:00', end: '17:00' })"><x-icon name="plus" class="size-4" /> Add range</button>
            </div>
        </div>
        <div class="flex justify-between">
            <a href="{{ route('campaigns.content', $campaign) }}" class="btn-secondary">Back</a>
            <button class="btn-primary">Save & continue</button>
        </div>
    </form>
</x-layouts.app>

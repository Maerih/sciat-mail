{{-- Shared wizard chrome: header + step indicator --}}
<x-page-header :title="$campaign->name" :subtitle="($campaign->isAbTest() ? 'A/B test' : ($campaign->isPlain() ? 'Plain text' : 'HTML')).' campaign · '.ucfirst($campaign->status)" :back="route('campaigns.index')">
    <x-slot:actions>
        <a href="{{ route('campaigns.preview', $campaign) }}" target="_blank" class="btn-secondary"><x-icon name="eye" /> Preview</a>
    </x-slot:actions>
</x-page-header>
<ol class="mb-6 flex flex-wrap gap-2">
    @foreach ($steps as $key => $label)
        @php $index = array_search($key, array_keys($steps)); $currentIndex = array_search($current, array_keys($steps)); @endphp
        <li>
            <a href="{{ route('campaigns.'.$key, $campaign) }}" @class([
                'flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium',
                'bg-brand-600 text-white' => $key === $current,
                'bg-brand-50 text-brand-700 hover:bg-brand-100' => $index < $currentIndex,
                'bg-white text-gray-500 ring-1 ring-gray-200 hover:text-gray-700' => $index > $currentIndex,
            ])>
                <span class="text-xs opacity-75">{{ $index + 1 }}</span> {{ $label }}
            </a>
        </li>
    @endforeach
</ol>

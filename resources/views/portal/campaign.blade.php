<x-layouts.portal :title="$campaign->name">
    <x-page-header :title="$campaign->name" :subtitle="$campaign->subject" :back="route('portal.campaigns')" />
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Delivered" :value="number_format($stats['delivered'])" :hint="'Sent '.($campaign->started_at?->setTimezone($portalWorkspace->timezone)->format('M j, Y H:i') ?? '')" />
        <x-stat label="Open rate" :value="$stats['open_rate'].'%'" :hint="number_format($stats['opened']).' people opened'" tone="green" />
        <x-stat label="Click rate" :value="$stats['click_rate'].'%'" :hint="number_format($stats['clicked']).' people clicked'" tone="green" />
        <x-stat label="Unsubscribed" :value="$stats['unsubscribe_rate'].'%'" :hint="number_format($stats['unsubscribed']).' people'" />
    </div>
    <div class="mt-6 grid gap-6 md:grid-cols-3">
        @foreach ($breakdowns as $title => $rows)
            <div class="card card-body">
                <h2 class="card-title mb-3">{{ $title }}</h2>
                @php $max = max(1, $rows->max() ?? 1); @endphp
                @forelse ($rows as $label => $readers)
                    <div class="mb-2">
                        <div class="flex justify-between text-sm"><span class="text-gray-700">{{ $title === 'Country' ? config('countries.'.$label, $label) : ($label === 'proxy' ? 'Hidden by mail proxy' : ucfirst($label)) }}</span><span class="tabular-nums text-gray-500">{{ number_format($readers) }}</span></div>
                        <div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ round($readers / $max * 100) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No data yet.</p>
                @endforelse
            </div>
        @endforeach
    </div>
    <div class="card mt-6 overflow-x-auto">
        <div class="card-header"><h2 class="card-title">Most clicked links</h2></div>
        @if ($topLinks->isEmpty())
            <p class="px-5 py-6 text-sm text-gray-500">No clicks yet.</p>
        @else
            <table class="table"><tbody>
                @foreach ($topLinks as $link)<tr><td class="max-w-xl truncate">{{ $link->url }}</td><td class="text-right tabular-nums">{{ number_format($link->unique_clicks) }} people</td></tr>@endforeach
            </tbody></table>
        @endif
    </div>
</x-layouts.portal>

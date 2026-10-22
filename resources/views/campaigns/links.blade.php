<x-layouts.app :title="'Links · '.$campaign->name">
    @include('campaigns._header')
    @include('campaigns._report-tabs')
    @php $maxClicks = max(1, (int) $links->max('clicks')); @endphp
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Links clicked" :value="number_format($links->count())" icon="link" />
        <x-stat label="Total clicks" :value="number_format($links->sum('clicks'))" icon="ads_click" tone="green" />
        <x-stat label="Top link share" :value="($links->sum('clicks') ? round($links->first()->clicks / $links->sum('clicks') * 100) : 0).'%'" hint="Of all clicks" icon="leaderboard" tone="amber" />
    </div>
    <div class="card">
        <div class="card-header"><div><h2 class="card-title">Link performance</h2><p class="text-xs text-[var(--muted)]">Every link that was clicked, most popular first</p></div>
            <a href="{{ route('campaigns.log', [$campaign, 'clicks']) }}" class="text-sm text-brand-600 hover:underline">Click log →</a></div>
        @if ($links->isEmpty())
            <x-empty title="No clicks yet" icon="ads_click">Link clicks appear here as soon as recipients start clicking.</x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>URL</th><th class="w-64">Share</th><th class="text-right">Clicks</th><th class="text-right">Unique</th><th class="text-right">Click rate</th><th>Last click</th></tr></thead>
                    <tbody>
                        @foreach ($links as $link)
                            <tr>
                                <td class="max-w-md"><a href="{{ $link->url }}" target="_blank" rel="noopener" class="block truncate font-mono text-xs hover:text-brand-600" title="{{ $link->url }}">{{ $link->url }}</a></td>
                                <td><div class="h-1.5 rounded-full bg-[var(--surface-2)]"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ round($link->clicks / $maxClicks * 100) }}%"></div></div></td>
                                <td class="text-right tabular-nums">{{ number_format($link->clicks) }}</td>
                                <td class="text-right tabular-nums">{{ number_format($link->unique_clicks) }}</td>
                                <td class="text-right tabular-nums">{{ $delivered ? round($link->unique_clicks / $delivered * 100, 2) : 0 }}%</td>
                                <td class="whitespace-nowrap text-[var(--muted)]">{{ \Illuminate\Support\Carbon::parse($link->last_click)->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>

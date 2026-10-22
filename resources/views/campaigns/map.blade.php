<x-layouts.app :title="'Map · '.$campaign->name">
    @include('campaigns._header')
    @include('campaigns._report-tabs')
    @php
        $maxOpens = max(1, (int) $countries->max('opens'));
        $flag = fn (string $code) => strlen($code) === 2 ? mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65) : '🌐';
    @endphp
    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Countries" :value="number_format($countries->count())" icon="public" />
        <x-stat label="Top country" :value="$countries->first()?->name ?? '—'" :hint="$countries->first() ? number_format($countries->first()->opens).' readers' : null" icon="flag" tone="green" />
        <x-stat label="Unknown location" :value="number_format($unknown)" hint="Readers without location data" icon="location_off" />
    </div>
    <div class="card">
        <div class="card-header"><div><h2 class="card-title">Opens and clicks by country</h2><p class="text-xs text-[var(--muted)]">Unique readers per country, based on where emails were opened</p></div></div>
        @if ($countries->isEmpty())
            <x-empty title="No location data yet" icon="public">Countries appear once recipients open the email. Location comes from your CDN or proxy (for example Cloudflare's country header).</x-empty>
        @else
            @foreach ($countries as $country)
                <div class="grid grid-cols-[2rem_1fr_auto] items-center gap-3 border-b border-[var(--line)] px-6 py-3 last:border-0 sm:grid-cols-[2rem_14rem_1fr_auto]">
                    <span class="text-xl leading-none">{{ $flag($country->code) }}</span>
                    <span class="truncate text-sm font-medium">{{ $country->name }}</span>
                    <div class="hidden sm:block">
                        <div class="h-2 rounded-full bg-[var(--surface-2)]"><div class="h-2 rounded-full bg-brand-500" style="width: {{ round($country->opens / $maxOpens * 100) }}%"></div></div>
                    </div>
                    <span class="text-right text-sm tabular-nums"><span class="font-semibold">{{ number_format($country->opens) }}</span> <span class="text-xs text-[var(--muted)]">opens · {{ number_format($country->clicks) }} clicks</span></span>
                </div>
            @endforeach
        @endif
    </div>
</x-layouts.app>

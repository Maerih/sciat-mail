<x-layouts.app title="Integrations">
    <x-page-header title="Integrations" subtitle="Sending services, SMS gateways, deliverability tools and developer connections." />
    <x-hero icon="extension" title="Connect your favourite services" text="Route email through any SMTP service or API, send SMS in Kenya and worldwide, warm up new senders and plug the platform into your own systems."
        :stats="[['link', $connected, 'connected'], ['apps', $total, 'available']]" />

    <div class="space-y-8">
        @foreach ($groups as $group => [$text, $cards])
            <section>
                <div class="mb-3"><h2 class="text-lg font-bold">{{ $group }}</h2><p class="text-sm text-[var(--muted)]">{{ $text }}</p></div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($cards as $card)
                        <a href="{{ $card['url'] }}" class="card card-body group flex flex-col transition hover:border-brand-500/60">
                            <div class="flex items-start justify-between gap-2">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-sm font-bold text-brand-600">{{ strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', $card['name']), 0, 2)) }}</span>
                                <span class="flex flex-wrap justify-end gap-1">
                                    @if ($card['badge'])<span class="badge-gray">{{ $card['badge'] }}</span>@endif
                                    @if ($card['connected'])
                                        <span class="badge-green"><x-icon name="check_circle" class="size-3.5" /> {{ is_int($card['connected']) && $card['connected'] > 1 ? $card['connected'].' connected' : 'Connected' }}</span>
                                    @endif
                                </span>
                            </div>
                            <h3 class="mt-3 font-semibold group-hover:text-brand-600">{{ $card['name'] }}</h3>
                            <p class="mt-1 flex-1 text-sm text-[var(--muted)]">{{ $card['text'] }}</p>
                            <span class="mt-3 text-sm font-medium text-brand-600">{{ $card['connected'] ? 'Manage' : 'Set up' }} →</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.app>

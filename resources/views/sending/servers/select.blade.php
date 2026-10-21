<x-layouts.app title="Add sending server">
    <x-page-header title="Connect a sending service" subtitle="SMTP works everywhere; API drivers report bounces and complaints back through webhooks." :back="route('sending.servers.index')" />
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($types as $key => $type)
            <a href="{{ route('sending.servers.create', ['type' => $key]) }}" class="card card-body block hover:border-brand-500/60 hover:shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-icon name="server" /></span>
                    <span class="text-sm font-semibold text-gray-900">{{ $type['label'] }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-500">{{ $type['description'] }}</p>
                <p class="mt-2 text-xs text-gray-400">{{ $type['webhook'] ? 'Bounce & complaint webhooks' : 'Bounce processing via IMAP in a later phase' }}</p>
            </a>
        @endforeach
    </div>
</x-layouts.app>

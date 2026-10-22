<x-layouts.portal title="Overview">
    <x-page-header :title="'Welcome, '.auth()->user()->name" subtitle="Your email marketing results for the last 30 days." />
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Emails delivered" :value="number_format($sent)" />
        <x-stat label="Open rate" :value="$openRate.'%'" tone="green" />
        <x-stat label="Click rate" :value="$clickRate.'%'" tone="green" />
        <x-stat label="Subscribed contacts" :value="number_format($subscribers)" />
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Recent campaigns</h2><a href="{{ route('portal.campaigns') }}" class="text-sm text-brand-600 hover:underline">All campaigns &rarr;</a></div>
            @forelse ($recent as $campaign)
                <a href="{{ route('portal.campaign', $campaign) }}" class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-3 last:border-0 hover:bg-gray-50">
                    <span class="min-w-0"><span class="block truncate text-sm font-medium text-gray-900">{{ $campaign->name }}</span>
                        <span class="block truncate text-xs text-gray-500">{{ $campaign->subject }}</span></span>
                    <span class="shrink-0 text-xs text-gray-500">{{ $campaign->started_at?->setTimezone($workspace->timezone)->format('M j, Y') }}</span>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-gray-500">No campaigns have been sent yet.</p>
            @endforelse
        </div>
        <div class="card card-body space-y-4">
            <h2 class="card-title">Plan usage</h2>
            <x-quota-meter label="Emails this month" :used="$quota['emails']['used']" :limit="$quota['emails']['limit']" />
            <x-quota-meter label="Contacts" :used="$quota['contacts']['used']" :limit="$quota['contacts']['limit']" />
        </div>
    </div>
</x-layouts.portal>

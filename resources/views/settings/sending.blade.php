<x-layouts.app title="Sending defaults">
    <x-settings-frame :tab="$tab">
        <form method="POST" action="{{ route('settings.sending') }}" class="space-y-6">
            @csrf @method('PUT')
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Compliance</h2><p class="text-xs text-[var(--muted)]">Rules applied to every email sent from the platform.</p></div></div>
                <div class="card-body space-y-4">
                    <x-toggle name="one_click_unsubscribe" label="One-click unsubscribe headers (RFC 8058)" help="Adds List-Unsubscribe and List-Unsubscribe-Post headers. Gmail and Yahoo require them for bulk senders — keep this on." :checked="$values['one_click_unsubscribe']" />
                    <x-toggle name="use_global_blacklist" label="Use the global blacklist" help="Addresses blacklisted for all clients are skipped by every client's campaigns." :checked="$values['use_global_blacklist']" />
                </div>
            </div>
            <div class="card">
                <div class="card-header"><div><h2 class="card-title">Defaults for new lists and campaigns</h2><p class="text-xs text-[var(--muted)]">Pre-selected options; each list or campaign can still change them.</p></div></div>
                <div class="card-body space-y-4">
                    <x-toggle name="default_double_optin" label="Double opt-in on new lists" help="New subscribers must confirm by email before receiving campaigns." :checked="$values['default_double_optin']" />
                    <x-toggle name="default_skip_failed" label="Skip failed messages" help="Keep sending when an individual delivery fails instead of stopping the campaign." :checked="$values['default_skip_failed']" />
                    <x-toggle name="default_track_open" label="Track opens" :checked="$values['default_track_open']" />
                    <x-toggle name="default_track_click" label="Track clicks" :checked="$values['default_track_click']" />
                </div>
            </div>
            <div class="flex justify-end"><button class="btn-primary"><x-icon name="check" class="size-4.5" /> Save settings</button></div>
        </form>
    </x-settings-frame>
</x-layouts.app>

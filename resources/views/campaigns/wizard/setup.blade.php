<x-layouts.app :title="'Setup · '.$campaign->name">
    @include('campaigns.wizard._layout')
    @php
        $weights = $campaign->server_weights ?? [];
        $verified = $domains->filter->isVerified()->pluck('name');
    @endphp
    <form method="POST" action="{{ route('campaigns.setup.save', $campaign) }}" class="space-y-6"
          x-data="{ allServers: @js(old('use_all_servers', empty($weights)) ? true : false), utm: @js((bool) old('utm_enabled', $campaign->utm['enabled'] ?? false)), from: @js(old('from_email', $campaign->from_email)), verified: @js($verified) }">
        @csrf
        <div class="grid items-start gap-6 lg:grid-cols-[1fr_400px]">
        <div class="space-y-6">
        <div class="card card-body space-y-4">
            <h2 class="card-title">Basic information</h2>
            <x-field name="name" label="Campaign name" :value="$campaign->name" required help="Internal only — subscribers don't see it." />
            <div class="-mb-3 flex justify-end"><x-ai.subjects subject="#f-subject" preheader="#f-preheader" :content="$campaign->html ?: $campaign->plain" /></div>
            <x-field name="subject" label="Subject line" :value="$campaign->subject" required maxlength="255" help="Personalise with merge tags, e.g. &#123;&#123; SUBSCRIBER_FIRST_NAME | there &#125;&#125;" />
            <x-field name="preheader" label="Preheader" :value="$campaign->preheader" maxlength="255" help="Preview text shown after the subject in most inboxes." />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="from_name" label="From name" :value="$campaign->from_name" required />
                <div>
                    <x-field name="from_email" type="email" label="From email" :value="$campaign->from_email" required x-model="from" />
                    <p class="help" x-show="from && !verified.includes(from.split('@')[1]?.toLowerCase())">
                        <span class="text-amber-600">This domain is not verified.</span> <a href="{{ route('sending.domains.index') }}" class="text-brand-600 hover:underline">Authenticate it</a> to avoid spam folders.
                    </p>
                </div>
                <x-field name="reply_to" type="email" label="Reply-to email" :value="$campaign->reply_to" class="sm:col-span-2" />
            </div>
        </div>

        <div class="card card-body space-y-3">
            <h2 class="card-title">RSS feed (optional)</h2>
            <p class="-mt-1 text-sm text-gray-500">Pull the latest blog or podcast posts into this email with <code>&#123;&#123; RSS_ITEMS &#125;&#125;</code>, <code>&#123;&#123; RSS_LATEST_TITLE &#125;&#125;</code> or <code>&#123;&#123; RSS_LATEST_URL &#125;&#125;</code>.</p>
            <div class="grid gap-3 sm:grid-cols-4">
                <x-field name="rss_url" type="url" label="Feed URL" :value="$campaign->rss['url'] ?? ''" placeholder="https://example.com/feed" class="sm:col-span-3" />
                <x-field name="rss_count" type="number" min="1" max="20" label="Posts" :value="$campaign->rss['count'] ?? 5" />
            </div>
        </div>

        </div>
        <div class="space-y-6">
        <details class="card group" open>
            <summary class="card-header cursor-pointer list-none"><div><h2 class="card-title">Advanced settings</h2><p class="text-xs text-[var(--muted)]">Tracking, DKIM signing and delivery options</p></div><x-icon name="expand_more" class="size-5 text-[var(--muted)] transition group-open:rotate-180" /></summary>
            <div class="card-body space-y-4">
            <x-toggle name="track_open" label="Track opens" help="Adds an invisible tracking pixel. Apple Mail Privacy Protection can inflate open counts." :checked="$campaign->track_open" />
            <x-toggle name="track_click" label="Track clicks" help="Links are rewritten through your tracking domain." :checked="$campaign->track_click" />
            <x-toggle name="sign_dkim" label="DKIM-sign messages" help="Signs SMTP sends with the verified domain's key. API providers sign with their own keys." :checked="$campaign->sign_dkim" />
            <x-toggle name="spintax" label="Spintax" help="Write {Hi|Hello|Hey} and each recipient gets one option — useful for varied cold outreach. Works in subject, preheader and content; nesting allowed." :checked="$campaign->spintax" />
            <x-toggle name="skip_failed" label="Skip failed messages" help="Keep sending when an individual delivery fails. Off: the first permanent error stops the campaign." :checked="$campaign->skip_failed" />
            </div>
        </details>

        <details class="card group" @if (! empty($weights)) open @endif>
            <summary class="card-header cursor-pointer list-none"><div><h2 class="card-title">Sending servers</h2><p class="text-xs text-[var(--muted)]">By default every active server is used, picked at random per message</p></div><x-icon name="expand_more" class="size-5 text-[var(--muted)] transition group-open:rotate-180" /></summary>
            <div class="card-body space-y-4">
            @if ($servers->isEmpty())
                <p class="text-sm text-amber-700">No active sending server. <a href="{{ route('sending.servers.select') }}" class="underline">Connect one</a> before launching.</p>
            @else
                <input type="hidden" name="use_all_servers" :value="allServers ? 1 : 0">
                <label class="flex items-start gap-3"><input type="checkbox" x-model="allServers" class="mt-0.5 rounded border-gray-300 text-brand-600">
                    <span><span class="block text-sm font-medium">Use all servers</span><span class="block text-xs text-gray-500">Messages rotate randomly across every active server available to this client.</span></span></label>
                <div x-show="!allServers" x-cloak class="space-y-2 border-l-2 border-gray-100 pl-6">
                    <p class="text-xs text-gray-500">Weight decides each server's share of the traffic. 0 excludes a server.</p>
                    @foreach ($servers as $server)
                        <div class="flex items-center gap-3 text-sm">
                            <input type="number" min="0" max="100" name="weights[{{ $server->id }}]" value="{{ old('weights.'.$server->id, $weights[$server->id] ?? (empty($weights) ? 1 : 0)) }}" class="input w-20">
                            <span class="font-medium">{{ $server->name }}</span><span class="text-gray-500">{{ $server->typeLabel() }}</span>
                            @if ($server->hourly_limit)<span class="text-xs text-gray-400">{{ number_format($server->hourly_limit) }}/hour</span>@endif
                        </div>
                    @endforeach
                </div>
            @endif
            </div>
        </details>

        <details class="card group" @if ($campaign->utm['enabled'] ?? false) open @endif>
            <summary class="card-header cursor-pointer list-none"><div><h2 class="card-title">UTM tracking</h2><p class="text-xs text-[var(--muted)]">Attribute website traffic to this campaign in analytics tools</p></div><x-icon name="expand_more" class="size-5 text-[var(--muted)] transition group-open:rotate-180" /></summary>
            <div class="card-body space-y-4">
            <x-toggle name="utm_enabled" label="Add Google Analytics UTM tags" help="Appends utm_* parameters to every link." :checked="$campaign->utm['enabled'] ?? false" x-model="utm" />
            <div x-show="utm" x-cloak class="grid gap-3 sm:grid-cols-2">
                @foreach (['source' => 'newsletter', 'medium' => 'email', 'campaign' => \Illuminate\Support\Str::slug($campaign->name), 'term' => '', 'content' => ''] as $key => $default)
                    <x-field :name="'utm['.$key.']'" :label="'utm_'.$key" :value="$campaign->utm[$key] ?? $default" />
                @endforeach
            </div>
            </div>
        </details>
        </div>
        </div>

        <x-wizard-bar icon="tune" title="Name it and set the sender" text="The from name and subject are what recipients judge first.">
            <a href="{{ route('campaigns.recipients', $campaign) }}" class="btn-secondary"><x-icon name="arrow_back" class="size-4.5" /> Back</a>
            <button class="btn-primary">Save & continue <x-icon name="arrow_forward" class="size-4.5" /></button>
        </x-wizard-bar>
    </form>
</x-layouts.app>

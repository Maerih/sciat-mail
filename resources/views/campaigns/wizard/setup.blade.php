<x-layouts.app :title="'Setup · '.$campaign->name">
    @include('campaigns.wizard._layout')
    @php
        $weights = $campaign->server_weights ?? [];
        $verified = $domains->filter->isVerified()->pluck('name');
    @endphp
    <form method="POST" action="{{ route('campaigns.setup.save', $campaign) }}" class="max-w-3xl space-y-6"
          x-data="{ allServers: @js(old('use_all_servers', empty($weights)) ? true : false), utm: @js((bool) old('utm_enabled', $campaign->utm['enabled'] ?? false)), from: @js(old('from_email', $campaign->from_email)), verified: @js($verified) }">
        @csrf
        <div class="card card-body space-y-4">
            <h2 class="card-title">Basic information</h2>
            <x-field name="name" label="Campaign name" :value="$campaign->name" required help="Internal only — subscribers don't see it." />
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

        <div class="card card-body space-y-4">
            <h2 class="card-title">Tracking & delivery</h2>
            <x-toggle name="track_open" label="Track opens" help="Adds an invisible tracking pixel. Apple Mail Privacy Protection can inflate open counts." :checked="$campaign->track_open" />
            <x-toggle name="track_click" label="Track clicks" help="Links are rewritten through your tracking domain." :checked="$campaign->track_click" />
            <x-toggle name="sign_dkim" label="DKIM-sign messages" help="Signs SMTP sends with the verified domain's key. API providers sign with their own keys." :checked="$campaign->sign_dkim" />
            <x-toggle name="skip_failed" label="Skip failed messages" help="Keep sending when an individual delivery fails. Off: the first permanent error stops the campaign." :checked="$campaign->skip_failed" />
        </div>

        <div class="card card-body space-y-4">
            <h2 class="card-title">Sending servers</h2>
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

        <div class="card card-body space-y-4">
            <x-toggle name="utm_enabled" label="Add Google Analytics UTM tags" help="Appends utm_* parameters to every link." :checked="$campaign->utm['enabled'] ?? false" x-model="utm" />
            <div x-show="utm" x-cloak class="grid gap-3 sm:grid-cols-3">
                @foreach (['source' => 'newsletter', 'medium' => 'email', 'campaign' => \Illuminate\Support\Str::slug($campaign->name), 'term' => '', 'content' => ''] as $key => $default)
                    <x-field :name="'utm['.$key.']'" :label="'utm_'.$key" :value="$campaign->utm[$key] ?? $default" />
                @endforeach
            </div>
        </div>

        <div class="flex justify-between">
            <a href="{{ route('campaigns.recipients', $campaign) }}" class="btn-secondary">Back</a>
            <button class="btn-primary">Save & continue</button>
        </div>
    </form>
</x-layouts.app>

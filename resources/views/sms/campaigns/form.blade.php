<x-layouts.app :title="$campaign->name">
    <x-page-header :title="$campaign->name" subtitle="Bulk SMS · Draft" :back="route('sms.campaigns.index')" />
    @php
        $listData = $lists->mapWithKeys(fn ($l) => [$l->id => ['name' => $l->name, 'phone' => $l->phoneTag(), 'segments' => $l->segments->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values()]]);
    @endphp
    <form method="POST" action="{{ route('sms.campaigns.update', $campaign) }}"
          x-data="{ type: @js(old('audience_type', $campaign->audienceValue('type', 'list'))), list: @js((string) old('list_id', $campaign->audienceValue('list_id', ''))), lists: @js($listData) }">
        @csrf @method('PUT')
        <div class="grid items-start gap-6 lg:grid-cols-[1fr_380px]">
            <div class="space-y-6">
                <div class="card card-body space-y-4">
                    <h2 class="card-title">Message</h2>
                    <x-field name="name" label="Campaign name" :value="$campaign->name" required help="Internal only." />
                    @include('sms._composer', ['name' => 'message', 'value' => old('message', $campaign->message), 'tags' => $tags, 'suffix' => old('opt_out_text', $campaign->opt_out_text)])
                    <div>
                        <x-field name="opt_out_text" label="Opt-out line" :value="$campaign->opt_out_text" x-on:input="$dispatch('sms-suffix', $event.target.value)" help="Added on its own line at the end. Promotional SMS should tell people how to stop — replies with STOP or ACHA opt them out automatically." />
                    </div>
                </div>
                <div class="card card-body space-y-4">
                    <h2 class="card-title">Recipients</h2>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (\App\Models\SmsCampaign::AUDIENCES as $value => $label)
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border p-3 text-sm" :class="type === @js($value) ? 'border-brand-500 bg-brand-50' : 'border-[var(--line)]'">
                                <input type="radio" name="audience_type" value="{{ $value }}" x-model="type" class="mt-0.5 text-brand-600"> {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <div x-show="type !== 'non_openers'" class="grid gap-3 sm:grid-cols-2">
                        <div><label class="label">List</label>
                            <select name="list_id" class="input" x-model="list">
                                @foreach ($lists as $list)<option value="{{ $list->id }}">{{ $list->name }}</option>@endforeach
                            </select>
                            <p class="help" x-show="list && lists[list] && !lists[list].phone"><span class="text-amber-600">This list has no phone field.</span> Add a field of type “Phone number” (or tagged PHONE) under the list's Fields.</p>
                            <p class="help" x-show="list && lists[list] && lists[list].phone">Numbers come from the <code x-text="lists[list]?.phone"></code> field.</p>
                        </div>
                        <div x-show="type === 'segment'"><label class="label">Segment</label>
                            <select name="segment_id" class="input">
                                <template x-for="s in (lists[list]?.segments || [])"><option :value="s.id" x-text="s.name" :selected="s.id == @js($campaign->audienceValue('segment_id'))"></option></template>
                            </select></div>
                    </div>
                    <div x-show="type === 'non_openers'" x-cloak>
                        <label class="label">Email campaign</label>
                        <select name="campaign_id" class="input">
                            @forelse ($emailCampaigns as $email)<option value="{{ $email->id }}" @selected($campaign->audienceValue('campaign_id') == $email->id)>{{ $email->name }} — {{ $email->started_at?->format('M j') }}</option>@empty<option value="">No sent email campaigns yet</option>@endforelse
                        </select>
                        <p class="help">Texts everyone who received that email but hasn't opened it (and has a phone number).</p>
                    </div>
                    <p class="rounded-lg bg-[var(--surface-2)] px-3 py-2 text-sm"><strong>{{ number_format($recipients) }}</strong> contacts match after saving — subscribed, with a valid phone number, not opted out of SMS. Duplicate numbers get one message.</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="card card-body space-y-4">
                    <h2 class="card-title">Sending</h2>
                    <div><label class="label">Provider</label>
                        <select name="sms_provider_id" class="input">
                            <option value="">Default provider</option>
                            @foreach ($providers as $provider)<option value="{{ $provider->id }}" @selected($campaign->sms_provider_id == $provider->id)>{{ $provider->name }} ({{ $provider->typeLabel() }})</option>@endforeach
                        </select>
                        @if ($providers->isEmpty())<p class="help text-amber-600">No provider yet — <a href="{{ route('sms.providers.index') }}" class="underline">connect one</a>.</p>@endif
                    </div>
                    <x-field name="sender_id" label="Sender ID (optional)" :value="$campaign->sender_id" help="Overrides the provider's default sender." />
                    <x-field name="scheduled_at" type="datetime-local" label="Schedule (optional)" :value="$campaign->scheduled_at?->setTimezone($campaign->workspace->timezone)->format('Y-m-d\TH:i')" :help="'Leave empty to send immediately. Timezone: '.$campaign->workspace->timezone" />
                    @if ($remaining !== null)<p class="text-xs text-[var(--muted)]">{{ number_format($remaining) }} SMS left this month for this client.</p>@endif
                </div>
                <div class="card card-body space-y-2">
                    <button class="btn-secondary w-full"><x-icon name="save" class="size-4.5" /> Save draft</button>
                    <button name="next" value="launch" class="btn-primary w-full" onclick="return confirm('Send this SMS campaign now (or schedule it)?')"><x-icon name="send" class="size-4.5" /> Save and send</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>

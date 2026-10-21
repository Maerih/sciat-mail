<x-layouts.app title="API reference">
    <x-page-header title="API reference" subtitle="REST API v1 · JSON in, JSON out · 120 requests per minute per token" :back="route('api.settings')" />
    @php
        $listUid = $list?->uid ?? 'LIST_UID';
        $auth = "-H 'Authorization: Bearer YOUR_TOKEN' -H 'Accept: application/json'";
        $endpoints = [
            ['Account', [
                ['GET', '/me', 'Which client the token belongs to.', null],
            ]],
            ['Lists', [
                ['GET', '/lists', 'All lists with their fields.', null],
                ['POST', '/lists', 'Create a list.', '{"name":"Customers","from_name":"Acme","from_email":"news@acme.com","double_optin":false}'],
                ['GET', '/lists/{list}', 'One list with subscriber counts.', null],
                ['PATCH', '/lists/{list}', 'Update name, sender or opt-in settings.', '{"name":"VIP customers"}'],
                ['DELETE', '/lists/{list}', 'Delete a list and its contacts.', null],
            ]],
            ['Contacts', [
                ['GET', '/lists/{list}/subscribers?status=subscribed&tag=vip&updated_since=2026-10-01', 'Paginated contacts (per_page up to 500).', null],
                ['POST', '/lists/{list}/subscribers', 'Add or update a contact by email. Double opt-in lists email a confirmation unless "confirmed": true.', '{"email":"ann@example.com","fields":{"FIRST_NAME":"Ann"},"tags":["shop"]}'],
                ['POST', '/lists/{list}/subscribers/bulk', 'Up to 1,000 contacts per call.', '{"subscribers":[{"email":"a@example.com"},{"email":"b@example.com","fields":{"FIRST_NAME":"Bo"}}]}'],
                ['GET', '/lists/{list}/subscribers/{uid-or-email}', 'One contact.', null],
                ['PATCH', '/lists/{list}/subscribers/{uid-or-email}', 'Change email, fields or replace tags.', '{"fields":{"PLAN":"gold"}}'],
                ['POST', '/lists/{list}/subscribers/{uid-or-email}/tags', 'Add and/or remove tags (fires tag automations).', '{"add":["customer"],"remove":["lead"]}'],
                ['POST', '/lists/{list}/subscribers/{uid-or-email}/unsubscribe', 'Unsubscribe a contact.', null],
                ['DELETE', '/lists/{list}/subscribers/{uid-or-email}', 'Delete a contact.', null],
            ]],
            ['Campaigns', [
                ['GET', '/campaigns?status=done', 'Campaigns of this client.', null],
                ['POST', '/campaigns', 'Create a campaign; "send": true launches it (now or at scheduled_at).', '{"name":"October news","subject":"Hi {{ SUBSCRIBER_FIRST_NAME | there }}","from_name":"Acme","from_email":"news@acme.com","html":"<p>Hello</p>","lists":["'.$listUid.'"],"send":false}'],
                ['GET', '/campaigns/{campaign}', 'Campaign with delivery, open, click, bounce and unsubscribe stats.', null],
                ['POST', '/campaigns/{campaign}/launch', 'Launch a draft (runs the pre-flight checks).', null],
                ['POST', '/campaigns/{campaign}/pause', 'Pause sending.', null],
                ['POST', '/campaigns/{campaign}/resume', 'Resume a paused campaign.', null],
            ]],
            ['Automations', [
                ['GET', '/automations', 'Automations and their run counts.', null],
                ['POST', '/automations/{automation}/enroll', 'Start an active automation for a contact on its list.', '{"email":"ann@example.com"}'],
            ]],
            ['Transactional email', [
                ['POST', '/send', 'Send one email now: receipts, password resets, alerts. Use "html", "text" or a template uid; "data" fills {{ TAGS }}.', '{"to":"ann@example.com","subject":"Your order #{{ ORDER }}","template":"TEMPLATE_UID","data":{"order":"1042"}}'],
            ]],
        ];
    @endphp

    <div class="card card-body mb-6 space-y-2 text-sm text-gray-600">
        <p><strong>Base URL:</strong> <code>{{ $base }}</code></p>
        <p><strong>Authentication:</strong> create a token under <a href="{{ route('api.settings') }}" class="text-brand-600 hover:underline">API & webhooks</a> and send it as <code>Authorization: Bearer sm_…</code>. Tokens only see the client they belong to.</p>
        <p><strong>Errors:</strong> 401 bad token · 404 not found · 409 not allowed in the current state · 422 validation (<code>{"message","errors":{field:[…]}}</code>) · 429 rate limited.</p>
    </div>

    <div class="space-y-6">
        @foreach ($endpoints as [$group, $items])
            <div class="card">
                <div class="card-header"><h2 class="card-title">{{ $group }}</h2></div>
                <div class="divide-y divide-gray-100">
                    @foreach ($items as [$method, $path, $description, $body])
                        @php
                            $path = str_replace(['{list}', '{automation}'], [$listUid, $automation?->uid ?? 'AUTOMATION_UID'], $path);
                            $curl = "curl -X {$method} '{$base}{$path}' {$auth}".($body ? " -H 'Content-Type: application/json' -d '".$body."'" : '');
                        @endphp
                        <div class="px-5 py-4" x-data="copy(@js($curl))">
                            <div class="flex flex-wrap items-center gap-2">
                                <span @class(['badge font-mono', 'bg-emerald-50 text-emerald-700' => $method === 'GET', 'bg-blue-50 text-blue-700' => $method === 'POST', 'bg-amber-50 text-amber-700' => $method === 'PATCH', 'bg-red-50 text-red-700' => $method === 'DELETE'])>{{ $method }}</span>
                                <code class="text-sm">{{ $path }}</code>
                                <button type="button" class="ml-auto text-xs text-brand-600 hover:underline" @click="copy()" x-text="copied ? 'Copied' : 'Copy curl'"></button>
                            </div>
                            <p class="mt-1 text-sm text-gray-600">{{ $description }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="card card-body text-sm text-gray-600">
            <h2 class="card-title mb-2">Webhook payloads</h2>
            <p>Every event is a JSON POST signed with your endpoint's secret:</p>
            <pre class="code mt-2 whitespace-pre-wrap">POST /your-endpoint
X-SM-Event: email.clicked
X-SM-Signature: sha256=&lt;hex HMAC-SHA256 of the raw body&gt;

{"event":"email.clicked","occurred_at":"2026-10-22T09:15:02+00:00",
 "data":{"delivery_uid":"…","email":"ann@example.com","url":"https://…",
         "campaign":{"uid":"…","name":"October news","automation":false},"subscriber_uid":"…"}}</pre>
            <p class="mt-2">Respond with any 2xx status within 10 seconds. Events: {{ implode(', ', array_keys(\App\Models\Webhook::EVENTS)) }}.</p>
        </div>
    </div>
</x-layouts.app>

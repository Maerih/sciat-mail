<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\Automation;
use App\Models\Webhook;
use App\Models\Workspace;
use App\Services\Webhooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApiSettingsController extends Controller
{
    public function index(): View
    {
        $workspace = Workspace::current();

        return view('api.index', [
            'tokens' => ApiToken::where('workspace_id', $workspace->id)->with('workspace')->latest()->get(),
            'webhooks' => Webhook::forWorkspace()->with(['deliveries' => fn ($q) => $q->limit(10)])->latest()->get(),
            'newToken' => session('new_token'),
        ]);
    }

    public function docs(): View
    {
        return view('api.docs', [
            'base' => url('/api/v1'),
            'list' => Workspace::current()->lists()->first(),
            'automation' => Automation::forWorkspace()->first(),
        ]);
    }

    public function storeToken(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120']);
        [, $plain] = ApiToken::issue(Workspace::current(), $data['name'], $request->user());

        // shown exactly once
        return back()->with('new_token', $plain)->with('success', 'Token created. Copy it now — it will not be shown again.');
    }

    public function destroyToken(int $token): RedirectResponse
    {
        ApiToken::where('workspace_id', Workspace::current()->id)->whereKey($token)->firstOrFail()->delete();

        return back()->with('success', 'Token revoked. Integrations using it stop working immediately.');
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:500', 'starts_with:https://,http://'],
            'events' => 'required|array|min:1',
            'events.*' => [Rule::in(array_keys(Webhook::EVENTS))],
        ]);
        Webhook::create($data + ['workspace_id' => Workspace::current()->id]);

        return back()->with('success', 'Webhook endpoint added.');
    }

    public function toggleWebhook(Webhook $webhook): RedirectResponse
    {
        $webhook->forceFill(['active' => ! $webhook->active, 'failures' => 0])->save();

        return back()->with('success', $webhook->active ? 'Webhook enabled.' : 'Webhook disabled.');
    }

    public function testWebhook(Webhook $webhook): RedirectResponse
    {
        abort_unless($webhook->active, 409, 'Enable the webhook first.');
        Webhooks::deliver($webhook, 'webhook.test', ['message' => 'Test event from '.config('app.name'), 'webhook_uid' => $webhook->uid]);

        return back()->with('success', 'Test event queued — see the delivery log below.');
    }

    public function destroyWebhook(Webhook $webhook): RedirectResponse
    {
        $webhook->delete();

        return back()->with('success', 'Webhook removed.');
    }
}

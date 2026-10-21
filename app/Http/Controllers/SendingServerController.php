<?php

namespace App\Http\Controllers;

use App\Models\SendingServer;
use App\Models\Workspace;
use App\Sending\SendFailed;
use App\Services\SystemMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SendingServerController extends Controller
{
    public function index(): View
    {
        $servers = Workspace::current()->availableServers()->orderBy('name')->get();

        return view('sending.servers.index', compact('servers'));
    }

    public function select(): View
    {
        return view('sending.servers.select', ['types' => SendingServer::TYPES]);
    }

    public function create(Request $request): View
    {
        $type = $request->query('type');
        abort_unless(isset(SendingServer::TYPES[$type]), 404);

        return view('sending.servers.form', ['server' => new SendingServer(['type' => $type, 'status' => 'active']), 'type' => SendingServer::TYPES[$type]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type');
        abort_unless(isset(SendingServer::TYPES[$type]), 422);

        $server = SendingServer::create($this->validated($request, $type) + [
            'type' => $type,
            'workspace_id' => $request->user()->isAdmin() && $request->boolean('shared') ? null : Workspace::current()->id,
        ]);

        return redirect()->route('sending.servers.edit', $server)->with('success', 'Sending server added. Send a test to confirm it works.');
    }

    public function edit(SendingServer $server): View
    {
        return view('sending.servers.form', ['server' => $server, 'type' => SendingServer::TYPES[$server->type]]);
    }

    public function update(Request $request, SendingServer $server): RedirectResponse
    {
        $this->authorizeChange($request, $server);
        $data = $this->validated($request, $server->type, $server);
        $server->update($data);

        return back()->with('success', 'Sending server saved.');
    }

    public function destroy(Request $request, SendingServer $server): RedirectResponse
    {
        $this->authorizeChange($request, $server);
        $server->delete();

        return redirect()->route('sending.servers.index')->with('success', 'Sending server deleted.');
    }

    public function test(Request $request, SendingServer $server, SystemMailer $mailer): RedirectResponse
    {
        $data = $request->validate(['to' => 'required|email', 'from' => 'required|email']);

        try {
            $mailer->send(Workspace::current(), $data['to'], 'Test email from '.config('app.name'),
                view('emails.server-test', ['server' => $server])->render(), $data['from'], config('app.name'),
                server: $server, kind: 'test');
            $server->forceFill(['last_tested_at' => now(), 'last_error' => null])->save();

            return back()->with('success', "Test email sent to {$data['to']}. Check the inbox (and spam folder).");
        } catch (SendFailed $e) {
            $server->forceFill(['last_tested_at' => now(), 'last_error' => $e->getMessage()])->save();

            return back()->with('error', 'Sending failed: '.$e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, string $type, ?SendingServer $existing = null): array
    {
        $definition = SendingServer::TYPES[$type];
        $rules = [
            'name' => 'required|string|max:190',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'hourly_limit' => 'nullable|integer|min:1',
            'daily_limit' => 'nullable|integer|min:1',
            'default_from_email' => 'nullable|email',
        ];

        foreach ($definition['fields'] as $key => $field) {
            $fieldRules = $field['rules'];
            // Secrets left blank on edit keep their stored value
            if ($existing && ! empty($field['secret'])) {
                $fieldRules = str_replace('required', 'nullable', $fieldRules);
            }
            $rules['config.'.$key] = $fieldRules;
        }

        $data = $request->validate($rules);
        $config = $existing?->config ?? [];
        foreach ($definition['fields'] as $key => $field) {
            $value = $data['config'][$key] ?? null;
            if ($existing && ! empty($field['secret']) && blank($value)) {
                continue;
            }
            $config[$key] = $value;
        }
        $data['config'] = $config;

        return $data;
    }

    protected function authorizeChange(Request $request, SendingServer $server): void
    {
        abort_if($server->isShared() && ! $request->user()->isAdmin(), 403, 'Only administrators can change shared servers.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\Workspace;
use App\Sms\PhoneNumber;
use App\Sms\SmsManager;
use App\Sms\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SmsProviderController extends Controller
{
    public function index(): View
    {
        return view('sms.providers.index', [
            'providers' => SmsProvider::availableTo(Workspace::current())->orderByRaw('workspace_id is null')->orderBy('name')->get(),
            'types' => SmsManager::TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        $type = $request->query('type');
        abort_unless(isset(SmsManager::TYPES[$type]), 404);

        return view('sms.providers.form', ['provider' => new SmsProvider(['type' => $type, 'status' => 'active', 'per_second' => 10]), 'type' => SmsManager::TYPES[$type]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type');
        abort_unless(isset(SmsManager::TYPES[$type]), 422);

        $provider = SmsProvider::create($this->validated($request, $type) + [
            'type' => $type,
            'workspace_id' => $request->user()->isAdmin() && $request->boolean('shared') ? null : Workspace::current()->id,
        ]);
        if (! SmsProvider::availableTo(Workspace::current())->whereKeyNot($provider->id)->where('is_default', true)->exists()) {
            $provider->forceFill(['is_default' => true])->save();
        }

        return redirect()->route('sms.providers.edit', $provider)->with('success', 'SMS provider added. Send a test message to confirm it works.');
    }

    public function edit(SmsProvider $provider): View
    {
        return view('sms.providers.form', ['provider' => $provider, 'type' => SmsManager::TYPES[$provider->type]]);
    }

    public function update(Request $request, SmsProvider $provider): RedirectResponse
    {
        $this->authorizeChange($request, $provider);
        $provider->update($this->validated($request, $provider->type, $provider));

        return back()->with('success', 'SMS provider saved.');
    }

    public function destroy(Request $request, SmsProvider $provider): RedirectResponse
    {
        $this->authorizeChange($request, $provider);
        $provider->delete();

        return redirect()->route('sms.providers.index')->with('success', 'SMS provider deleted.');
    }

    public function makeDefault(Request $request, SmsProvider $provider): RedirectResponse
    {
        SmsProvider::availableTo(Workspace::current())->whereKeyNot($provider->id)->when(! $request->user()->isAdmin(), fn ($q) => $q->whereNotNull('workspace_id'))
            ->update(['is_default' => false]);
        $provider->forceFill(['is_default' => true])->save();

        return back()->with('success', $provider->name.' is now the default SMS provider.');
    }

    public function test(Request $request, SmsProvider $provider, SmsService $sms): RedirectResponse
    {
        $data = $request->validate(['to' => 'required|string|max:32', 'message' => 'nullable|string|max:480']);
        $phone = PhoneNumber::normalize($data['to'], Workspace::current()->sms_country);
        if (! $phone) {
            return back()->withErrors(['to' => 'Enter a valid number, e.g. 0712 345 678 or +254712345678.'])->withInput();
        }
        $message = SmsMessage::create([
            'workspace_id' => Workspace::current()->id, 'sms_provider_id' => $provider->id, 'direction' => 'out', 'source' => 'test',
            'phone' => $phone, 'body' => $data['message'] ?: 'Test message from '.config('app.name').'. Your SMS provider works!', 'parts' => 1,
        ]);
        $ok = $sms->deliver($message->load('provider', 'workspace'));
        $provider->forceFill(['last_tested_at' => now(), 'last_error' => $ok ? null : $message->error])->save();

        return $ok
            ? back()->with('success', "Test SMS sent to {$phone}.")
            : back()->with('error', 'Sending failed: '.$message->error);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, string $type, ?SmsProvider $existing = null): array
    {
        $definition = SmsManager::TYPES[$type];
        $rules = [
            'name' => 'required|string|max:120',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sender_id' => 'nullable|string|max:32',
            'per_second' => 'required|integer|between:1,500',
        ];
        foreach ($definition['fields'] as $key => $field) {
            $rules['config.'.$key] = $existing && ! empty($field['secret']) ? str_replace('required', 'nullable', $field['rules']) : $field['rules'];
        }
        $data = $request->validate($rules);

        $config = $existing?->config ?? [];
        foreach ($definition['fields'] as $key => $field) {
            $value = $data['config'][$key] ?? null;
            if (! ($existing && ! empty($field['secret']) && blank($value))) {
                $config[$key] = $value; // blank secrets on edit keep the stored value
            }
        }
        $data['config'] = $config;

        return $data;
    }

    protected function authorizeChange(Request $request, SmsProvider $provider): void
    {
        abort_if($provider->isShared() && ! $request->user()->isAdmin(), 403, 'Only administrators can change shared SMS providers.');
    }
}

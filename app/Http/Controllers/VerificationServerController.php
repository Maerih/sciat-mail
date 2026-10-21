<?php

namespace App\Http\Controllers;

use App\Jobs\VerifySubscribers;
use App\Models\MailList;
use App\Models\VerificationServer;
use App\Models\Workspace;
use App\Services\Verification\EmailVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VerificationServerController extends Controller
{
    public function index(): View
    {
        return view('sending.verification', [
            'servers' => VerificationServer::forWorkspace()->orderByDesc('is_default')->get(),
            'active' => VerificationServer::activeFor(Workspace::current()),
        ]);
    }

    public function store(Request $request, EmailVerifier $verifier): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'provider' => ['required', Rule::in(array_keys(VerificationServer::PROVIDERS))],
            'api_key' => [Rule::requiredIf(fn () => VerificationServer::PROVIDERS[$request->provider]['needs_key'] ?? false), 'nullable', 'string', 'max:255'],
            'test_email' => 'nullable|email',
        ]);

        $server = VerificationServer::create(collect($data)->except('test_email')->all() + [
            'workspace_id' => Workspace::current()->id,
            'is_default' => ! VerificationServer::forWorkspace()->exists(),
        ]);

        if (! empty($data['test_email'])) {
            [$status, $reason] = $verifier->verify($data['test_email'], $server);

            return back()->with($status === 'unknown' ? 'error' : 'success', "Saved. Test result for {$data['test_email']}: {$status}".($reason ? " ({$reason})" : '').'.');
        }

        return back()->with('success', 'Verification service saved.');
    }

    public function makeDefault(VerificationServer $verification): RedirectResponse
    {
        VerificationServer::forWorkspace()->update(['is_default' => false]);
        $verification->forceFill(['is_default' => true])->save();

        return back()->with('success', $verification->name.' is now used for verification.');
    }

    public function destroy(VerificationServer $verification): RedirectResponse
    {
        $verification->delete();

        return back()->with('success', 'Verification service removed.');
    }

    public function verifyList(Request $request, MailList $list): RedirectResponse
    {
        abort_if($list->verification_job_status === 'running', 409, 'Verification is already running for this list.');
        $list->forceFill(['verification_job_status' => 'queued'])->save();
        VerifySubscribers::dispatch($list->id, null, $request->boolean('recheck'));

        return back()->with('success', 'Verification started. Results appear on the subscribers as they are checked.');
    }
}

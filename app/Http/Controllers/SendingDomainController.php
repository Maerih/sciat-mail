<?php

namespace App\Http\Controllers;

use App\Models\SendingDomain;
use App\Models\WarmupStrategy;
use App\Models\Workspace;
use App\Services\DomainVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SendingDomainController extends Controller
{
    public function index(): View
    {
        return view('sending.domains.index', ['domains' => SendingDomain::forWorkspace()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['name' => strtolower(trim(preg_replace('#^https?://|/.*$#i', '', (string) $request->name)))]);
        $data = $request->validate([
            'name' => ['required', 'regex:/^(?!-)([a-z0-9-]{1,63}\.)+[a-z]{2,63}$/',
                Rule::unique('sending_domains')->where('workspace_id', Workspace::current()->id)],
        ], ['name.regex' => 'Enter a domain like example.com — no protocol or path.']);

        $domain = SendingDomain::create($data + ['workspace_id' => Workspace::current()->id]);

        return redirect()->route('sending.domains.show', $domain)->with('success', 'Domain added. Publish the DNS records below, then verify.');
    }

    public function show(SendingDomain $domain): View
    {
        $spfIncludes = Workspace::current()->availableServers()->where('status', 'active')->get()
            ->map->spfInclude()->filter()->unique()->values();

        $strategies = WarmupStrategy::availableTo(Workspace::current())->orderBy('name')->get();

        return view('sending.domains.show', compact('domain', 'spfIncludes', 'strategies'));
    }

    public function warmup(Request $request, SendingDomain $domain): RedirectResponse
    {
        $data = $request->validate([
            'warmup_strategy_id' => ['nullable', 'integer', Rule::in(WarmupStrategy::availableTo(Workspace::current())->pluck('id')->all())],
            'warmup_started_on' => 'nullable|date',
        ]);
        $domain->forceFill([
            'warmup_strategy_id' => $data['warmup_strategy_id'] ?? null,
            'warmup_started_on' => empty($data['warmup_strategy_id']) ? null : ($data['warmup_started_on'] ?? now(Workspace::current()->timezone)->toDateString()),
        ])->save();

        return back()->with('success', empty($data['warmup_strategy_id']) ? 'Domain warmup turned off.' : 'Domain warmup scheduled.');
    }

    public function verify(SendingDomain $domain, DomainVerifier $verifier): RedirectResponse
    {
        $verifier->verifySending($domain);

        return back()->with($domain->isVerified() ? 'success' : 'error', $domain->isVerified()
            ? 'Domain verified. Campaigns from this domain will be DKIM-signed.'
            : 'Some records were not found yet. DNS changes can take up to 48 hours to propagate.');
    }

    public function destroy(SendingDomain $domain): RedirectResponse
    {
        $domain->delete();

        return redirect()->route('sending.domains.index')->with('success', 'Domain removed.');
    }
}

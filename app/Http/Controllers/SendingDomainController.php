<?php

namespace App\Http\Controllers;

use App\Models\SendingDomain;
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

        return view('sending.domains.show', compact('domain', 'spfIncludes'));
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

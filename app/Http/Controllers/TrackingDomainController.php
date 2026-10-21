<?php

namespace App\Http\Controllers;

use App\Models\TrackingDomain;
use App\Models\Workspace;
use App\Services\DomainVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackingDomainController extends Controller
{
    public function index(): View
    {
        return view('sending.tracking-domains.index', [
            'domains' => TrackingDomain::forWorkspace()->orderBy('host')->get(),
            'target' => TrackingDomain::cnameTarget(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['host' => strtolower(trim(preg_replace('#^https?://|/.*$#i', '', (string) $request->host)))]);
        $data = $request->validate([
            'host' => ['required', 'regex:/^(?!-)([a-z0-9-]{1,63}\.)+[a-z]{2,63}$/', 'unique:tracking_domains,host'],
            'scheme' => 'required|in:http,https',
        ], ['host.regex' => 'Enter a hostname like links.example.com.']);

        TrackingDomain::create($data + ['workspace_id' => Workspace::current()->id]);

        return back()->with('success', 'Tracking domain added. Create the CNAME record, then verify.');
    }

    public function verify(TrackingDomain $trackingDomain, DomainVerifier $verifier): RedirectResponse
    {
        $verifier->verifyTracking($trackingDomain);

        return back()->with($trackingDomain->verified ? 'success' : 'error', $trackingDomain->verified
            ? 'Tracking domain verified.'
            : 'The CNAME record was not found yet. DNS can take a while to propagate.');
    }

    public function makeDefault(TrackingDomain $trackingDomain): RedirectResponse
    {
        TrackingDomain::forWorkspace()->update(['is_default' => false]);
        $trackingDomain->forceFill(['is_default' => true])->save();

        return back()->with('success', 'Default tracking domain updated.');
    }

    public function destroy(TrackingDomain $trackingDomain): RedirectResponse
    {
        $trackingDomain->delete();

        return back()->with('success', 'Tracking domain removed.');
    }
}

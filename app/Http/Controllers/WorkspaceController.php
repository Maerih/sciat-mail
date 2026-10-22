<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Quota;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index()
    {
        $workspaces = Workspace::withCount(['lists', 'campaigns'])->orderBy('name')->get()
            ->each(fn (Workspace $w) => $w->setAttribute('usage', Quota::summary($w)));

        return view('workspaces.index', compact('workspaces'));
    }

    public function create()
    {
        return view('workspaces.create');
    }

    public function store(Request $request)
    {
        $workspace = Workspace::create($this->validated($request));
        $request->user()->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()->route('dashboard')->with('success', "Client “{$workspace->name}” created.");
    }

    public function switch(Request $request, string $workspace)
    {
        $workspace = Workspace::where('uid', $workspace)->firstOrFail();
        $request->user()->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()->route('dashboard');
    }

    public function edit()
    {
        return view('workspaces.edit', [
            'workspace' => Workspace::current(),
            'portalUsers' => User::where('role', 'client')->where('workspace_id', Workspace::current()->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $workspace = Workspace::current();
        $data = $this->validated($request) + $request->validate([
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'website' => 'nullable|url|max:255',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);
        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('branding/'.$workspace->uid, 'public');
        } elseif ($request->boolean('remove_logo')) {
            $data['logo_path'] = null;
        }
        unset($data['logo']);
        if ($request->user()->isAdmin()) {
            // limits are commercial terms: only administrators may change them
            $limits = $request->validate(['monthly_email_limit' => 'nullable|integer|min:0', 'contact_limit' => 'nullable|integer|min:0']);
            $workspace->forceFill(['monthly_email_limit' => $limits['monthly_email_limit'] ?? null, 'contact_limit' => $limits['contact_limit'] ?? null]);
        }
        $workspace->update($data);

        return back()->with('success', 'Client settings saved.');
    }

    public function destroy(Request $request)
    {
        $request->validate(['confirm' => 'required|in:'.Workspace::current()->name], ['confirm.in' => 'Type the workspace name exactly to confirm.']);
        Workspace::current()->delete();
        $request->user()->forceFill(['current_workspace_id' => null])->save();

        return redirect()->route('dashboard')->with('success', 'Client deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'timezone' => 'required|timezone',
            'company' => 'nullable|string|max:190',
            'address' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email',
        ]);
    }
}

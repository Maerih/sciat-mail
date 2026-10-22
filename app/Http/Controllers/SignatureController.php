<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SignatureController extends Controller
{
    public function index(): View
    {
        return view('sending.signatures.index', ['signatures' => Signature::forWorkspace()->orderByDesc('is_default')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $workspace = Workspace::current();

        return view('sending.signatures.form', ['signature' => new Signature([
            'name' => 'Default signature',
            'html' => '<table cellpadding="0" cellspacing="0" style="font-family:Arial,sans-serif;font-size:14px;color:#374151">'
                ."\n  <tr><td style=\"padding-top:16px;border-top:1px solid #e5e7eb\">"
                ."\n    <strong style=\"font-size:15px;color:#111827\">Your Name</strong><br>"
                ."\n    Marketing Manager · ".e($workspace->company ?: $workspace->name).'<br>'
                ."\n    <a href=\"".e($workspace->website ?: 'https://example.com').'" style="color:#16a34a">'.e(preg_replace('#^https?://#', '', $workspace->website ?: 'example.com')).'</a>'
                ."\n  </td></tr>\n</table>",
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $signature = new Signature($this->validated($request));
        $signature->workspace_id = Workspace::current()->id;
        $signature->save();
        if ($request->boolean('is_default') || Signature::forWorkspace()->count() === 1) {
            $signature->makeDefault();
        }

        return redirect()->route('sending.signatures.index')->with('success', 'Signature saved.');
    }

    public function edit(Signature $signature): View
    {
        return view('sending.signatures.form', compact('signature'));
    }

    public function update(Request $request, Signature $signature): RedirectResponse
    {
        $signature->update($this->validated($request));
        if ($request->boolean('is_default')) {
            $signature->makeDefault();
        }

        return redirect()->route('sending.signatures.index')->with('success', 'Signature saved.');
    }

    public function destroy(Signature $signature): RedirectResponse
    {
        $wasDefault = $signature->is_default;
        $signature->delete();
        if ($wasDefault) {
            Signature::forWorkspace()->oldest('id')->first()?->makeDefault();
        }

        return redirect()->route('sending.signatures.index')->with('success', 'Signature deleted.');
    }

    public function makeDefault(Signature $signature): RedirectResponse
    {
        $signature->makeDefault();

        return back()->with('success', '“'.$signature->name.'” is now the default signature.');
    }

    /** @return array{name: string, html: string} */
    protected function validated(Request $request): array
    {
        return $request->validate(['name' => 'required|string|max:120', 'html' => 'required|string|max:20000']);
    }
}

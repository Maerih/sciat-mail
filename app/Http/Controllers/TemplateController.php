<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\Workspace;
use App\Sending\MergeTags;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        $templates = Template::availableTo(Workspace::current())
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->q.'%'))
            ->when($request->owner === 'mine', fn ($q) => $q->whereNotNull('workspace_id'))
            ->when($request->owner === 'system', fn ($q) => $q->whereNull('workspace_id'))
            ->orderByRaw('workspace_id is null')->orderBy('name')->paginate(24)->withQueryString();

        return view('templates.index', compact('templates'));
    }

    public function create(Request $request): View
    {
        $base = $request->filled('from') ? Template::availableTo(Workspace::current())->where('uid', $request->from)->first() : null;

        return view('templates.form', [
            'template' => new Template(['name' => $base ? $base->name.' (copy)' : '', 'html' => $base?->html ?? '', 'category' => 'custom']),
            'tags' => MergeTags::catalogue(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $base = $request->filled('from') ? Template::availableTo(Workspace::current())->where('uid', $request->from)->first() : null;
        $data = $this->validated($request);
        // keep the source design when the copied HTML was not changed
        $template = Template::create($data + [
            'workspace_id' => Workspace::current()->id,
            'builder_json' => $base && $base->html === $data['html'] ? $base->builder_json : null,
        ]);

        return redirect()->route('templates.edit', $template)->with('success', 'Template saved.');
    }

    public function edit(Template $template): View|RedirectResponse
    {
        if ($template->isSystem()) {
            return redirect()->route('templates.create', ['from' => $template->uid]);
        }

        return view('templates.form', ['template' => $template, 'tags' => MergeTags::catalogue()]);
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        abort_if($template->isSystem(), 403);
        $data = $this->validated($request);
        if ($data['html'] !== $template->html) {
            $data['builder_json'] = null; // hand-edited HTML replaces the drag & drop design
        }
        $template->update($data);

        return back()->with('success', 'Template saved.');
    }

    public function destroy(Template $template): RedirectResponse
    {
        abort_if($template->isSystem(), 403);
        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template deleted.');
    }

    public function preview(Template $template): Response
    {
        return response($template->html)->header('Content-Security-Policy', "script-src 'none'");
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:190',
            'html' => 'required|string',
        ]);
    }
}

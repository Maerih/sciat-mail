<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\Campaign;
use App\Models\Template;
use App\Models\Workspace;
use App\Sending\MergeTags;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Drag-and-drop editing for templates and campaign / automation email content.
 */
class BuilderController extends Controller
{
    public function template(Template $template): View|RedirectResponse
    {
        if ($template->isSystem()) {
            return redirect()->route('templates.create', ['from' => $template->uid]);
        }

        return $this->editor($template->name, $template->html, $template->builder_json,
            route('templates.builder.save', $template), route('templates.edit', $template), MergeTags::catalogue());
    }

    public function saveTemplate(Request $request, Template $template): JsonResponse
    {
        abort_if($template->isSystem(), 403);
        $template->forceFill($this->validated($request))->save();

        return response()->json(['ok' => true]);
    }

    public function campaign(Campaign $campaign): View
    {
        $this->guard($campaign);
        $back = $campaign->isAutomationEmail()
            ? $this->automationEmailUrl($campaign)
            : route('campaigns.content', $campaign);

        return $this->editor($campaign->name, $campaign->html, $campaign->builder_json,
            route('campaigns.builder.save', $campaign), $back, MergeTags::catalogue($campaign->audiences()->first()?->list ?? Automation::find($campaign->automation_id)?->list));
    }

    public function saveCampaign(Request $request, Campaign $campaign): JsonResponse
    {
        $this->guard($campaign);
        $campaign->forceFill($this->validated($request) + ['type' => $campaign->isPlain() ? 'regular' : $campaign->type])->save();

        return response()->json(['ok' => true]);
    }

    /** Image uploads from the builder's asset manager; returned URLs are absolute for email clients. */
    public function upload(Request $request): JsonResponse
    {
        $request->validate(['files' => 'required|array|max:10', 'files.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120']);
        $workspace = Workspace::current();

        $urls = collect($request->file('files'))->map(function ($file) use ($workspace) {
            $path = $file->storeAs('uploads/'.$workspace->uid, Str::random(24).'.'.$file->extension(), 'public');

            return url(Storage::disk('public')->url($path));
        });

        return response()->json(['data' => $urls->map(fn ($src) => ['src' => $src, 'type' => 'image'])->all()]);
    }

    /** @return array{html: string, builder_json: string} */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'html' => 'required|string|max:2000000',
            'project' => 'required|json|max:5000000',
        ]);

        return ['html' => $data['html'], 'builder_json' => $data['project']];
    }

    protected function editor(string $title, ?string $html, ?string $project, string $saveUrl, string $backUrl, array $tags): View
    {
        $assets = collect(Storage::disk('public')->files('uploads/'.Workspace::current()->uid))
            ->map(fn ($p) => url(Storage::disk('public')->url($p)))->values();

        return view('builder', [
            'title' => $title,
            'html' => $html,
            'project' => $project,
            'saveUrl' => $saveUrl,
            'backUrl' => $backUrl,
            'tags' => $tags,
            'assets' => $assets,
            'uploadUrl' => route('builder.upload'),
        ]);
    }

    protected function guard(Campaign $campaign): void
    {
        abort_unless($campaign->isEditable() || $campaign->isAutomationEmail(), 409, 'This campaign can no longer be edited.');
    }

    protected function automationEmailUrl(Campaign $campaign): string
    {
        $automation = Automation::findOrFail($campaign->automation_id);
        $step = collect($automation->flow()->steps())->search(fn ($s) => ($s['campaign_id'] ?? null) === $campaign->id);

        return $step ? route('automations.email', [$automation, $step]) : route('automations.show', $automation);
    }
}

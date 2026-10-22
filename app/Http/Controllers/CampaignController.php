<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Workspace;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $campaigns = Campaign::forWorkspace()->regular()
            ->when(in_array($request->status, Campaign::STATUSES, true), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('subject', 'like', '%'.$request->q.'%')))
            ->orderBy($request->sort === 'name' ? 'name' : 'created_at', $request->sort === 'name' ? 'asc' : 'desc')
            ->paginate(20)->withQueryString();

        $statusCounts = Campaign::forWorkspace()->regular()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('campaigns.index', compact('campaigns', 'statusCounts'));
    }

    public function create(): View
    {
        return view('campaigns.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['type' => 'required|in:regular,plain,ab', 'name' => 'nullable|string|max:190']);
        $workspace = Workspace::current();

        $campaign = Campaign::create([
            'workspace_id' => $workspace->id,
            'type' => $data['type'],
            'name' => ($data['name'] ?? null) ?: 'Untitled campaign '.now()->setTimezone($workspace->timezone)->format('M j, H:i'),
            'from_name' => $workspace->company ?: $workspace->name,
            'from_email' => $workspace->contact_email,
            'reply_to' => $workspace->contact_email,
        ] + Campaign::defaultOptions());

        if ($campaign->isAbTest()) {
            $campaign->variants()->createMany([['label' => 'A'], ['label' => 'B']]);
        }

        return redirect()->route('campaigns.recipients', $campaign);
    }

    /** Ends an A/B test early, with the chosen variant or the current leader. */
    public function winner(Request $request, Campaign $campaign, CampaignService $service): RedirectResponse
    {
        abort_unless($campaign->isAbTest() && ! $campaign->winner_variant_id && in_array($campaign->status, ['testing', 'paused'], true), 409);
        $variant = $request->filled('variant') ? $campaign->variants()->findOrFail($request->integer('variant')) : null;
        $winner = $service->pickWinner($campaign, $variant);

        return back()->with('success', "Variant {$winner->label} wins — sending it to the remaining recipients.");
    }

    /** Report overview for launched campaigns; drafts go back into the wizard. */
    public function show(Campaign $campaign): View|RedirectResponse
    {
        if (in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return redirect()->route('campaigns.confirm', $campaign);
        }

        $campaign->load('audiences.list', 'audiences.segment');

        $topLinks = $campaign->events()->where('type', 'click')
            ->selectRaw('url, count(*) as clicks, count(distinct delivery_id) as unique_clicks')
            ->groupBy('url')->orderByDesc('clicks')->limit(10)->get();

        $bounceTypes = $campaign->events()->where('type', 'bounce')
            ->selectRaw('subtype, count(*) as c')->groupBy('subtype')->pluck('c', 'subtype');

        // Unique readers per dimension, from opens and clicks (a click implies an open)
        $breakdown = fn (string $column) => $campaign->events()->toBase()->whereIn('type', ['open', 'click'])->whereNotNull($column)
            ->selectRaw("{$column} as label, count(distinct delivery_id) as readers")->groupBy($column)->orderByDesc('readers')->limit(8)->pluck('readers', 'label');

        return view('campaigns.show', [
            'breakdowns' => ['Email app' => $breakdown('client'), 'Device' => $breakdown('device'), 'Operating system' => $breakdown('os'), 'Country' => $breakdown('country')],
            'campaign' => $campaign->load('variants'),
            'variantStats' => $campaign->isAbTest() ? $campaign->variantStats() : [],
            'stats' => $campaign->stats(),
            'topLinks' => $topLinks,
            'bounceTypes' => $bounceTypes,
        ]);
    }

    public function pause(Campaign $campaign, CampaignService $service): RedirectResponse
    {
        $service->pause($campaign);

        return back()->with('success', 'Campaign paused.');
    }

    public function resume(Campaign $campaign, CampaignService $service): RedirectResponse
    {
        $service->resume($campaign);

        return back()->with('success', 'Campaign resumed.');
    }

    public function cancel(Campaign $campaign, CampaignService $service): RedirectResponse
    {
        abort_if(in_array($campaign->status, ['done', 'cancelled'], true), 409);
        $service->cancel($campaign);

        return back()->with('success', 'Campaign cancelled. Remaining recipients were skipped.');
    }

    public function duplicate(Campaign $campaign): RedirectResponse
    {
        abort_if($campaign->isAutomationEmail(), 404);
        $copy = $campaign->duplicate();

        return redirect()->route('campaigns.setup', $copy)->with('success', 'Campaign duplicated.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        abort_if($campaign->isAutomationEmail(), 404);
        abort_if($campaign->isRunning(), 409, 'Pause or cancel the campaign before deleting it.');
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign deleted.');
    }
}

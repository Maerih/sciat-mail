<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\Workspace;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class CampaignController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return CampaignResource::collection(Campaign::forWorkspace()->regular()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(50));
    }

    public function show(Campaign $campaign): CampaignResource
    {
        abort_if($campaign->isAutomationEmail(), 404);

        return new CampaignResource($campaign);
    }

    /**
     * Creates a regular or plain-text campaign for one or more lists. Pass "send": true to
     * launch right away (or at "scheduled_at"); otherwise it stays a draft.
     */
    public function store(Request $request, CampaignService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'subject' => 'required|string|max:255',
            'preheader' => 'nullable|string|max:255',
            'from_name' => 'required|string|max:190',
            'from_email' => 'required|email',
            'reply_to' => 'nullable|email',
            'html' => 'required_without:plain|nullable|string',
            'plain' => 'required_without:html|nullable|string',
            'lists' => 'required|array|min:1',
            'lists.*' => 'string',
            'exclude_lists' => 'sometimes|array',
            'exclude_lists.*' => 'string',
            'track_open' => 'sometimes|boolean',
            'track_click' => 'sometimes|boolean',
            'scheduled_at' => 'nullable|date|after:now',
            'send' => 'sometimes|boolean',
        ]);

        $resolve = fn (array $uids) => collect($uids)->map(fn ($uid) => MailList::forWorkspace()->where('uid', $uid)->first()
            ?? throw ValidationException::withMessages(['lists' => "Unknown list: {$uid}"]));
        $include = $resolve($data['lists']);
        $exclude = $resolve($data['exclude_lists'] ?? []);

        $campaign = Campaign::create(collect($data)->only(['name', 'subject', 'preheader', 'from_name', 'from_email', 'reply_to', 'html', 'plain', 'scheduled_at'])->all() + [
            'workspace_id' => Workspace::current()->id,
            'type' => empty($data['html']) ? 'plain' : 'regular',
            'track_open' => $data['track_open'] ?? true,
            'track_click' => $data['track_click'] ?? true,
        ]);
        $include->each(fn ($l) => $campaign->audiences()->create(['mail_list_id' => $l->id]));
        $exclude->each(fn ($l) => $campaign->audiences()->create(['mail_list_id' => $l->id, 'exclude' => true]));

        if ($request->boolean('send')) {
            $this->launch($campaign->load('audiences.segment'), $service);
        }

        return (new CampaignResource($campaign->fresh()))->response()->setStatusCode(201);
    }

    public function launchCampaign(Campaign $campaign, CampaignService $service): CampaignResource
    {
        abort_unless(! $campaign->isAutomationEmail() && $campaign->isEditable(), 409, 'Only draft, scheduled or paused campaigns can be launched.');
        $this->launch($campaign->load('audiences.segment'), $service);

        return new CampaignResource($campaign->fresh());
    }

    public function pause(Campaign $campaign, CampaignService $service): CampaignResource
    {
        $service->pause($campaign);

        return new CampaignResource($campaign->fresh());
    }

    public function resume(Campaign $campaign, CampaignService $service): CampaignResource
    {
        $service->resume($campaign);

        return new CampaignResource($campaign->fresh());
    }

    protected function launch(Campaign $campaign, CampaignService $service): void
    {
        $errors = collect($service->preflight($campaign))->where('level', 'error');
        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages(['campaign' => $errors->pluck('message')->all()]);
        }
        $service->launch($campaign);
    }
}

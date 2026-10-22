<?php

namespace App\Http\Controllers;

use App\Jobs\LaunchSmsCampaign;
use App\Jobs\SendSmsBatch;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\Setting;
use App\Models\SmsCampaign;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\Workspace;
use App\Sending\MergeTags;
use App\Services\Quota;
use App\Sms\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SmsCampaignController extends Controller
{
    public function index(Request $request): View
    {
        $campaigns = SmsCampaign::forWorkspace()->with('provider:id,name')
            ->when(in_array($request->status, ['draft', 'scheduled', 'sending', 'done'], true), fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(20)->withQueryString();
        $month = SmsMessage::forWorkspace()->where('direction', 'out')->where('created_at', '>=', now()->startOfMonth());

        return view('sms.campaigns.index', [
            'campaigns' => $campaigns,
            'stats' => [
                'sent' => (clone $month)->whereIn('status', ['sent', 'delivered'])->count(),
                'delivered' => (clone $month)->where('status', 'delivered')->count(),
                'failed' => (clone $month)->where('status', 'failed')->count(),
                'replies' => SmsMessage::forWorkspace()->where('direction', 'in')->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'hasProvider' => SmsProvider::defaultFor(Workspace::current()) !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = Workspace::current();
        $campaign = new SmsCampaign([
            'name' => 'SMS campaign '.now($workspace->timezone)->format('M j, H:i'),
            'sms_provider_id' => SmsProvider::defaultFor($workspace)?->id,
            'opt_out_text' => Setting::get('sms_opt_out_text', 'Reply STOP to opt out'),
            'audience' => array_filter([
                'type' => $request->query('campaign') ? 'non_openers' : 'list',
                'list_id' => MailList::forWorkspace()->value('id'),
                'campaign_id' => Campaign::forWorkspace()->where('uid', $request->query('campaign'))->value('id'),
            ]),
        ]);
        $campaign->workspace_id = $workspace->id;
        $campaign->save();

        return redirect()->route('sms.campaigns.edit', $campaign);
    }

    public function edit(SmsCampaign $campaign): View|RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('sms.campaigns.show', $campaign);
        }
        $lists = MailList::forWorkspace()->with('segments:id,mail_list_id,uid,name', 'fields')->orderBy('name')->get();

        return view('sms.campaigns.form', [
            'campaign' => $campaign,
            'lists' => $lists,
            'providers' => SmsProvider::availableTo(Workspace::current())->where('status', 'active')->orderBy('name')->get(),
            'emailCampaigns' => Campaign::forWorkspace()->whereIn('status', ['done', 'sending', 'paused'])->whereNull('automation_id')->latest('started_at')->limit(50)->get(['id', 'name', 'started_at']),
            'tags' => MergeTags::catalogue($lists->firstWhere('id', $campaign->audienceValue('list_id'))),
            'recipients' => $campaign->recipientQuery()->count(),
            'remaining' => Quota::smsRemaining(Workspace::current()),
        ]);
    }

    public function update(Request $request, SmsCampaign $campaign, SmsService $sms): RedirectResponse
    {
        abort_unless($campaign->isEditable(), 409);
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'sms_provider_id' => ['nullable', 'integer', Rule::in(SmsProvider::availableTo(Workspace::current())->pluck('id')->all())],
            'sender_id' => 'nullable|string|max:32',
            'message' => 'nullable|string|max:1600',
            'opt_out_text' => 'nullable|string|max:80',
            'audience_type' => ['required', Rule::in(array_keys(SmsCampaign::AUDIENCES))],
            'list_id' => ['nullable', 'integer', Rule::exists('mail_lists', 'id')->where('workspace_id', Workspace::current()->id)],
            'segment_id' => 'nullable|integer',
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')->where('workspace_id', Workspace::current()->id)],
            'scheduled_at' => 'nullable|date',
        ]);

        $campaign->fill([
            'name' => $data['name'], 'sms_provider_id' => $data['sms_provider_id'] ?? null, 'sender_id' => $data['sender_id'] ?? null,
            'message' => $data['message'] ?? null, 'opt_out_text' => $data['opt_out_text'] ?? null,
            'audience' => array_filter(['type' => $data['audience_type'], 'list_id' => $data['list_id'] ?? null, 'segment_id' => $data['segment_id'] ?? null, 'campaign_id' => $data['campaign_id'] ?? null]),
            'scheduled_at' => filled($data['scheduled_at'] ?? null) ? Carbon::parse($data['scheduled_at'], Workspace::current()->timezone)->utc() : null,
        ])->save();
        if ($list = MailList::find($data['list_id'] ?? null)) {
            $sms->syncPhones($list);
        }

        if ($request->input('next') !== 'launch') {
            return back()->with('success', 'SMS campaign saved.');
        }

        return $this->launch($campaign);
    }

    public function launch(SmsCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->isEditable(), 409);
        $problems = $this->problems($campaign);
        if ($problems) {
            return back()->with('error', implode(' ', $problems));
        }

        if ($campaign->scheduled_at?->isFuture()) {
            $campaign->forceFill(['status' => 'scheduled'])->save();

            return redirect()->route('sms.campaigns.show', $campaign)->with('success', 'Scheduled for '.$campaign->scheduled_at->setTimezone($campaign->workspace->timezone)->format('D j M, H:i').'.');
        }
        $campaign->forceFill(['status' => 'queued'])->save();
        LaunchSmsCampaign::dispatch($campaign->id);

        return redirect()->route('sms.campaigns.show', $campaign)->with('success', 'SMS campaign is sending.');
    }

    public function show(SmsCampaign $campaign): View
    {
        return view('sms.campaigns.show', [
            'campaign' => $campaign->load('provider'),
            'stats' => $campaign->stats(),
            'messages' => $campaign->messages()->latest('id')->paginate(50),
            'optedOut' => SmsMessage::forWorkspace()->where('direction', 'in')->where('created_at', '>=', $campaign->started_at ?? now())
                ->whereIn('phone', $campaign->messages()->select('phone'))->whereRaw("upper(trim(body)) in ('".implode("','", SmsService::OPT_OUT_KEYWORDS)."')")->count(),
        ]);
    }

    public function pause(SmsCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status === 'sending', 409);
        $campaign->forceFill(['status' => 'paused'])->save();

        return back()->with('success', 'Paused. Messages not yet sent will wait.');
    }

    public function resume(SmsCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->status === 'paused', 409);
        $campaign->forceFill(['status' => 'sending'])->save();
        $campaign->messages()->where('status', 'queued')->orderBy('id')->pluck('id')->chunk(SmsService::BATCH)
            ->each(fn ($ids) => SendSmsBatch::dispatch($ids->values()->all(), $campaign->id));

        return back()->with('success', 'Resumed.');
    }

    public function cancel(SmsCampaign $campaign): RedirectResponse
    {
        abort_unless(in_array($campaign->status, ['scheduled', 'queued', 'sending', 'paused'], true), 409);
        $campaign->messages()->where('status', 'queued')->delete();
        $campaign->forceFill(['status' => $campaign->started_at ? 'done' : 'draft', 'finished_at' => $campaign->started_at ? now() : null])->save();

        return back()->with('success', 'Cancelled.');
    }

    public function destroy(SmsCampaign $campaign): RedirectResponse
    {
        abort_if(in_array($campaign->status, ['queued', 'sending'], true), 409);
        $campaign->delete();

        return redirect()->route('sms.campaigns.index')->with('success', 'SMS campaign deleted.');
    }

    /** @return string[] */
    protected function problems(SmsCampaign $campaign): array
    {
        return array_values(array_filter([
            blank($campaign->message) ? 'Write a message.' : null,
            ! ($campaign->provider ?? SmsProvider::defaultFor($campaign->workspace)) ? 'Add an SMS provider first.' : null,
            $campaign->recipientQuery()->doesntExist() ? 'No subscribed contacts with a phone number match this audience.' : null,
            Quota::smsRemaining($campaign->workspace) === 0 ? 'The monthly SMS limit has been reached.' : null,
        ]));
    }
}

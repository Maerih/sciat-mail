<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\MailList;
use App\Models\SendingDomain;
use App\Models\Template;
use App\Models\Workspace;
use App\Sending\Dispatcher;
use App\Sending\MergeTags;
use App\Sending\MessageBuilder;
use App\Sending\SendFailed;
use App\Sending\ServerPicker;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Campaign editor: Recipients → Setup → Content → Schedule → Confirm.
 */
class CampaignWizardController extends Controller
{
    public const STEPS = ['recipients' => 'Recipients', 'setup' => 'Setup', 'content' => 'Content', 'schedule' => 'Schedule', 'confirm' => 'Confirm'];

    public function recipients(Campaign $campaign): View|RedirectResponse
    {
        if ($redirect = $this->guard($campaign)) {
            return $redirect;
        }

        $lists = MailList::forWorkspace()->with('segments:id,mail_list_id,uid,name')
            ->withCount(['subscribers as subscribed_count' => fn ($q) => $q->where('status', 'subscribed')])
            ->orderBy('name')->get();

        return $this->step($campaign, 'recipients', [
            'lists' => $lists,
            'audiences' => $campaign->audiences()->with('list:id,uid', 'segment:id,uid')->get(),
        ]);
    }

    public function saveRecipients(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($campaign, abort: true);
        $request->validate(['audiences' => 'required|array|min:1', 'audiences.*.list' => 'required|string']);

        $rows = collect($request->input('audiences'))->map(function ($row) {
            $list = MailList::forWorkspace()->where('uid', $row['list'])->first();
            $segment = $list && ! empty($row['segment']) ? $list->segments()->where('uid', $row['segment'])->first() : null;

            return $list ? ['mail_list_id' => $list->id, 'segment_id' => $segment?->id, 'exclude' => ! empty($row['exclude'])] : null;
        })->filter()->unique(fn ($r) => $r['mail_list_id'].'-'.$r['segment_id'].'-'.(int) $r['exclude']);

        if ($rows->where('exclude', false)->isEmpty()) {
            throw ValidationException::withMessages(['audiences' => 'Choose at least one list to send to.']);
        }

        $campaign->audiences()->delete();
        $campaign->audiences()->createMany($rows->values()->all());

        // Pre-fill the sender from the first list when the campaign has none yet
        $first = MailList::find($rows->firstWhere('exclude', false)['mail_list_id']);
        $campaign->fill([
            'from_name' => $campaign->from_name ?: $first->from_name,
            'from_email' => $campaign->from_email ?: $first->from_email,
            'reply_to' => $campaign->reply_to ?: ($first->reply_to ?: $first->from_email),
        ])->save();

        return redirect()->route('campaigns.setup', $campaign);
    }

    public function setup(Campaign $campaign, ServerPicker $picker): View|RedirectResponse
    {
        if ($redirect = $this->guard($campaign)) {
            return $redirect;
        }

        return $this->step($campaign, 'setup', [
            'servers' => Workspace::current()->availableServers()->where('status', 'active')->orderBy('name')->get(),
            'domains' => SendingDomain::forWorkspace()->get(),
            'tags' => MergeTags::catalogue($campaign->audiences()->first()?->list),
        ]);
    }

    public function saveSetup(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($campaign, abort: true);
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'subject' => 'required|string|max:255',
            'preheader' => 'nullable|string|max:255',
            'from_name' => 'required|string|max:190',
            'from_email' => 'required|email',
            'reply_to' => 'nullable|email',
            'weights' => 'nullable|array',
            'weights.*' => 'nullable|integer|min:0|max:100',
            'utm.source' => 'nullable|string|max:100',
            'utm.medium' => 'nullable|string|max:100',
            'utm.campaign' => 'nullable|string|max:100',
            'utm.term' => 'nullable|string|max:100',
            'utm.content' => 'nullable|string|max:100',
        ]);

        $weights = null;
        if (! $request->boolean('use_all_servers')) {
            $allowed = Workspace::current()->availableServers()->pluck('id')->all();
            $weights = collect($data['weights'] ?? [])->filter(fn ($w, $id) => in_array((int) $id, $allowed, true) && (int) $w > 0)
                ->map(fn ($w) => (int) $w)->all();
            if (! $weights) {
                throw ValidationException::withMessages(['weights' => 'Give at least one server a weight above 0, or use all servers.']);
            }
        }

        $campaign->fill(collect($data)->only(['name', 'subject', 'preheader', 'from_name', 'from_email', 'reply_to'])->all() + [
            'track_open' => $request->boolean('track_open'),
            'track_click' => $request->boolean('track_click'),
            'sign_dkim' => $request->boolean('sign_dkim'),
            'skip_failed' => $request->boolean('skip_failed'),
            'server_weights' => $weights,
            'utm' => ['enabled' => $request->boolean('utm_enabled')] + ($data['utm'] ?? []),
        ])->save();

        return redirect()->route('campaigns.content', $campaign);
    }

    public function content(Campaign $campaign): View|RedirectResponse
    {
        if ($redirect = $this->guard($campaign)) {
            return $redirect;
        }

        return $this->step($campaign, 'content', [
            'templates' => Template::availableTo(Workspace::current())->orderByRaw('workspace_id is null')->orderBy('name')->get(['id', 'uid', 'name', 'category', 'workspace_id']),
            'tags' => MergeTags::catalogue($campaign->audiences()->first()?->list),
        ]);
    }

    public function saveContent(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($campaign, abort: true);
        $data = $request->validate([
            'html' => $campaign->isPlain() ? 'nullable' : 'required|string',
            'plain' => $campaign->isPlain() ? 'required|string' : 'nullable|string',
        ]);
        $campaign->fill($data)->save();

        return $request->input('next') === 'stay'
            ? back()->with('success', 'Content saved.')
            : redirect()->route('campaigns.schedule', $campaign);
    }

    public function useTemplate(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($campaign, abort: true);
        $template = Template::availableTo(Workspace::current())->where('uid', $request->input('template'))->firstOrFail();
        $campaign->forceFill(['html' => $template->html])->save();

        return redirect()->route('campaigns.content', $campaign)->with('success', "Template “{$template->name}” applied.");
    }

    /** Renders the email for the first matching recipient (or placeholders) without tracking. */
    public function preview(Campaign $campaign, MessageBuilder $builder): Response
    {
        $campaign->load('audiences.segment');
        $subscriber = $campaign->audiences->where('exclude', false)->isNotEmpty()
            ? $campaign->recipientQuery()->with('list.fields')->first()
            : null;
        $delivery = new Delivery(['email' => $subscriber?->email ?? 'subscriber@example.com', 'workspace_id' => $campaign->workspace_id]);
        $delivery->uid = 'preview';
        $delivery->setRelation('workspace', $campaign->workspace);
        $values = $builder->values($campaign, $delivery, $subscriber);

        $body = $campaign->isPlain()
            ? '<pre style="white-space:pre-wrap;font:14px/1.6 ui-monospace,monospace;padding:24px">'.e(MergeTags::render((string) $campaign->plain, $values, false)).'</pre>'
            : $builder->renderHtml($campaign, $delivery, $values, track: false);

        return response($body)->header('Content-Security-Policy', "script-src 'none'");
    }

    public function schedule(Campaign $campaign): View|RedirectResponse
    {
        if ($redirect = $this->guard($campaign)) {
            return $redirect;
        }

        return $this->step($campaign, 'schedule');
    }

    public function saveSchedule(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->guard($campaign, abort: true);
        $tz = $campaign->workspace->timezone;
        $data = $request->validate([
            'when' => 'required|in:now,later',
            'date' => 'required_if:when,later|nullable|date_format:Y-m-d',
            'time' => 'required_if:when,later|nullable|date_format:H:i',
            'window' => 'nullable|in:0,1',
            'ranges' => 'nullable|array',
            'ranges.*.weekday' => 'required|integer|between:1,7',
            'ranges.*.start' => 'required|date_format:H:i',
            'ranges.*.end' => 'required|date_format:H:i',
        ]);

        $scheduledAt = null;
        if ($data['when'] === 'later') {
            $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', $data['date'].' '.$data['time'], $tz)->utc();
            if ($scheduledAt->isPast()) {
                throw ValidationException::withMessages(['date' => 'Pick a time in the future.']);
            }
        }

        $window = null;
        if (! empty($data['window'])) {
            $window = collect($data['ranges'] ?? [])->filter(fn ($r) => $r['start'] < $r['end'])->values()->all();
            if (! $window) {
                throw ValidationException::withMessages(['ranges' => 'Add at least one valid time range, or allow sending at any time.']);
            }
        }

        $campaign->forceFill(['scheduled_at' => $scheduledAt, 'sending_window' => $window])->save();

        return redirect()->route('campaigns.confirm', $campaign);
    }

    public function confirm(Campaign $campaign, CampaignService $service): View|RedirectResponse
    {
        if ($redirect = $this->guard($campaign)) {
            return $redirect;
        }
        $campaign->load('audiences.list', 'audiences.segment');

        return $this->step($campaign, 'confirm', [
            'checks' => $service->preflight($campaign),
            'canLaunch' => $service->canLaunch($campaign),
        ]);
    }

    public function sendTest(Request $request, Campaign $campaign, MessageBuilder $builder, Dispatcher $dispatcher, ServerPicker $picker): RedirectResponse
    {
        $data = $request->validate(['emails' => 'required|string|max:1000']);
        $emails = collect(preg_split('/[\s,;]+/', $data['emails']))->filter()->unique()->take(10);
        if ($emails->contains(fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL))) {
            throw ValidationException::withMessages(['emails' => 'Enter valid email addresses separated by commas.']);
        }

        $server = $picker->pick($picker->forCampaign($campaign));
        if (! $server) {
            return back()->with('error', 'No active sending server is available for this campaign.');
        }

        $campaign->load('audiences.segment');
        $subscriber = $campaign->audiences->where('exclude', false)->isNotEmpty()
            ? $campaign->recipientQuery()->with('list.fields')->first() : null;

        $test = $campaign->replicate();
        $test->subject = '[Test] '.$campaign->subject;
        $sent = 0;
        foreach ($emails as $email) {
            $delivery = Delivery::create(['workspace_id' => $campaign->workspace_id, 'email' => $email, 'kind' => 'test',
                'status' => 'pending', 'sending_server_id' => $server->id]);
            try {
                $message = $builder->forCampaign($test, $delivery, $subscriber, $server);
                $id = $dispatcher->send($message, $server, $campaign->from_email, $email);
                $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => $id, 'attempts' => 1])->save();
                $sent++;
            } catch (SendFailed $e) {
                $delivery->forceFill(['status' => 'failed', 'error' => $e->getMessage(), 'attempts' => 1])->save();

                return back()->with('error', 'Test send failed via '.$server->name.': '.$e->getMessage());
            }
        }

        return back()->with('success', "Test email sent to {$sent} address(es) via {$server->name}.");
    }

    public function launch(Campaign $campaign, CampaignService $service): RedirectResponse
    {
        $this->guard($campaign, abort: true);
        $campaign->load('audiences.segment');

        if (! $service->canLaunch($campaign)) {
            return redirect()->route('campaigns.confirm', $campaign)->with('error', 'Fix the items marked in red before launching.');
        }

        $service->launch($campaign);

        return $campaign->status === 'scheduled'
            ? redirect()->route('campaigns.index')->with('success', 'Campaign scheduled.')
            : redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign launched — sending has started.');
    }

    protected function step(Campaign $campaign, string $step, array $data = []): View
    {
        return view('campaigns.wizard.'.$step, $data + ['campaign' => $campaign, 'steps' => self::STEPS, 'current' => $step]);
    }

    protected function guard(Campaign $campaign, bool $abort = false): ?RedirectResponse
    {
        if ($campaign->isEditable()) {
            return null;
        }
        abort_if($abort, 409, 'This campaign can no longer be edited.');

        return redirect()->route('campaigns.show', $campaign);
    }
}

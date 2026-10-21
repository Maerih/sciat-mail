<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\Campaign;
use App\Models\MailList;
use App\Models\Segment;
use App\Models\Template;
use App\Models\Workspace;
use App\Sending\MergeTags;
use App\Sending\ServerPicker;
use App\Services\Automation\Engine;
use App\Services\Automation\Flow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutomationController extends Controller
{
    public function index(Request $request): View
    {
        $automations = Automation::forWorkspace()->with('list:id,uid,name')
            ->withCount([
                'runs',
                'runs as active_runs_count' => fn ($q) => $q->whereIn('status', ['waiting', 'active']),
                'runs as completed_runs_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->when(in_array($request->status, ['draft', 'active', 'paused'], true), fn ($q) => $q->where('status', $request->status))
            ->latest()->paginate(20)->withQueryString();

        return view('automations.index', compact('automations'));
    }

    public function create(): View
    {
        return view('automations.create', [
            'automation' => new Automation(['trigger_type' => 'subscribed', 'trigger_config' => ['time' => '09:00', 'weekday' => 1, 'monthday' => 1, 'offset_days' => 0]]),
            'lists' => MailList::forWorkspace()->with('fields')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedSettings($request);
        $automation = Automation::create($data + ['workspace_id' => Workspace::current()->id, 'status' => 'draft']);

        return redirect()->route('automations.show', $automation)->with('success', 'Automation created. Add the steps of the journey below.');
    }

    public function show(Automation $automation): View
    {
        $automation->load('list.fields', 'emails');
        $flow = $automation->flow();

        $onStep = $automation->runs()->whereIn('status', ['waiting', 'active'])
            ->selectRaw('step_id, count(*) as c')->groupBy('step_id')->pluck('c', 'step_id');
        $emailStats = $automation->emails->mapWithKeys(fn (Campaign $c) => [$c->id => $c->stats()]);

        return view('automations.show', [
            'automation' => $automation,
            'flow' => $flow,
            'counts' => $automation->runCounts(),
            'onStep' => $onStep,
            'emailStats' => $emailStats,
            'emails' => $automation->emails->keyBy('id'),
            'problems' => $this->problems($automation),
        ]);
    }

    public function settings(Automation $automation): View
    {
        return view('automations.create', ['automation' => $automation, 'lists' => MailList::forWorkspace()->with('fields')->orderBy('name')->get()]);
    }

    public function updateSettings(Request $request, Automation $automation): RedirectResponse
    {
        $data = $this->validatedSettings($request, $automation);
        $automation->update($data);

        return redirect()->route('automations.show', $automation)->with('success', 'Settings saved.');
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $automation->delete();

        return redirect()->route('automations.index')->with('success', 'Automation deleted.');
    }

    public function activate(Automation $automation): RedirectResponse
    {
        if ($problems = $this->problems($automation)) {
            return back()->with('error', 'Fix these first: '.implode(' ', $problems));
        }
        $automation->forceFill(['status' => 'active'])->save();

        return back()->with('success', 'Automation is live. New triggers will enroll contacts automatically.');
    }

    public function pause(Automation $automation): RedirectResponse
    {
        $automation->forceFill(['status' => 'paused'])->save();

        return back()->with('success', 'Automation paused. Contacts stay where they are and continue when you resume.');
    }

    /** Enrolls contacts who already match (e.g. existing subscribers into a new welcome series). */
    public function enrollExisting(Request $request, Automation $automation, Engine $engine): RedirectResponse
    {
        abort_unless($automation->isActive(), 409, 'Activate the automation first.');
        $data = $request->validate(['segment' => 'nullable|string']);

        $query = $automation->list->subscribers()->where('status', 'subscribed')->getQuery();
        if (! empty($data['segment']) && ($segment = $automation->list->segments()->where('uid', $data['segment'])->first())) {
            $query = Segment::applyConditions($query, $segment->conditions ?? [], $segment->matching);
        }

        $count = 0;
        $query->select('subscribers.*')->chunkById(500, function ($subscribers) use ($engine, $automation, &$count) {
            foreach ($subscribers as $subscriber) {
                if ($engine->enroll($automation, $subscriber, 'manual')) {
                    $count++;
                }
            }
        });

        return back()->with('success', number_format($count).' contact(s) enrolled.');
    }

    public function contacts(Request $request, Automation $automation): View
    {
        $runs = $automation->runs()->with('subscriber:id,uid,email,mail_list_id')
            ->when(in_array($request->status, ['waiting', 'active', 'completed', 'exited', 'failed'], true), fn ($q) => $q->where('status', $request->status))
            ->latest('updated_at')->paginate(50)->withQueryString();

        return view('automations.contacts', ['automation' => $automation, 'runs' => $runs, 'flow' => $automation->flow()]);
    }

    public function addStep(Request $request, Automation $automation): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Flow::TYPES))],
            'parent' => 'nullable|string',
            'branch' => 'required|in:next,yes,no',
        ]);
        $flow = $automation->flow();
        abort_if($data['parent'] !== null && ! $flow->get($data['parent']), 422);

        $step = match ($data['type']) {
            'wait' => ['type' => 'wait', 'amount' => 1, 'unit' => 'days'],
            'condition' => ['type' => 'condition', 'rule' => ['kind' => 'opened', 'step' => '']],
            'action' => ['type' => 'action', 'action' => 'add_tag', 'tag' => ''],
            'send' => ['type' => 'send', 'campaign_id' => $this->newEmail($automation)->id],
        };
        $id = $flow->insert($data['parent'], $data['branch'], $step);
        $automation->setFlow($flow);
        $automation->save();

        return $data['type'] === 'send'
            ? redirect()->route('automations.email', [$automation, $id])
            : redirect()->route('automations.step', [$automation, $id]);
    }

    public function editStep(Automation $automation, string $step): View|RedirectResponse
    {
        $config = $automation->flow()->get($step) ?? abort(404);
        if ($config['type'] === 'send') {
            return redirect()->route('automations.email', [$automation, $step]);
        }

        return view('automations.step', [
            'automation' => $automation->load('list.fields'),
            'stepId' => $step,
            'step' => $config,
            'sendSteps' => $automation->flow()->sendSteps(),
            'emails' => $automation->emails()->get()->keyBy('id'),
            'lists' => MailList::forWorkspace()->whereKeyNot($automation->mail_list_id)->orderBy('name')->get(),
            'operators' => Segment::OPERATORS['text'],
        ]);
    }

    public function updateStep(Request $request, Automation $automation, string $step): RedirectResponse
    {
        $flow = $automation->flow();
        $current = $flow->get($step) ?? abort(404);

        $config = match ($current['type']) {
            'wait' => $request->validate([
                'amount' => 'required|integer|min:0|max:1000',
                'unit' => ['required', Rule::in(array_keys(Flow::WAIT_UNITS))],
            ]),
            'condition' => ['rule' => $request->validate([
                'kind' => ['required', Rule::in(array_keys(Flow::CONDITIONS))],
                'step' => 'nullable|string',
                'tag' => 'required_if:kind,tag|nullable|string|max:100',
                'field' => 'required_if:kind,field|nullable|string|max:64',
                'operator' => ['nullable', Rule::in(array_keys(Segment::OPERATORS['text']))],
                'value' => 'nullable|string|max:255',
            ])],
            'action' => $request->validate([
                'action' => ['required', Rule::in(array_keys(Flow::ACTIONS))],
                'tag' => 'required_if:action,add_tag,remove_tag|nullable|string|max:100',
                'field' => 'required_if:action,update_field|nullable|string|max:64',
                'value' => 'nullable|string|max:255',
                'list_id' => ['required_if:action,copy_list,move_list', 'nullable', 'integer',
                    Rule::exists('mail_lists', 'id')->where('workspace_id', $automation->workspace_id)],
            ]),
        };

        $flow->update($step, ['type' => $current['type']] + $config);
        $automation->setFlow($flow);
        $automation->save();

        return redirect()->route('automations.show', $automation)->with('success', 'Step saved.');
    }

    public function deleteStep(Automation $automation, string $step): RedirectResponse
    {
        $flow = $automation->flow();
        abort_unless($flow->get($step), 404);

        $result = $flow->remove($step);
        $campaignIds = collect($result['removed'])->map(fn ($id) => $automation->flow()->get($id)['campaign_id'] ?? null)->filter();

        // Contacts waiting on a removed step carry on from where the flow now continues
        $automation->runs()->whereIn('step_id', $result['removed'])->whereIn('status', ['waiting', 'active'])
            ->update(['step_id' => $result['continue'], 'wake_at' => null, 'status' => 'waiting']);

        $automation->setFlow($flow);
        $automation->save();
        $automation->emails()->whereIn('id', $campaignIds)->delete();

        return redirect()->route('automations.show', $automation)->with('success', 'Step removed.');
    }

    public function email(Automation $automation, string $step): View
    {
        $config = $automation->flow()->get($step);
        abort_unless($config && $config['type'] === 'send', 404);
        $campaign = $automation->emails()->findOrFail($config['campaign_id']);

        return view('automations.email', [
            'automation' => $automation->load('list.fields'),
            'stepId' => $step,
            'campaign' => $campaign,
            'templates' => Template::availableTo(Workspace::current())->orderByRaw('workspace_id is null')->orderBy('name')->get(['id', 'uid', 'name', 'category', 'workspace_id']),
            'tags' => MergeTags::catalogue($automation->list),
        ]);
    }

    public function updateEmail(Request $request, Automation $automation, string $step): RedirectResponse
    {
        $config = $automation->flow()->get($step);
        abort_unless($config && $config['type'] === 'send', 404);
        $campaign = $automation->emails()->findOrFail($config['campaign_id']);

        if ($request->filled('template')) {
            $template = Template::availableTo(Workspace::current())->where('uid', $request->template)->firstOrFail();
            $campaign->forceFill(['html' => $template->html, 'builder_json' => $template->builder_json])->save();

            return back()->with('success', "Template “{$template->name}” applied.");
        }

        $data = $request->validate([
            'name' => 'required|string|max:190',
            'subject' => 'required|string|max:255',
            'preheader' => 'nullable|string|max:255',
            'from_name' => 'required|string|max:190',
            'from_email' => 'required|email',
            'reply_to' => 'nullable|email',
            'html' => 'nullable|string',
            'plain' => 'nullable|string',
        ]);
        if (blank($data['html']) && blank($data['plain'])) {
            throw ValidationException::withMessages(['html' => 'Add HTML content or a plain-text version.']);
        }

        if ($data['html'] !== $campaign->html) {
            $campaign->builder_json = null;
        }
        $campaign->fill($data + [
            'type' => blank($data['html']) ? 'plain' : 'regular',
            'track_open' => $request->boolean('track_open'),
            'track_click' => $request->boolean('track_click'),
        ])->save();

        return $request->input('next') === 'stay'
            ? back()->with('success', 'Email saved.')
            : redirect()->route('automations.show', $automation)->with('success', 'Email saved.');
    }

    protected function newEmail(Automation $automation): Campaign
    {
        $list = $automation->list;
        $number = $automation->emails()->count() + 1;

        $campaign = new Campaign([
            'workspace_id' => $automation->workspace_id,
            'automation_id' => $automation->id,
            'name' => $automation->name.' — email '.$number,
            'type' => 'regular',
            'from_name' => $list->from_name,
            'from_email' => $list->from_email,
            'reply_to' => $list->reply_to ?: $list->from_email,
        ]);
        $campaign->status = 'automation';
        $campaign->save();

        return $campaign;
    }

    /** @return string[] reasons the automation cannot go live */
    protected function problems(Automation $automation): array
    {
        $problems = [];
        $flow = $automation->flow();
        if ($flow->isEmpty()) {
            $problems[] = 'Add at least one step.';
        }

        $emails = $automation->emails()->get()->keyBy('id');
        foreach ($flow->sendSteps() as $step) {
            $email = $emails->get($step['campaign_id'] ?? 0);
            if (! $email || blank($email->subject) || (blank($email->html) && blank($email->plain))) {
                $problems[] = 'Every “Send email” step needs a subject and content.';
                break;
            }
        }

        if (app(ServerPicker::class)->pool($automation->workspace)->isEmpty()) {
            $problems[] = 'Connect an active sending server.';
        }

        return $problems;
    }

    /** @return array<string, mixed> */
    protected function validatedSettings(Request $request, ?Automation $automation = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'list' => 'required|string',
            'trigger_type' => ['required', Rule::in(array_keys(Automation::TRIGGERS))],
            'config.field' => 'nullable|string|max:64',
            'config.tag' => 'nullable|string|max:100',
            'config.value' => 'nullable|string|max:255',
            'config.time' => 'nullable|date_format:H:i',
            'config.weekday' => 'nullable|integer|between:1,7',
            'config.monthday' => 'nullable|integer|between:1,31',
            'config.offset_days' => 'nullable|integer|between:-365,365',
        ]);

        $list = MailList::forWorkspace()->where('uid', $data['list'])->firstOrFail();
        if ($automation && $automation->mail_list_id !== $list->id && $automation->runs()->exists()) {
            throw ValidationException::withMessages(['list' => 'The list cannot change once contacts have entered the automation.']);
        }

        $needs = Automation::TRIGGERS[$data['trigger_type']]['config'];
        $config = collect($data['config'] ?? [])->only($needs)->all();
        foreach ($needs as $key) {
            if (in_array($key, ['field', 'tag'], true) && blank($config[$key] ?? null)) {
                throw ValidationException::withMessages(['config.'.$key => 'This trigger needs a '.$key.'.']);
            }
        }

        return [
            'name' => $data['name'],
            'mail_list_id' => $list->id,
            'trigger_type' => $data['trigger_type'],
            'trigger_config' => $config,
            'allow_reentry' => $request->boolean('allow_reentry'),
        ];
    }
}

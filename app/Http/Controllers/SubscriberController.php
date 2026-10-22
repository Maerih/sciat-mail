<?php

namespace App\Http\Controllers;

use App\Jobs\SendListEmail;
use App\Jobs\VerifySubscribers;
use App\Models\Blacklist;
use App\Models\MailList;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\VerificationServer;
use App\Services\Quota;
use App\Services\QuotaExceeded;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public const BULK_ACTIONS = [
        'tag' => 'Add tag(s)',
        'untag' => 'Remove tag(s)',
        'subscribe' => 'Subscribe',
        'unsubscribe' => 'Unsubscribe',
        'confirm_email' => 'Resend confirmation email',
        'verify' => 'Verify email addresses',
        'copy' => 'Copy to list',
        'move' => 'Move to list',
        'blacklist' => 'Blacklist',
        'delete' => 'Delete',
    ];

    public function index(Request $request, MailList $list): View
    {
        $subscribers = $this->filtered($request, $list)
            ->orderBy(match ($request->sort) {
                'email' => 'email',
                'updated' => 'updated_at',
                default => 'id',
            }, $request->sort === 'email' ? 'asc' : 'desc')
            ->paginate(50)->withQueryString();

        return view('subscribers.index', [
            'list' => $list,
            'subscribers' => $subscribers,
            'fields' => $list->fields,
            'segments' => $list->segments()->orderBy('name')->get(),
            'otherLists' => MailList::forWorkspace()->whereKeyNot($list->id)->orderBy('name')->get(),
            'counts' => $list->statusCounts(),
        ]);
    }

    public function create(MailList $list): View
    {
        return view('subscribers.form', ['list' => $list, 'subscriber' => new Subscriber(['status' => 'subscribed'])]);
    }

    public function store(Request $request, MailList $list, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate($this->rules($list) + [
            'EMAIL' => ['required', 'email', Rule::unique('subscribers', 'email')->where('mail_list_id', $list->id)],
            'tags' => 'nullable|string|max:500',
            'skip_confirmation' => 'boolean',
        ]);

        try {
            [$subscriber, $pending] = $subscriptions->subscribe($list, $data['EMAIL'], $this->fieldValues($list, $data), 'manual',
                $request->ip(), $request->boolean('skip_confirmation'), $this->parseTags($data['tags'] ?? ''));
        } catch (QuotaExceeded $e) {
            return back()->withInput()->withErrors(['EMAIL' => $e->getMessage()]);
        }

        return redirect()->route('lists.subscribers.index', $list)->with('success', $pending
            ? "{$subscriber->email} added — a confirmation email has been sent."
            : "{$subscriber->email} added.");
    }

    public function show(MailList $list, Subscriber $subscriber): View
    {
        $timeline = $subscriber->events()->with('campaign:id,uid,name')->latest('created_at')->limit(100)->get()
            ->map(fn ($e) => ['at' => $e->created_at, 'type' => $e->type, 'subtype' => $e->subtype, 'campaign' => $e->campaign, 'url' => $e->url])
            ->concat($subscriber->deliveries()->with('campaign:id,uid,name')->whereNotNull('sent_at')->latest('sent_at')->limit(100)->get()
                ->map(fn ($d) => ['at' => $d->sent_at, 'type' => 'sent', 'subtype' => $d->kind, 'campaign' => $d->campaign, 'url' => null]))
            ->push(['at' => $subscriber->created_at, 'type' => 'added', 'subtype' => $subscriber->source, 'campaign' => null, 'url' => null])
            ->sortByDesc('at')->values();

        return view('subscribers.show', compact('list', 'subscriber', 'timeline'));
    }

    public function edit(MailList $list, Subscriber $subscriber): View
    {
        return view('subscribers.form', compact('list', 'subscriber'));
    }

    public function update(Request $request, MailList $list, Subscriber $subscriber): RedirectResponse
    {
        $data = $request->validate($this->rules($list) + [
            'EMAIL' => ['required', 'email', Rule::unique('subscribers', 'email')->where('mail_list_id', $list->id)->ignore($subscriber->id)],
            'status' => 'required|in:'.implode(',', Subscriber::STATUSES),
            'tags' => 'nullable|string|max:500',
        ]);

        $subscriber->fill([
            'email' => $data['EMAIL'],
            'data' => array_merge($subscriber->data ?? [], $this->fieldValues($list, $data)),
            'tags' => $this->parseTags($data['tags'] ?? ''),
        ]);

        if ($data['status'] !== $subscriber->status) {
            $subscriber->status = $data['status'];
            $subscriber->unsubscribed_at = $data['status'] === 'unsubscribed' ? now() : null;
            if ($data['status'] === 'blacklisted') {
                Blacklist::add($subscriber->email, $list->workspace_id, 'manual');
            }
        }
        $subscriber->save();

        return redirect()->route('lists.subscribers.show', [$list, $subscriber])->with('success', 'Subscriber updated.');
    }

    public function destroy(MailList $list, Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return redirect()->route('lists.subscribers.index', $list)->with('success', 'Subscriber deleted.');
    }

    /** Applies an action to the checked rows, or to every row matching the current filters. */
    public function bulk(Request $request, MailList $list): RedirectResponse
    {
        $data = $request->validate([
            'action' => 'required|in:'.implode(',', array_keys(self::BULK_ACTIONS)),
            'scope' => 'required|in:selected,all',
            'ids' => 'required_if:scope,selected|array',
            'ids.*' => 'string',
            'tags' => 'required_if:action,tag,untag|nullable|string|max:500',
            'target_list' => 'required_if:action,copy,move|nullable|string',
        ]);

        $query = $data['scope'] === 'all'
            ? $this->filtered($request, $list)
            : $list->subscribers()->whereIn('uid', $data['ids'] ?? []);

        $target = ! empty($data['target_list']) ? MailList::forWorkspace()->where('uid', $data['target_list'])->firstOrFail() : null;
        $tags = $this->parseTags($data['tags'] ?? '');
        $count = 0;

        if ($data['action'] === 'verify') {
            $ids = $query->pluck('id')->all();
            VerifySubscribers::dispatch($list->id, $ids);

            return back()->with('success', number_format(count($ids)).' address(es) queued for verification.');
        }

        $query->chunkById(500, function ($subscribers) use ($data, $list, $target, $tags, &$count) {
            foreach ($subscribers as $subscriber) {
                $count++;
                match ($data['action']) {
                    'tag' => tap($subscriber, fn ($s) => $s->addTags($tags))->save(),
                    'untag' => tap($subscriber, fn ($s) => $s->removeTags($tags))->save(),
                    'subscribe' => $subscriber->status === 'blacklisted' ? null
                        : $subscriber->forceFill(['status' => 'subscribed', 'unsubscribed_at' => null])->save(),
                    'unsubscribe' => $subscriber->unsubscribe(),
                    'confirm_email' => $subscriber->status === 'unconfirmed' ? SendListEmail::dispatch($subscriber->id, 'confirm') : null,
                    'blacklist' => Blacklist::add($subscriber->email, $list->workspace_id, 'manual'),
                    'copy', 'move' => $this->copyTo($subscriber, $target, $data['action'] === 'move'),
                    'delete' => $subscriber->delete(),
                };
            }
        });

        return back()->with('success', self::BULK_ACTIONS[$data['action']].': '.number_format($count).' subscriber(s) processed.');
    }

    public function export(Request $request, MailList $list): StreamedResponse
    {
        $fields = $list->fields;
        $query = $this->filtered($request, $list);

        return response()->streamDownload(function () use ($query, $fields) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['Email'], $fields->pluck('label')->all(), ['Status', 'Tags', 'Created']));
            $query->chunkById(1000, function ($rows) use ($out, $fields) {
                foreach ($rows as $s) {
                    fputcsv($out, array_merge(
                        [$s->email],
                        $fields->map(fn ($f) => $s->field($f->tag))->all(),
                        [$s->status, implode(', ', $s->tags ?? []), $s->created_at?->toDateTimeString()]
                    ));
                }
            });
            fclose($out);
        }, str($list->name)->slug().'-subscribers-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function filtered(Request $request, MailList $list): Builder
    {
        $query = $list->subscribers()->getQuery();

        if ($request->filled('segment') && ($segment = $list->segments()->where('uid', $request->segment)->first())) {
            $query = Segment::applyConditions($query, $segment->conditions ?? [], $segment->matching);
        }

        return $query
            ->when(in_array($request->status, Subscriber::STATUSES, true), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('tag'), fn ($q) => $q->whereJsonContains('tags', $request->tag))
            ->when(in_array($request->verification, VerificationServer::STATUSES, true), fn ($q) => $q->where('verification_status', $request->verification))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.addcslashes($request->q, '%_\\').'%';
                $q->where(fn ($w) => $w->where('email', 'like', $term)
                    ->orWhere('data->FIRST_NAME', 'like', $term)
                    ->orWhere('data->LAST_NAME', 'like', $term));
            });
    }

    protected function copyTo(Subscriber $subscriber, MailList $target, bool $move): void
    {
        $copy = $target->subscribers()->firstOrNew(['email' => $subscriber->email]);
        if (! $copy->exists) {
            if (! $move && ! Quota::canAddContacts($target->workspace)) {
                return; // copies count against the contact limit; moves don't change the total
            }
            $copy->fill($subscriber->only(['status', 'data', 'tags', 'ip', 'confirmed_at', 'unsubscribed_at']) + ['source' => 'copy']);
            $copy->save();
        }
        if ($move) {
            $subscriber->delete();
        }
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(MailList $list): array
    {
        return $list->fields->mapWithKeys(fn ($f) => [$f->tag => $f->validationRules()])->all();
    }

    /** @return array<string, mixed> */
    protected function fieldValues(MailList $list, array $data): array
    {
        return $list->fields->mapWithKeys(fn ($f) => [$f->tag => $data[$f->tag] ?? null])
            ->filter(fn ($v) => $v !== null)->all();
    }

    /** @return string[] */
    protected function parseTags(string $tags): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $tags)))));
    }
}

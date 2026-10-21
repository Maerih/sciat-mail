<?php

namespace App\Http\Controllers;

use App\Models\MailList;
use App\Models\Segment;
use App\Models\Subscriber;
use App\Models\VerificationServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SegmentController extends Controller
{
    public function index(MailList $list): View
    {
        $segments = $list->segments()->orderBy('name')->get()
            ->each(fn (Segment $s) => $s->setAttribute('size', $s->subscribersQuery()->count()));

        return view('segments.index', ['list' => $list, 'segments' => $segments, 'total' => $list->subscribers()->count()]);
    }

    public function create(MailList $list): View
    {
        return view('segments.form', ['list' => $list, 'segment' => new Segment(['matching' => 'all', 'conditions' => []])]
            + $this->builderData($list));
    }

    public function store(Request $request, MailList $list): RedirectResponse
    {
        $list->segments()->create($this->validated($request, $list));

        return redirect()->route('lists.segments.index', $list)->with('success', 'Segment created.');
    }

    public function edit(MailList $list, Segment $segment): View
    {
        return view('segments.form', ['list' => $list, 'segment' => $segment] + $this->builderData($list));
    }

    public function update(Request $request, MailList $list, Segment $segment): RedirectResponse
    {
        $segment->update($this->validated($request, $list));

        return redirect()->route('lists.segments.index', $list)->with('success', 'Segment saved.');
    }

    public function destroy(MailList $list, Segment $segment): RedirectResponse
    {
        $segment->delete();

        return redirect()->route('lists.segments.index', $list)->with('success', 'Segment deleted.');
    }

    /** Live size preview for the builder. */
    public function count(Request $request, MailList $list): JsonResponse
    {
        $conditions = $this->cleanConditions($request->input('conditions', []), $list);
        $count = Segment::applyConditions($list->subscribers()->getQuery(), $conditions, $request->input('matching') === 'any' ? 'any' : 'all')->count();

        return response()->json(['count' => $count]);
    }

    /** @return array<string, mixed> */
    protected function builderData(MailList $list): array
    {
        $fields = collect(['EMAIL' => ['label' => 'Email', 'kind' => 'text']])
            ->merge($list->fields->mapWithKeys(fn ($f) => [$f->tag => ['label' => $f->label, 'kind' => 'text']]))
            ->merge(Segment::SPECIAL_FIELDS);

        return ['fields' => $fields, 'operators' => Segment::OPERATORS, 'statuses' => Subscriber::STATUSES,
            'verifications' => VerificationServer::STATUSES];
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, MailList $list): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'matching' => 'required|in:all,any',
            'conditions' => 'required|array|min:1',
        ], ['conditions.required' => 'Add at least one condition.']);

        $data['conditions'] = $this->cleanConditions($data['conditions'], $list);

        return $data;
    }

    /** Drops conditions whose field/operator are unknown so stored segments are always valid. */
    protected function cleanConditions(array $conditions, MailList $list): array
    {
        $kinds = collect(['EMAIL' => 'text'])
            ->merge($list->fields->mapWithKeys(fn ($f) => [$f->tag => 'text']))
            ->merge(collect(Segment::SPECIAL_FIELDS)->map(fn ($f) => $f['kind']));

        return collect($conditions)
            ->filter(fn ($c) => is_array($c) && isset($kinds[$c['field'] ?? ''])
                && isset(Segment::OPERATORS[$kinds[$c['field']]][$c['operator'] ?? '']))
            ->map(fn ($c) => ['field' => $c['field'], 'operator' => $c['operator'], 'value' => (string) ($c['value'] ?? '')])
            ->values()->all();
    }
}

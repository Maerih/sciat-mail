<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriberResource;
use App\Models\MailList;
use App\Models\Subscriber;
use App\Services\QuotaExceeded;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class SubscriberController extends Controller
{
    public function index(Request $request, MailList $list): AnonymousResourceCollection
    {
        $request->validate(['status' => 'nullable|in:'.implode(',', Subscriber::STATUSES), 'per_page' => 'nullable|integer|min:1|max:500']);

        $subscribers = $list->subscribers()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->email, fn ($q) => $q->where('email', strtolower($request->email)))
            ->when($request->tag, fn ($q) => $q->whereJsonContains('tags', $request->tag))
            ->when($request->updated_since, fn ($q) => $q->where('updated_at', '>=', $request->date('updated_since')))
            ->orderBy('id')->paginate($request->integer('per_page', 100));

        return SubscriberResource::collection($subscribers);
    }

    public function show(MailList $list, string $subscriber): SubscriberResource
    {
        return new SubscriberResource($this->find($list, $subscriber));
    }

    /**
     * Adds or updates a contact by email. Double opt-in lists send a confirmation unless
     * "confirmed": true is passed (only when consent was collected elsewhere).
     */
    public function store(Request $request, MailList $list, SubscriptionService $subscriptions): JsonResponse
    {
        $data = $this->validateContact($request, $list);
        $existed = $list->subscribers()->where('email', strtolower($data['email']))->exists();

        try {
            [$subscriber, $pending] = $subscriptions->subscribe($list, $data['email'], $data['fields'] ?? [], 'api', $request->ip(),
                (bool) ($data['confirmed'] ?? false), $data['tags'] ?? []);
        } catch (QuotaExceeded $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new SubscriberResource($subscriber->fresh()))->additional(['confirmation_sent' => $pending])
            ->response()->setStatusCode($existed ? 200 : 201);
    }

    /** Up to 1,000 contacts per call; same rules as store. */
    public function bulk(Request $request, MailList $list, SubscriptionService $subscriptions): JsonResponse
    {
        $request->validate(['subscribers' => 'required|array|min:1|max:1000', 'confirmed' => 'sometimes|boolean']);
        $results = ['created' => 0, 'updated' => 0, 'failed' => []];

        foreach ($request->input('subscribers') as $index => $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results['failed'][] = ['index' => $index, 'email' => $row['email'] ?? null, 'error' => 'Invalid email'];

                continue;
            }
            $existed = $list->subscribers()->where('email', $email)->exists();
            try {
                $subscriptions->subscribe($list, $email, $this->cleanFields($list, (array) ($row['fields'] ?? [])), 'api', $request->ip(),
                    $request->boolean('confirmed'), array_filter((array) ($row['tags'] ?? []), 'is_string'));
            } catch (QuotaExceeded $e) {
                $results['failed'][] = ['index' => $index, 'email' => $email, 'error' => $e->getMessage()];

                continue;
            }
            $results[$existed ? 'updated' : 'created']++;
        }

        return response()->json($results);
    }

    public function update(Request $request, MailList $list, string $subscriber): SubscriberResource
    {
        $model = $this->find($list, $subscriber);
        $data = $request->validate([
            'email' => 'sometimes|email|max:190',
            'fields' => 'sometimes|array',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:100',
        ]);

        if (isset($data['email']) && strtolower($data['email']) !== $model->email
            && $list->subscribers()->where('email', strtolower($data['email']))->exists()) {
            throw ValidationException::withMessages(['email' => 'Another contact on this list already uses that email.']);
        }

        $model->fill([
            'email' => $data['email'] ?? $model->email,
            'data' => array_merge($model->data ?? [], $this->cleanFields($list, $data['fields'] ?? [])),
            'tags' => array_key_exists('tags', $data) ? array_values(array_unique($data['tags'])) : $model->tags,
        ])->save();

        return new SubscriberResource($model);
    }

    public function destroy(MailList $list, string $subscriber): JsonResponse
    {
        $this->find($list, $subscriber)->delete();

        return response()->json(null, 204);
    }

    public function unsubscribe(MailList $list, string $subscriber, SubscriptionService $subscriptions): SubscriberResource
    {
        $model = $this->find($list, $subscriber);
        $subscriptions->unsubscribe($model);

        return new SubscriberResource($model->fresh());
    }

    public function tags(Request $request, MailList $list, string $subscriber): SubscriberResource
    {
        $data = $request->validate(['add' => 'sometimes|array', 'add.*' => 'string|max:100', 'remove' => 'sometimes|array', 'remove.*' => 'string|max:100']);
        $model = $this->find($list, $subscriber);
        $model->addTags($data['add'] ?? []);
        $model->removeTags($data['remove'] ?? []);
        $model->save();

        return new SubscriberResource($model);
    }

    /** Accepts a subscriber uid or an email address in the URL. */
    protected function find(MailList $list, string $key): Subscriber
    {
        return $list->subscribers()->where(str_contains($key, '@') ? 'email' : 'uid', strtolower($key))->firstOrFail();
    }

    /** @return array<string, mixed> */
    protected function validateContact(Request $request, MailList $list): array
    {
        $data = $request->validate([
            'email' => 'required|email|max:190',
            'fields' => 'sometimes|array',
            'tags' => 'sometimes|array',
            'tags.*' => 'string|max:100',
            'confirmed' => 'sometimes|boolean',
        ]);
        $data['fields'] = $this->cleanFields($list, $data['fields'] ?? []);

        $rules = $list->fields->mapWithKeys(fn ($f) => ['fields.'.$f->tag => array_map(fn ($r) => $r === 'required' ? 'nullable' : $r, $f->validationRules())])->all();
        validator(['fields' => $data['fields']], $rules)->validate();

        return $data;
    }

    /** Keeps only known field tags (case-insensitive keys). @return array<string, mixed> */
    protected function cleanFields(MailList $list, array $fields): array
    {
        $tags = $list->fields->pluck('tag')->all();

        return collect($fields)->mapWithKeys(fn ($v, $k) => [strtoupper((string) $k) => $v])
            ->only($tags)->filter(fn ($v) => $v !== null && is_scalar($v))->all();
    }
}

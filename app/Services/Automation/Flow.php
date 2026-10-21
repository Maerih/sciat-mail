<?php

namespace App\Services\Automation;

use Illuminate\Support\Str;

/**
 * Immutable-style wrapper around an automation's step graph:
 *   ['start' => 'ab12', 'steps' => ['ab12' => ['type' => 'send', 'campaign_id' => 5, 'next' => 'cd34'], ...]]
 * Linear steps point to 'next'; condition steps branch to 'yes' / 'no'. The graph is a tree.
 */
class Flow
{
    public const TYPES = [
        'send' => 'Send email',
        'wait' => 'Wait',
        'condition' => 'Condition',
        'action' => 'Action',
    ];

    public const ACTIONS = [
        'add_tag' => 'Add tag',
        'remove_tag' => 'Remove tag',
        'update_field' => 'Update field',
        'copy_list' => 'Copy to list',
        'move_list' => 'Move to list',
        'unsubscribe' => 'Unsubscribe',
    ];

    public const CONDITIONS = [
        'opened' => 'Opened an email in this automation',
        'clicked' => 'Clicked a link in an email of this automation',
        'tag' => 'Has tag',
        'field' => 'Field matches',
        'subscribed' => 'Is still subscribed',
    ];

    public const WAIT_UNITS = ['minutes' => 'minutes', 'hours' => 'hours', 'days' => 'days', 'weeks' => 'weeks'];

    /** @param array{start: ?string, steps: array<string, array<string, mixed>>} $data */
    public function __construct(protected array $data) {}

    /** @return array{start: null, steps: array<string, mixed>} */
    public static function empty(): array
    {
        return ['start' => null, 'steps' => []];
    }

    /** @return array{start: ?string, steps: array<string, array<string, mixed>>} */
    public function toArray(): array
    {
        return $this->data;
    }

    public function start(): ?string
    {
        return $this->data['start'] ?? null;
    }

    public function get(?string $id): ?array
    {
        return $id !== null ? ($this->data['steps'][$id] ?? null) : null;
    }

    /** @return array<string, array<string, mixed>> */
    public function steps(): array
    {
        return $this->data['steps'] ?? [];
    }

    public function isEmpty(): bool
    {
        return $this->start() === null;
    }

    /**
     * Inserts a step below $parentId on $branch ('next', 'yes' or 'no'); null parent = top.
     * Whatever followed that point continues after the new step (on its 'yes' path for a condition).
     *
     * @return string the new step id
     */
    public function insert(?string $parentId, string $branch, array $step): string
    {
        $id = strtolower(Str::random(8));
        $following = $parentId === null ? $this->start() : ($this->data['steps'][$parentId][$branch] ?? null);

        if ($step['type'] === 'condition') {
            $step['yes'] = $following;
            $step['no'] = null;
        } else {
            $step['next'] = $following;
        }

        $this->data['steps'][$id] = $step;
        if ($parentId === null) {
            $this->data['start'] = $id;
        } else {
            $this->data['steps'][$parentId][$branch] = $id;
        }

        return $id;
    }

    public function update(string $id, array $config): void
    {
        $links = array_intersect_key($this->data['steps'][$id], array_flip(['type', 'next', 'yes', 'no', 'campaign_id']));
        $this->data['steps'][$id] = array_merge($config, $links);
    }

    /**
     * Removes a step and reconnects its parent to what followed it. Removing a condition keeps
     * its "yes" path and deletes everything on its "no" path.
     *
     * @return array{continue: ?string, removed: string[]} where runs on removed steps should go, and every deleted id
     */
    public function remove(string $id): array
    {
        $step = $this->data['steps'][$id];
        $continue = $step['type'] === 'condition' ? ($step['yes'] ?? null) : ($step['next'] ?? null);
        $removed = [$id];

        if ($step['type'] === 'condition' && ! empty($step['no'])) {
            $removed = array_merge($removed, $this->subtree($step['no']));
        }

        [$parentId, $branch] = $this->parentOf($id);
        if ($parentId === null) {
            $this->data['start'] = $continue;
        } else {
            $this->data['steps'][$parentId][$branch] = $continue;
        }

        foreach ($removed as $removedId) {
            unset($this->data['steps'][$removedId]);
        }

        return ['continue' => $continue, 'removed' => $removed];
    }

    /** @return array{0: ?string, 1: ?string} parent id and branch key; [null, null] for the start step */
    public function parentOf(string $id): array
    {
        foreach ($this->steps() as $stepId => $step) {
            foreach (['next', 'yes', 'no'] as $branch) {
                if (($step[$branch] ?? null) === $id) {
                    return [$stepId, $branch];
                }
            }
        }

        return [null, null];
    }

    /** @return string[] ids of $id and everything reachable from it */
    public function subtree(?string $id): array
    {
        $ids = [];
        $stack = [$id];
        while ($stack) {
            $current = array_pop($stack);
            if ($current === null || ! isset($this->data['steps'][$current]) || in_array($current, $ids, true)) {
                continue;
            }
            $ids[] = $current;
            $step = $this->data['steps'][$current];
            // pushed in reverse so the main path is visited first
            array_push($stack, $step['no'] ?? null, $step['yes'] ?? null, $step['next'] ?? null);
        }

        return $ids;
    }

    /** Send steps in reading order, for condition pickers and statistics. @return array<string, array<string, mixed>> */
    public function sendSteps(): array
    {
        return collect($this->subtree($this->start()))
            ->mapWithKeys(fn ($id) => [$id => $this->data['steps'][$id]])
            ->filter(fn ($s) => $s['type'] === 'send')
            ->all();
    }
}

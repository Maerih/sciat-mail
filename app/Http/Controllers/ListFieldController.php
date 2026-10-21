<?php

namespace App\Http\Controllers;

use App\Models\ListField;
use App\Models\MailList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ListFieldController extends Controller
{
    public function index(MailList $list): View
    {
        return view('lists.fields', ['list' => $list, 'fields' => $list->fields]);
    }

    /**
     * Saves the whole field set at once: rows with an id are updated, rows without one are
     * created, and existing fields missing from the submission are deleted.
     */
    public function update(Request $request, MailList $list): RedirectResponse
    {
        $request->validate([
            'fields' => 'array',
            'fields.*.id' => 'nullable|integer',
            'fields.*.label' => 'required|string|max:120',
            'fields.*.tag' => 'nullable|string|max:60',
            'fields.*.type' => 'required|in:'.implode(',', array_keys(ListField::TYPES)),
            'fields.*.options' => 'nullable|string|max:2000',
            'fields.*.default_value' => 'nullable|string|max:255',
        ]);

        $rows = collect($request->input('fields', []))->values();
        $tags = $rows->map(fn ($r) => $this->normaliseTag($r['tag'] ?? '', $r['label']));

        if ($tags->contains('EMAIL') || $tags->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['fields' => 'Each field needs a unique merge tag, and EMAIL is reserved.']);
        }

        $keep = [];
        foreach ($rows as $i => $row) {
            $attributes = [
                'label' => $row['label'],
                'tag' => $tags[$i],
                'type' => $row['type'],
                'options' => $row['type'] === 'dropdown'
                    ? array_values(array_filter(array_map('trim', explode("\n", (string) ($row['options'] ?? '')))))
                    : null,
                'default_value' => $row['default_value'] ?? null,
                'required' => ! empty($row['required']),
                'visible' => ! empty($row['visible']),
                'sort' => $i + 1,
            ];

            $field = ! empty($row['id']) ? $list->fields()->find($row['id']) : null;
            if ($field) {
                $field->update($attributes);
            } else {
                $field = $list->fields()->create($attributes);
            }
            $keep[] = $field->id;
        }

        $list->fields()->whereNotIn('id', $keep)->delete();

        return back()->with('success', 'Fields saved.');
    }

    protected function normaliseTag(string $tag, string $label): string
    {
        $tag = $tag !== '' ? $tag : $label;

        return Str::of($tag)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->limit(60, '')->toString();
    }
}

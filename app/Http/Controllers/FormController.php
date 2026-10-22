<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\MailList;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FormController extends Controller
{
    public function index(): View
    {
        $forms = Form::with('list:id,uid,name', 'website:id,uid,name')
            ->whereHas('list', fn ($q) => $q->forWorkspace())
            ->latest()->paginate(20);

        return view('forms.index', compact('forms'));
    }

    public function create(Request $request): View
    {
        return view('forms.form', [
            'form' => new Form(['title' => 'Join our newsletter', 'button_text' => 'Subscribe', 'status' => 'published',
                'consent_text' => 'I agree to receive emails and accept the privacy policy.']),
            'lists' => MailList::forWorkspace()->orderBy('name')->get(),
            'selectedList' => $request->query('list'),
            'websites' => Website::forWorkspace()->orderBy('name')->get(),
            'selectedWebsite' => $request->query('website'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $list = MailList::forWorkspace()->where('uid', $data['list'])->firstOrFail();
        $form = $list->forms()->make($data);
        $form->website_id = $this->websiteId($request);
        $form->save();

        return redirect()->route('forms.embed', $form)->with('success', 'Form created. Copy the embed code below.');
    }

    public function edit(Form $form): View
    {
        return view('forms.form', ['form' => $form, 'lists' => MailList::forWorkspace()->orderBy('name')->get(), 'selectedList' => $form->list->uid,
            'websites' => Website::forWorkspace()->orderBy('name')->get(), 'selectedWebsite' => null]);
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $data = $this->validated($request);
        $list = MailList::forWorkspace()->where('uid', $data['list'])->firstOrFail();
        $form->mail_list_id = $list->id;
        $form->website_id = $this->websiteId($request);
        $form->update($data);

        return redirect()->route('forms.embed', $form)->with('success', 'Form saved.');
    }

    public function destroy(Form $form): RedirectResponse
    {
        $form->delete();

        return redirect()->route('forms.index')->with('success', 'Form deleted.');
    }

    public function embed(Form $form): View
    {
        $form->load('list.fields');

        return view('forms.embed', [
            'form' => $form,
            'code' => view('public.partials.form-embed', ['form' => $form])->render(),
        ]);
    }

    protected function websiteId(Request $request): ?int
    {
        return $request->filled('website') ? Website::forWorkspace()->where('uid', $request->input('website'))->firstOrFail()->id : null;
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'list' => 'required|string',
            'name' => 'required|string|max:190',
            'title' => 'nullable|string|max:190',
            'button_text' => 'required|string|max:60',
            'redirect_url' => 'nullable|url',
            'success_message' => 'nullable|string|max:1000',
            'consent_text' => 'nullable|string|max:2000',
            'status' => 'required|in:published,draft',
            'display' => 'required|in:embedded,popup',
            'popup.trigger' => ['nullable', Rule::in(array_keys(Form::TRIGGERS))],
            'popup.delay' => 'nullable|integer|min:0|max:600',
            'popup.scroll' => 'nullable|integer|min:1|max:100',
            'popup.position' => ['nullable', Rule::in(array_keys(Form::POSITIONS))],
            'popup.frequency_days' => 'nullable|integer|min:0|max:365',
            'popup.color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'popup.description' => 'nullable|string|max:500',
        ]) + [
            'popup_settings' => array_merge(Form::POPUP_DEFAULTS, array_filter((array) $request->input('popup', []), fn ($v) => $v !== null)),
            'only_required_fields' => $request->boolean('only_required_fields'),
            'consent_enabled' => $request->boolean('consent_enabled'),
        ];
    }
}

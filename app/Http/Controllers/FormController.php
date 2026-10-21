<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\MailList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FormController extends Controller
{
    public function index(): View
    {
        $forms = Form::with('list:id,uid,name')
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $list = MailList::forWorkspace()->where('uid', $data['list'])->firstOrFail();
        $form = $list->forms()->create($data);

        return redirect()->route('forms.embed', $form)->with('success', 'Form created. Copy the embed code below.');
    }

    public function edit(Form $form): View
    {
        return view('forms.form', ['form' => $form, 'lists' => MailList::forWorkspace()->orderBy('name')->get(), 'selectedList' => $form->list->uid]);
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $data = $this->validated($request);
        $list = MailList::forWorkspace()->where('uid', $data['list'])->firstOrFail();
        $form->mail_list_id = $list->id;
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
        ]) + [
            'only_required_fields' => $request->boolean('only_required_fields'),
            'consent_enabled' => $request->boolean('consent_enabled'),
        ];
    }
}

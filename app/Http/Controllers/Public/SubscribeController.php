<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Subscriber;
use App\Sending\TrackingUrls;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SubscribeController extends Controller
{
    public function show(string $form): View
    {
        $form = $this->form($form);

        return view('public.subscribe', ['form' => $form]);
    }

    public function store(Request $request, string $form, SubscriptionService $subscriptions): View|RedirectResponse|JsonResponse
    {
        $form = $this->form($form);
        $list = $form->list;

        // Honeypot: bots fill every field; pretend success so they don't retry
        if (filled($request->input('website_url'))) {
            return $this->success($request, $form, false);
        }

        $rules = ['EMAIL' => 'required|email:rfc|max:190'];
        foreach ($form->visibleFields() as $field) {
            $rules[$field->tag] = $field->validationRules();
        }
        if ($form->consent_enabled) {
            $rules['consent'] = 'accepted';
        }

        $validator = Validator::make($request->all(), $rules, ['consent.accepted' => 'Please tick the consent box to subscribe.']);
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'errors' => $validator->errors()], 422);
            }

            return redirect()->route('subscribe.show', $form->uid)->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $values = collect($data)->except(['EMAIL', 'consent'])->all();
        [, $pending] = $subscriptions->subscribe($list, $data['EMAIL'], $values, 'form', $request->ip());
        DB::table('forms')->where('id', $form->id)->increment('submissions');

        return $this->success($request, $form, $pending);
    }

    public function confirm(string $uid, string $sig, TrackingUrls $urls, SubscriptionService $subscriptions): View
    {
        abort_unless($urls->verify('confirm|'.$uid, $sig), 404);
        $subscriber = Subscriber::with('list')->where('uid', $uid)->firstOrFail();
        $subscriptions->confirm($subscriber);

        return view('public.message', [
            'title' => 'Subscription confirmed',
            'message' => 'Thanks! '.$subscriber->email.' is now subscribed to '.$subscriber->list->name.'.',
        ]);
    }

    protected function success(Request $request, Form $form, bool $pending): View|RedirectResponse|JsonResponse
    {
        $message = $pending
            ? 'Almost done — check your inbox and click the link to confirm your subscription.'
            : ($form->success_message ?: 'Thank you for subscribing!');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }
        if ($form->redirect_url) {
            return redirect()->away($form->redirect_url);
        }

        return view('public.message', ['title' => $pending ? 'Check your inbox' : 'You are subscribed', 'message' => $message]);
    }

    protected function form(string $uid): Form
    {
        return Form::with('list.fields', 'list.workspace')->where('uid', $uid)->where('status', 'published')->firstOrFail();
    }
}

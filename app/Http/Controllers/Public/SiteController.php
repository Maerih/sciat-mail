<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Website;
use App\Sending\TrackingUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The script client websites include, and the page-view collector it reports to. */
class SiteController extends Controller
{
    public function script(string $uid): Response
    {
        $website = Website::where('uid', $uid)->first();
        if (! $website) {
            return $this->js('/* website not found */');
        }

        $forms = $website->forms()->with('list.fields', 'list.workspace')->where('status', 'published')->get();

        return $this->js(view('public.site-js', [
            'website' => $website,
            'popups' => $forms->filter->isPopup()->values(),
            'inline' => $forms->reject->isPopup()->mapWithKeys(fn ($form) => [$form->uid => [
                'html' => view('public.partials.form-embed', ['form' => $form])->render(),
                'action' => route('subscribe.store', $form->uid),
            ]]),
        ])->render(), 'public, max-age=300');
    }

    public function collect(Request $request, string $uid, TrackingUrls $urls): Response
    {
        $website = Website::where('uid', $uid)->first();
        $origin = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        // Only pages on the site itself may report, so others can't inflate its numbers
        if (! $website || ! $website->matchesHost(parse_url((string) $origin, PHP_URL_HOST))) {
            return $this->empty();
        }

        $website->markSeen();
        if (! $website->track_visits) {
            return $this->empty();
        }

        // sendBeacon posts text/plain to avoid a CORS preflight
        $data = json_decode($request->getContent(), true) ?: $request->all();
        $subscriberId = null;
        if (preg_match('/^([A-Za-z0-9]+)\.([a-f0-9]{20})$/', (string) ($data['id'] ?? ''), $m) && $urls->verify('site|'.$m[1], $m[2])) {
            $subscriberId = Delivery::where('uid', $m[1])->where('workspace_id', $website->workspace_id)->value('subscriber_id');
        }

        $website->visits()->create([
            'subscriber_id' => $subscriberId,
            'visitor' => substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['v'] ?? '')), 0, 32) ?: 'unknown',
            'path' => mb_substr((string) ($data['p'] ?? '/'), 0, 500),
            'referrer' => mb_substr((string) ($data['r'] ?? ''), 0, 500) ?: null,
        ]);

        return $this->empty();
    }

    protected function js(string $body, string $cache = 'no-store'): Response
    {
        return response($body, 200, ['Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => $cache]);
    }

    protected function empty(): Response
    {
        return response('', 204, ['Access-Control-Allow-Origin' => '*']);
    }
}

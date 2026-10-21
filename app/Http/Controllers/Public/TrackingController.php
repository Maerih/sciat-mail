<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Sending\MergeTags;
use App\Sending\MessageBuilder;
use App\Sending\TrackingUrls;
use App\Services\EventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TrackingController extends Controller
{
    /** 1x1 transparent GIF */
    public const PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(Request $request, string $uid, EventRecorder $events): Response
    {
        if ($delivery = Delivery::where('uid', $uid)->first()) {
            $events->open($delivery, $request->ip(), $request->userAgent());
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function click(Request $request, string $uid, string $sig, TrackingUrls $urls, EventRecorder $events): RedirectResponse
    {
        $url = $urls->unb64((string) $request->query('u'));

        // Signed per delivery+URL so the endpoint can't be used as an open redirect
        abort_unless($url !== '' && preg_match('#^https?://#i', $url) && $urls->verify($uid.'|'.$url, $sig), 404);

        if ($delivery = Delivery::where('uid', $uid)->first()) {
            $events->click($delivery, $url, $request->ip(), $request->userAgent());
        }

        return redirect()->away($url);
    }

    public function webView(string $uid, string $sig, TrackingUrls $urls, MessageBuilder $builder): Response
    {
        abort_unless($urls->verify('view|'.$uid, $sig), 404);
        $delivery = Delivery::with('campaign.workspace', 'subscriber.list.fields', 'workspace')->where('uid', $uid)->firstOrFail();
        abort_unless($delivery->campaign, 404);

        $campaign = $delivery->campaign;
        $values = $builder->values($campaign, $delivery, $delivery->subscriber);
        $html = $campaign->isPlain()
            ? '<pre style="white-space:pre-wrap;font:15px/1.6 system-ui,sans-serif;max-width:680px;margin:24px auto">'.e(MergeTags::render((string) $campaign->plain, $values, false)).'</pre>'
            : $builder->renderHtml($campaign, $delivery, $values, track: false);

        return response($html)->header('Content-Security-Policy', "script-src 'none'");
    }
}

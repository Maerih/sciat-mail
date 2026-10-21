<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Subscriber;
use App\Sending\TrackingUrls;
use App\Services\EventRecorder;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * GET shows a confirmation page (link scanners must not unsubscribe people);
 * POST unsubscribes — this is also the RFC 8058 one-click endpoint mail clients call.
 */
class UnsubscribeController extends Controller
{
    public function show(string $uid, string $sig, TrackingUrls $urls): View
    {
        $delivery = $this->delivery($uid, $sig, $urls);

        return view('public.unsubscribe', [
            'email' => $delivery->email,
            'listName' => $delivery->subscriber?->list?->name,
            'done' => $delivery->subscriber?->status === 'unsubscribed',
            'brand' => $delivery->workspace,
        ]);
    }

    public function confirm(Request $request, string $uid, string $sig, TrackingUrls $urls, EventRecorder $events): View|Response
    {
        $delivery = $this->delivery($uid, $sig, $urls);
        $oneClick = $request->input('List-Unsubscribe') === 'One-Click';
        $events->unsubscribe($delivery, $request->ip(), $request->userAgent(), $oneClick ? 'one-click' : 'link');

        if ($oneClick) {
            return response('Unsubscribed', 200);
        }

        return view('public.unsubscribe', ['email' => $delivery->email, 'listName' => $delivery->subscriber?->list?->name, 'done' => true, 'brand' => $delivery->workspace]);
    }

    public function showSubscriber(string $uid, string $sig, TrackingUrls $urls): View
    {
        $subscriber = $this->subscriber($uid, $sig, $urls);

        return view('public.unsubscribe', ['email' => $subscriber->email, 'listName' => $subscriber->list->name, 'done' => $subscriber->status === 'unsubscribed', 'brand' => $subscriber->list->workspace]);
    }

    public function confirmSubscriber(Request $request, string $uid, string $sig, TrackingUrls $urls, SubscriptionService $subscriptions): View|Response
    {
        $subscriber = $this->subscriber($uid, $sig, $urls);
        $subscriptions->unsubscribe($subscriber);

        if ($request->input('List-Unsubscribe') === 'One-Click') {
            return response('Unsubscribed', 200);
        }

        return view('public.unsubscribe', ['email' => $subscriber->email, 'listName' => $subscriber->list->name, 'done' => true, 'brand' => $subscriber->list->workspace]);
    }

    protected function delivery(string $uid, string $sig, TrackingUrls $urls): Delivery
    {
        abort_unless($urls->verify('unsub|'.$uid, $sig), 404);

        return Delivery::with('subscriber.list', 'workspace')->where('uid', $uid)->firstOrFail();
    }

    protected function subscriber(string $uid, string $sig, TrackingUrls $urls): Subscriber
    {
        abort_unless($urls->verify('unsub-s|'.$uid, $sig), 404);

        return Subscriber::with('list.workspace')->where('uid', $uid)->firstOrFail();
    }
}

<?php

namespace App\Sending;

use App\Models\Delivery;
use App\Models\Subscriber;
use App\Models\Workspace;

/**
 * Builds and verifies tracking URLs. Links use the workspace's verified tracking domain
 * when one exists so click/open traffic carries the client's own brand.
 */
class TrackingUrls
{
    public function base(?Workspace $workspace): string
    {
        $domain = $workspace?->defaultTrackingDomain();

        return rtrim($domain ? $domain->baseUrl() : config('app.url'), '/');
    }

    public function open(Delivery $delivery): string
    {
        return $this->base($delivery->workspace).'/t/o/'.$delivery->uid.'.gif';
    }

    public function click(Delivery $delivery, string $url): string
    {
        return $this->base($delivery->workspace).'/t/c/'.$delivery->uid.'/'.$this->sign($delivery->uid.'|'.$url)
            .'?u='.$this->b64($url);
    }

    public function unsubscribe(Delivery $delivery): string
    {
        return $this->base($delivery->workspace).'/u/'.$delivery->uid.'/'.$this->sign('unsub|'.$delivery->uid);
    }

    public function webView(Delivery $delivery): string
    {
        return $this->base($delivery->workspace).'/w/'.$delivery->uid.'/'.$this->sign('view|'.$delivery->uid);
    }

    public function subscriberUnsubscribe(Subscriber $subscriber): string
    {
        return $this->base($subscriber->list->workspace).'/u/s/'.$subscriber->uid.'/'.$this->sign('unsub-s|'.$subscriber->uid);
    }

    public function confirm(Subscriber $subscriber): string
    {
        return rtrim(config('app.url'), '/').'/subscribe/confirm/'.$subscriber->uid.'/'.$this->sign('confirm|'.$subscriber->uid);
    }

    public function sign(string $payload): string
    {
        return substr(hash_hmac('sha256', $payload, config('app.key')), 0, 20);
    }

    public function verify(string $payload, string $signature): bool
    {
        return hash_equals($this->sign($payload), $signature);
    }

    public function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public function unb64(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}

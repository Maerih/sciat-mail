<?php

namespace App\Services;

use App\Jobs\SendListEmail;
use App\Models\Blacklist;
use App\Models\MailList;
use App\Models\Subscriber;

class SubscriptionService
{
    /**
     * Add or re-subscribe an address. Honors double opt-in and never resurrects a
     * blacklisted address. Returns the subscriber and whether confirmation is pending.
     *
     * @return array{0: Subscriber, 1: bool}
     */
    public function subscribe(MailList $list, string $email, array $data = [], string $source = 'form', ?string $ip = null,
        bool $forceConfirmed = false, array $tags = []): array
    {
        $email = strtolower(trim($email));
        $subscriber = $list->subscribers()->firstOrNew(['email' => $email]);
        $wasSubscribed = $subscriber->exists && $subscriber->status === 'subscribed';

        $subscriber->data = array_merge($subscriber->data ?? [], array_filter($data, fn ($v) => $v !== null && $v !== ''));
        if ($tags) {
            $subscriber->addTags($tags);
        }
        $subscriber->source = $subscriber->exists ? $subscriber->source : $source;
        $subscriber->ip ??= $ip;

        if (Blacklist::isBlocked($email, $list->workspace_id)) {
            $subscriber->status = 'blacklisted';
            $subscriber->save();

            return [$subscriber, false];
        }

        if ($wasSubscribed) {
            $subscriber->save();

            return [$subscriber, false];
        }

        $needsConfirmation = $list->double_optin && ! $forceConfirmed;
        $subscriber->status = $needsConfirmation ? 'unconfirmed' : 'subscribed';
        $subscriber->unsubscribed_at = null;
        if (! $needsConfirmation) {
            $subscriber->confirmed_at ??= now();
        }
        $subscriber->save();

        if ($needsConfirmation) {
            SendListEmail::dispatch($subscriber->id, 'confirm');
        } elseif ($list->send_welcome_email) {
            SendListEmail::dispatch($subscriber->id, 'welcome');
        }

        return [$subscriber, $needsConfirmation];
    }

    public function confirm(Subscriber $subscriber): void
    {
        if ($subscriber->status !== 'unconfirmed') {
            return;
        }

        $subscriber->forceFill(['status' => 'subscribed', 'confirmed_at' => now()])->save();
        if ($subscriber->list->send_welcome_email) {
            SendListEmail::dispatch($subscriber->id, 'welcome');
        }
    }

    public function unsubscribe(Subscriber $subscriber): void
    {
        if ($subscriber->status === 'unsubscribed') {
            return;
        }

        $subscriber->unsubscribe();
        if ($subscriber->list->unsubscribe_notification) {
            SendListEmail::dispatch($subscriber->id, 'unsubscribe_notice');
        }
    }
}

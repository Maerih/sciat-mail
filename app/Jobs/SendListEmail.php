<?php

namespace App\Jobs;

use App\Models\Subscriber;
use App\Sending\TrackingUrls;
use App\Services\SystemMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Opt-in confirmation, welcome email and unsubscribe notice for a list. */
class SendListEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $subscriberId, public string $type) {}

    public function handle(SystemMailer $mailer, TrackingUrls $urls): void
    {
        $subscriber = Subscriber::with('list.workspace', 'list.fields')->find($this->subscriberId);
        if (! $subscriber) {
            return;
        }

        $list = $subscriber->list;
        $workspace = $list->workspace;

        [$to, $subject, $html] = match ($this->type) {
            'confirm' => [$subscriber->email, 'Please confirm your subscription to '.$list->name,
                view('emails.confirm', ['list' => $list, 'url' => $urls->confirm($subscriber), 'brandWorkspace' => $workspace])->render()],
            'welcome' => [$subscriber->email, $list->welcome_subject ?: 'Welcome to '.$list->name,
                view('emails.welcome', [
                    'list' => $list,
                    'body' => $list->welcome_html,
                    'subscriber' => $subscriber,
                    'unsubscribeUrl' => $urls->subscriberUnsubscribe($subscriber),
                    'brandWorkspace' => $workspace,
                ])->render()],
            'unsubscribe_notice' => [$list->notification_email ?: $workspace->contact_email ?: $list->from_email,
                $subscriber->email.' unsubscribed from '.$list->name,
                view('emails.unsubscribe-notice', ['list' => $list, 'subscriber' => $subscriber])->render()],
        };

        if (! $to) {
            Log::warning('List email skipped: no recipient', ['type' => $this->type, 'list' => $list->id]);

            return;
        }

        $mailer->send($workspace, $to, $subject, $html, $list->from_email, $list->from_name, $list,
            subscriberId: $this->type === 'unsubscribe_notice' ? null : $subscriber->id);
    }
}

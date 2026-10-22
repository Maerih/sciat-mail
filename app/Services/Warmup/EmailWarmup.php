<?php

namespace App\Services\Warmup;

use App\Models\SendingServer;
use App\Models\WarmupEmail;
use App\Models\WarmupMailbox;
use App\Models\Workspace;
use App\Sending\SendFailed;
use App\Services\SystemMailer;
use Illuminate\Support\Str;

/**
 * Self-hosted email warmup: sends a small, growing number of natural emails a day from a
 * sending server to seed inboxes you own, then rescues any that land in spam.
 */
class EmailWarmup
{
    /** Local hours during which warmup emails are spread. */
    public const WINDOW = [8, 20];

    public function __construct(protected SystemMailer $mailer, protected WarmupInbox $inbox) {}

    /** Called hourly. Returns how many warmup emails were sent. */
    public function sendDue(): int
    {
        $sent = 0;
        $servers = SendingServer::where('status', 'active')->whereNotNull('email_warmup')->get()->filter(fn ($s) => $s->emailWarmup('enabled'));
        foreach ($servers as $server) {
            $sent += $this->sendFor($server);
        }

        return $sent;
    }

    public function sendFor(SendingServer $server, bool $ignoreWindow = false): int
    {
        $timezone = $server->workspace?->timezone ?? 'Africa/Nairobi';
        $hour = (int) now($timezone)->format('G');
        if (! $ignoreWindow && ($hour < self::WINDOW[0] || $hour >= self::WINDOW[1])) {
            return 0;
        }
        $mailboxes = WarmupMailbox::where('status', 'active')
            ->when($server->workspace_id, fn ($q) => $q->where('workspace_id', $server->workspace_id))->get();
        $from = (string) $server->emailWarmup('from_email');
        if ($mailboxes->isEmpty() || $from === '') {
            return 0;
        }

        $today = WarmupEmail::where('sending_server_id', $server->id)->where('sent_at', '>=', now($timezone)->startOfDay()->utc())->count();
        $left = max(0, $server->emailWarmupTarget() - $today);
        $hoursLeft = max(1, self::WINDOW[1] - max($hour, self::WINDOW[0]));
        $batch = $ignoreWindow ? $left : (int) ceil($left / $hoursLeft);

        $sent = 0;
        for ($i = 0; $i < $batch; $i++) {
            $mailbox = $mailboxes->random();
            [$subject, $html] = $this->compose($token = strtoupper(Str::random(10)));
            $workspace = $server->workspace ?? Workspace::find($mailbox->workspace_id);
            $email = WarmupEmail::create(['workspace_id' => $workspace->id, 'sending_server_id' => $server->id, 'warmup_mailbox_id' => $mailbox->id, 'token' => $token, 'subject' => $subject]);
            try {
                $this->mailer->send($workspace, $mailbox->email, $subject, $html, $from, $workspace->company ?: $workspace->name, server: $server, kind: 'warmup');
                $email->forceFill(['sent_at' => now()])->save();
                $sent++;
            } catch (SendFailed) {
                $email->forceFill(['placement' => 'failed'])->save();
            }
        }

        return $sent;
    }

    /** Called every 15 minutes: finds warmup emails in the seed inboxes and rescues spam. */
    public function check(): int
    {
        $checked = 0;
        foreach (WarmupMailbox::where('status', 'active')->get() as $mailbox) {
            $pending = $mailbox->emails()->where('placement', 'pending')->whereNotNull('sent_at')->where('sent_at', '>=', now()->subDays(3))->pluck('token')->all();
            if ($pending) {
                try {
                    foreach ($this->inbox->scan($mailbox, $pending) as $token => $placement) {
                        WarmupEmail::where('token', $token)->update(['placement' => $placement, 'rescued' => $placement === 'spam', 'checked_at' => now()]);
                        $checked++;
                    }
                    $mailbox->forceFill(['last_checked_at' => now(), 'last_error' => null])->save();
                } catch (\Throwable $e) {
                    $mailbox->forceFill(['last_checked_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 250)])->save();
                }
            }
            // Not found within two days: count as missing (blocked or deleted by the provider)
            $mailbox->emails()->where('placement', 'pending')->where('sent_at', '<', now()->subDays(2))->update(['placement' => 'missing', 'checked_at' => now()]);
        }

        return $checked;
    }

    /** @return array{0: string, 1: string} subject and HTML, varied so no two look alike */
    public function compose(string $token): array
    {
        $topics = ['the project timeline', 'next week\'s meeting', 'the quarterly report', 'our catch-up', 'the proposal draft', 'the client feedback', 'the training session', 'the budget review', 'the new office plan', 'the team lunch'];
        $openers = ['Quick question about', 'Following up on', 'Thoughts on', 'A note about', 'Update on', 'Checking in about'];
        $bodies = [
            'I had a look at %s and I think we are in good shape. Could you share your thoughts when you have a moment?',
            'Just wanted to follow up on %s. Let me know if Thursday afternoon still works for you.',
            'Thanks again for your help with %s. I have added a few notes and will send the final version soon.',
            'Hope your week is going well. Do you have ten minutes tomorrow to go over %s together?',
            'I spoke with the team about %s and everyone is on board. I will put the details in our shared folder.',
        ];
        $closers = ['Best regards', 'Thanks', 'Kind regards', 'Cheers', 'Talk soon'];
        $topic = $topics[array_rand($topics)];
        $subject = $openers[array_rand($openers)].' '.$topic;
        $paragraph = sprintf($bodies[array_rand($bodies)], $topic);

        $html = '<div style="font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#222">'
            .'<p>Hi,</p><p>'.e($paragraph).'</p><p>'.e($closers[array_rand($closers)]).'</p>'
            .'<p style="color:#999;font-size:11px">Ref: '.$token.'</p></div>';

        return [$subject, $html];
    }

    /** @return array{sent: int, inbox: int, spam: int, missing: int, inbox_rate: float} */
    public static function stats(SendingServer $server, int $days = 14): array
    {
        $counts = WarmupEmail::where('sending_server_id', $server->id)->where('sent_at', '>=', now()->subDays($days))
            ->selectRaw('placement, count(*) as c')->groupBy('placement')->pluck('c', 'placement');
        $judged = (int) (($counts['inbox'] ?? 0) + ($counts['spam'] ?? 0) + ($counts['missing'] ?? 0));

        return [
            'sent' => (int) $counts->sum() - (int) ($counts['failed'] ?? 0),
            'inbox' => (int) ($counts['inbox'] ?? 0),
            'spam' => (int) ($counts['spam'] ?? 0),
            'missing' => (int) ($counts['missing'] ?? 0),
            'inbox_rate' => $judged ? round(($counts['inbox'] ?? 0) / $judged * 100, 1) : 0.0,
        ];
    }
}

<?php

namespace App\Jobs;

use App\Models\MailList;
use App\Models\Subscriber;
use App\Models\VerificationServer;
use App\Services\Verification\EmailVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Verifies a list (only unverified contacts unless $recheck) or a given set of subscribers. */
class VerifySubscribers implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    /** @param int[]|null $subscriberIds */
    public function __construct(public int $listId, public ?array $subscriberIds = null, public bool $recheck = false) {}

    public function handle(EmailVerifier $verifier): void
    {
        $list = MailList::with('workspace')->find($this->listId);
        if (! $list) {
            return;
        }

        $server = VerificationServer::activeFor($list->workspace);
        $query = $list->subscribers()
            ->when($this->subscriberIds !== null, fn ($q) => $q->whereIn('id', $this->subscriberIds))
            ->when($this->subscriberIds === null && ! $this->recheck, fn ($q) => $q->where('verification_status', 'unverified'));

        $list->forceFill(['verification_job_status' => 'running', 'verification_job_total' => (clone $query)->count()])->save();

        $query->chunkById(200, function ($subscribers) use ($verifier, $server) {
            foreach ($subscribers as $subscriber) {
                [$status, $reason] = $verifier->verify($subscriber->email, $server);
                Subscriber::whereKey($subscriber->id)->update([
                    'verification_status' => $status,
                    'verification_reason' => $reason ? mb_substr($reason, 0, 250) : null,
                    'verified_at' => now(),
                ]);
            }
        });

        $list->forceFill(['verification_job_status' => 'done'])->save();
    }
}

<?php

namespace App\Services\Bounces;

use App\Models\BounceHandler;
use App\Models\Delivery;
use App\Services\EventRecorder;

/** Polls a bounce handler's mailbox and records each bounce or complaint it finds. */
class BounceProcessor
{
    public const BATCH = 200;

    public function __construct(protected Mailbox $mailbox, protected EventRecorder $events) {}

    /** @return array{read: int, bounces: int, complaints: int, unmatched: int} */
    public function process(BounceHandler $handler): array
    {
        $stats = ['read' => 0, 'bounces' => 0, 'complaints' => 0, 'unmatched' => 0];

        try {
            foreach ($this->mailbox->unread($handler, self::BATCH) as $message) {
                $stats['read']++;
                $report = BounceParser::parse($message['raw']);
                $delivery = $report ? $this->delivery($report, $handler) : null;

                if ($report && $delivery) {
                    if ($report['type'] === 'complaint') {
                        $this->events->complaint($delivery, $report['detail']);
                        $stats['complaints']++;
                    } else {
                        $this->events->bounce($delivery, $report['hard'] ? 'hard' : 'soft', $report['detail']);
                        $stats['bounces']++;
                    }
                } elseif ($report) {
                    $stats['unmatched']++;
                }

                // Unrelated mail stays unread for a human; reports are marked (or deleted)
                if ($report) {
                    $this->mailbox->markProcessed($handler, $message['id'], (bool) $handler->delete_processed);
                }
            }
            $this->mailbox->close();
            $handler->forceFill(['last_error' => null])->save();
        } catch (\Throwable $e) {
            $handler->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        $handler->forceFill([
            'last_checked_at' => now(),
            'processed_count' => $handler->processed_count + $stats['bounces'] + $stats['complaints'],
        ])->save();

        return $stats;
    }

    protected function delivery(array $report, BounceHandler $handler): ?Delivery
    {
        $delivery = $this->events->findDelivery($report['delivery_uid'], $report['message_id']);

        // Last resort: the most recent delivery to the bounced address in this workspace
        if (! $delivery && $report['recipient']) {
            $delivery = Delivery::where('email', $report['recipient'])
                ->when($handler->workspace_id, fn ($q) => $q->where('workspace_id', $handler->workspace_id))
                ->whereNotNull('sent_at')->latest('sent_at')->first();
        }

        // A handler only ever touches its own workspace's deliveries
        if ($delivery && $handler->workspace_id && $delivery->workspace_id !== $handler->workspace_id) {
            return null;
        }

        return $delivery;
    }
}

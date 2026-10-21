<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\MailList;
use App\Models\SendingServer;
use App\Models\Workspace;
use App\Sending\Dispatcher;
use App\Sending\MessageBuilder;
use App\Sending\SendFailed;
use App\Sending\ServerPicker;

/**
 * Sends one-off messages (opt-in confirmation, welcome, notifications, tests) through
 * the workspace's sending servers and records them in the delivery ledger.
 */
class SystemMailer
{
    public function __construct(
        protected ServerPicker $picker,
        protected MessageBuilder $builder,
        protected Dispatcher $dispatcher,
    ) {}

    public function serverFor(Workspace $workspace, ?MailList $list = null): ?SendingServer
    {
        if ($list?->defaultSendingServer?->isActive()) {
            return $list->defaultSendingServer;
        }

        return $this->picker->pick($this->picker->pool($workspace));
    }

    /** @throws SendFailed */
    public function send(Workspace $workspace, string $to, string $subject, string $html, string $fromEmail, ?string $fromName = null,
        ?MailList $list = null, ?SendingServer $server = null, string $kind = 'system', ?int $subscriberId = null): Delivery
    {
        $server ??= $this->serverFor($workspace, $list);
        if (! $server) {
            throw new SendFailed('No active sending server is available.', false);
        }

        $delivery = Delivery::create([
            'workspace_id' => $workspace->id,
            'subscriber_id' => $subscriberId,
            'email' => strtolower($to),
            'kind' => $kind,
            'status' => 'pending',
            'sending_server_id' => $server->id,
        ]);

        try {
            $message = $this->builder->simple($fromEmail, $fromName, $to, $subject, $html, $workspace->id, $server, $delivery);
            $messageId = $this->dispatcher->send($message, $server, $fromEmail, $to);
            $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'provider_message_id' => $messageId, 'attempts' => 1])->save();
        } catch (SendFailed $e) {
            $delivery->forceFill(['status' => 'failed', 'error' => $e->getMessage(), 'attempts' => 1])->save();
            throw $e;
        }

        return $delivery;
    }
}

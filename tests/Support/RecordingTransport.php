<?php

namespace Tests\Support;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * In-memory transport: records every message, or fails with a configured error.
 */
class RecordingTransport extends AbstractTransport
{
    /** @var SentMessage[] */
    public array $sent = [];

    public ?string $failWith = null;

    protected function doSend(SentMessage $message): void
    {
        if ($this->failWith !== null) {
            throw new TransportException($this->failWith);
        }
        $this->sent[] = $message;
    }

    public function __toString(): string
    {
        return 'recording://';
    }

    /** @return string[] */
    public function recipients(): array
    {
        return array_map(fn (SentMessage $m) => $m->getEnvelope()->getRecipients()[0]->getAddress(), $this->sent);
    }
}

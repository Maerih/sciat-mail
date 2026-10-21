<?php

namespace App\Sending;

use App\Models\SendingServer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Message;

/**
 * Sends a built message through a server and reports provider id / transient-ness of errors.
 */
class Dispatcher
{
    public function __construct(protected TransportFactory $transports, protected Throttle $throttle) {}

    /** @return string|null provider message id */
    public function send(Message $message, SendingServer $server, string $from, string $to): ?string
    {
        // Bounces go to the server's bounce mailbox (Return-Path) when one is configured
        $envelope = new Envelope(new Address($server->bounce_address ?: $from), [new Address($to)]);

        try {
            $sent = $this->transports->for($server)->send($message, $envelope);
        } catch (TransportExceptionInterface $e) {
            // a broken SMTP connection must not be reused for the next message
            $this->transports->forget($server);
            throw new SendFailed($e->getMessage(), static::isTransient($e->getMessage()), $e);
        } finally {
            $this->throttle->hit($server);
        }

        return $sent?->getMessageId();
    }

    /** 4xx SMTP replies, timeouts and provider throttling are worth retrying; 5xx are not. */
    public static function isTransient(string $error): bool
    {
        if (preg_match('/\b5\d\d\b/', $error) && ! preg_match('/\b(421|45\d)\b/', $error)) {
            return false;
        }

        return (bool) preg_match('/\b4\d\d\b|timed? ?out|timeout|connection|throttl|rate|too many|temporar|try again/i', $error);
    }
}

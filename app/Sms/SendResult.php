<?php

namespace App\Sms;

final class SendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
        public readonly string $status = 'sent',
    ) {}

    public static function failed(string $error): self
    {
        return new self(false, null, mb_substr($error, 0, 250), 'failed');
    }
}

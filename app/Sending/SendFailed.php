<?php

namespace App\Sending;

class SendFailed extends \RuntimeException
{
    public function __construct(string $message, public readonly bool $transient, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

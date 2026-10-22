<?php

namespace App\Sms\Drivers;

use App\Models\SmsProvider;
use App\Sms\SendResult;
use Illuminate\Http\Request;

interface SmsDriver
{
    /** $to is E.164 (+254712345678). */
    public function send(SmsProvider $provider, string $to, string $body, ?string $from): SendResult;

    /**
     * Delivery receipts in a webhook request.
     *
     * @return array<int, array{id: string, status: string, error: ?string}> status: sent | delivered | failed
     */
    public function statusUpdates(Request $request): array;

    /**
     * Incoming message in a webhook request.
     *
     * @return array{from: string, to: ?string, text: string, id: ?string}|null
     */
    public function inbound(Request $request): ?array;
}

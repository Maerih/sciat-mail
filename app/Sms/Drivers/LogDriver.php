<?php

namespace App\Sms\Drivers;

use App\Models\SmsProvider;
use App\Sms\SendResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Test mode: writes messages to the log instead of sending, and reports them delivered. */
class LogDriver implements SmsDriver
{
    public function send(SmsProvider $provider, string $to, string $body, ?string $from): SendResult
    {
        Log::info('SMS (test mode)', ['provider' => $provider->name, 'to' => $to, 'from' => $from, 'body' => $body]);

        return new SendResult(true, 'log-'.Str::random(16), null, 'delivered');
    }

    public function statusUpdates(Request $request): array
    {
        return [];
    }

    public function inbound(Request $request): ?array
    {
        return null;
    }
}

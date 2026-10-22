<?php

namespace App\Sms\Drivers;

use App\Models\SmsProvider;
use App\Sms\SendResult;
use App\Sms\SmsText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Vonage (formerly Nexmo) SMS API. */
class Vonage implements SmsDriver
{
    public function send(SmsProvider $provider, string $to, string $body, ?string $from): SendResult
    {
        $response = Http::asForm()->acceptJson()->timeout(20)->post('https://rest.nexmo.com/sms/json', array_filter([
            'api_key' => $provider->configValue('api_key'),
            'api_secret' => $provider->configValue('api_secret'),
            'from' => $from ?: 'SMS',
            'to' => ltrim($to, '+'),
            'text' => $body,
            'type' => SmsText::isGsm($body) ? null : 'unicode',
            'callback' => $provider->webhookUrl('status'),
        ]));

        $message = $response->json('messages.0');

        return $message && (string) ($message['status'] ?? '') === '0'
            ? new SendResult(true, $message['message-id'] ?? null)
            : SendResult::failed($message['error-text'] ?? 'HTTP '.$response->status());
    }

    public function statusUpdates(Request $request): array
    {
        if (! $request->filled('messageId') || ! $request->filled('status')) {
            return [];
        }

        return [[
            'id' => (string) $request->input('messageId'),
            'status' => match ($request->input('status')) {
                'delivered' => 'delivered',
                'failed', 'rejected', 'expired' => 'failed',
                default => 'sent',
            },
            'error' => $request->input('err-code') && $request->input('err-code') !== '0' ? 'Vonage error '.$request->input('err-code') : null,
        ]];
    }

    public function inbound(Request $request): ?array
    {
        return $request->filled('msisdn') && $request->has('text') && ! $request->filled('status')
            ? ['from' => (string) $request->input('msisdn'), 'to' => $request->input('to'), 'text' => (string) $request->input('text'), 'id' => $request->input('messageId')]
            : null;
    }
}

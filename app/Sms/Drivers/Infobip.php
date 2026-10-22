<?php

namespace App\Sms\Drivers;

use App\Models\SmsProvider;
use App\Sms\SendResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Infobip — global, with strong direct-carrier coverage across Africa. */
class Infobip implements SmsDriver
{
    public function send(SmsProvider $provider, string $to, string $body, ?string $from): SendResult
    {
        $base = 'https://'.preg_replace('#^https?://|/+$#', '', (string) $provider->configValue('base_url'));
        $response = Http::acceptJson()->timeout(20)
            ->withHeaders(['Authorization' => 'App '.$provider->configValue('api_key')])
            ->post($base.'/sms/2/text/advanced', ['messages' => [array_filter([
                'destinations' => [['to' => ltrim($to, '+')]],
                'from' => $from,
                'text' => $body,
                'notifyUrl' => $provider->webhookUrl('status'),
                'notifyContentType' => 'application/json',
            ])]]);

        $message = $response->json('messages.0');
        if (! $response->successful() || ! $message) {
            return SendResult::failed($response->json('requestError.serviceException.text') ?: 'HTTP '.$response->status());
        }
        if (in_array($message['status']['groupName'] ?? '', ['REJECTED', 'UNDELIVERABLE'], true)) {
            return SendResult::failed($message['status']['description'] ?? 'Rejected');
        }

        return new SendResult(true, $message['messageId'] ?? null);
    }

    public function statusUpdates(Request $request): array
    {
        return collect($request->input('results', []))->filter(fn ($r) => isset($r['messageId']))->map(fn ($r) => [
            'id' => (string) $r['messageId'],
            'status' => match ($r['status']['groupName'] ?? '') {
                'DELIVERED' => 'delivered',
                'UNDELIVERABLE', 'REJECTED', 'EXPIRED' => 'failed',
                default => 'sent',
            },
            'error' => ($r['error']['groupName'] ?? 'OK') !== 'OK' ? ($r['error']['description'] ?? null) : null,
        ])->values()->all();
    }

    public function inbound(Request $request): ?array
    {
        $r = $request->input('results.0');

        return isset($r['from'], $r['text']) && ! isset($r['status'])
            ? ['from' => (string) $r['from'], 'to' => $r['to'] ?? null, 'text' => (string) ($r['cleanText'] ?? $r['text']), 'id' => $r['messageId'] ?? null]
            : null;
    }
}

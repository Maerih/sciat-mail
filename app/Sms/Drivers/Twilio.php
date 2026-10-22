<?php

namespace App\Sms\Drivers;

use App\Models\SmsProvider;
use App\Sms\SendResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Twilio implements SmsDriver
{
    public function send(SmsProvider $provider, string $to, string $body, ?string $from): SendResult
    {
        $sid = $provider->configValue('account_sid');
        $service = $provider->configValue('messaging_service_sid');
        $response = Http::asForm()->timeout(20)->withBasicAuth($sid, $provider->configValue('auth_token'))
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", array_filter([
                'To' => $to,
                'Body' => $body,
                'From' => $service ? null : $from,
                'MessagingServiceSid' => $service ?: null,
                'StatusCallback' => $provider->webhookUrl('status'),
            ]));

        return $response->successful() && $response->json('sid')
            ? new SendResult(true, $response->json('sid'))
            : SendResult::failed($response->json('message') ?: 'HTTP '.$response->status());
    }

    public function statusUpdates(Request $request): array
    {
        if (! $request->filled('MessageSid') || ! $request->filled('MessageStatus')) {
            return [];
        }
        $status = match ($request->input('MessageStatus')) {
            'delivered', 'read' => 'delivered',
            'failed', 'undelivered', 'canceled' => 'failed',
            default => 'sent',
        };

        return [['id' => (string) $request->input('MessageSid'), 'status' => $status, 'error' => $request->filled('ErrorCode') ? 'Twilio error '.$request->input('ErrorCode') : null]];
    }

    public function inbound(Request $request): ?array
    {
        return $request->filled('From') && $request->has('Body') && ! $request->filled('MessageStatus')
            ? ['from' => (string) $request->input('From'), 'to' => $request->input('To'), 'text' => (string) $request->input('Body'), 'id' => $request->input('MessageSid')]
            : null;
    }
}

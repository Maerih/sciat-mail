<?php

namespace App\Sms\Drivers;

use App\Models\SmsProvider;
use App\Sms\SendResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Africa's Talking — Kenya, Uganda, Tanzania, Rwanda, Nigeria, Malawi, Zambia and more. */
class AfricasTalking implements SmsDriver
{
    public function send(SmsProvider $provider, string $to, string $body, ?string $from): SendResult
    {
        $sandbox = $provider->configValue('username') === 'sandbox';
        $response = Http::asForm()->acceptJson()->timeout(20)
            ->withHeaders(['apiKey' => $provider->configValue('api_key')])
            ->post($sandbox ? 'https://api.sandbox.africastalking.com/version1/messaging' : 'https://api.africastalking.com/version1/messaging', array_filter([
                'username' => $provider->configValue('username'),
                'to' => $to,
                'message' => $body,
                'from' => $from,
            ]));

        $recipient = $response->json('SMSMessageData.Recipients.0');
        if (! $response->successful() || ! $recipient) {
            return SendResult::failed($response->json('SMSMessageData.Message') ?: 'HTTP '.$response->status().': '.mb_substr($response->body(), 0, 200));
        }
        // 100 Processed, 101 Sent, 102 Queued; anything else is a failure (e.g. 403 invalid number, 405 insufficient balance)
        if (! in_array((int) $recipient['statusCode'], [100, 101, 102], true)) {
            return SendResult::failed($recipient['status'] ?? 'Rejected');
        }

        return new SendResult(true, $recipient['messageId'] ?? null);
    }

    public function statusUpdates(Request $request): array
    {
        if (! $request->filled('id') || ! $request->filled('status')) {
            return [];
        }
        $status = match ($request->input('status')) {
            'Success' => 'delivered',
            'Failed', 'Rejected' => 'failed',
            default => 'sent', // Sent, Submitted, Buffered
        };

        return [['id' => (string) $request->input('id'), 'status' => $status, 'error' => $request->input('failureReason')]];
    }

    public function inbound(Request $request): ?array
    {
        return $request->filled('from') && $request->has('text')
            ? ['from' => (string) $request->input('from'), 'to' => $request->input('to'), 'text' => (string) $request->input('text'), 'id' => $request->input('id')]
            : null;
    }
}

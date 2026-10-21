<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SendingServer;
use App\Services\EventRecorder;
use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bounce / complaint / unsubscribe feedback from sending providers. Every URL carries the
 * server uid and a per-server secret; providers that sign payloads are verified as well.
 */
class WebhookController extends Controller
{
    public function __construct(protected EventRecorder $events) {}

    public function ses(Request $request, string $server, string $secret, MessageValidator $validator): JsonResponse
    {
        $this->server($server, $secret, 'ses');

        try {
            $message = new Message(json_decode($request->getContent(), true) ?: []);
            $validator->validate($message);
        } catch (InvalidSnsMessageException|\InvalidArgumentException $e) {
            abort(403, 'Invalid SNS message');
        }

        if ($message['Type'] === 'SubscriptionConfirmation') {
            $url = (string) $message['SubscribeURL'];
            if (preg_match('#^https://sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?/#', $url)) {
                Http::timeout(10)->get($url);
            }

            return response()->json(['ok' => true, 'confirmed' => true]);
        }

        if ($message['Type'] === 'Notification') {
            $this->handleSesNotification(json_decode((string) $message['Message'], true) ?: []);
        }

        return response()->json(['ok' => true]);
    }

    /** Separated from signature checking so it can be exercised directly. */
    public function handleSesNotification(array $payload): void
    {
        $type = $payload['notificationType'] ?? $payload['eventType'] ?? null;
        $mail = $payload['mail'] ?? [];
        $uid = $mail['tags']['delivery'][0] ?? collect($mail['headers'] ?? [])->firstWhere('name', 'X-SM-Delivery')['value'] ?? null;
        $delivery = $this->events->findDelivery($uid, $mail['messageId'] ?? null);
        if (! $delivery) {
            return;
        }

        if ($type === 'Bounce') {
            $bounce = $payload['bounce'] ?? [];
            $hard = ($bounce['bounceType'] ?? '') === 'Permanent';
            foreach ($bounce['bouncedRecipients'] ?? [] as $recipient) {
                if (strcasecmp($recipient['emailAddress'] ?? '', $delivery->email) === 0) {
                    $this->events->bounce($delivery, $hard ? 'hard' : 'soft', $recipient['diagnosticCode'] ?? ($bounce['bounceSubType'] ?? null));
                }
            }
        } elseif ($type === 'Complaint') {
            $this->events->complaint($delivery, $payload['complaint']['complaintFeedbackType'] ?? null);
        }
    }

    public function sendgrid(Request $request, string $server, string $secret): JsonResponse
    {
        $this->server($server, $secret, 'sendgrid');

        foreach ((array) $request->json()->all() as $event) {
            if (! is_array($event)) {
                continue;
            }
            $providerId = isset($event['sg_message_id']) ? explode('.', $event['sg_message_id'])[0] : null;
            $delivery = $this->events->findDelivery($event['delivery'] ?? null, $providerId);
            if (! $delivery) {
                continue;
            }

            match ($event['event'] ?? null) {
                'bounce' => $this->events->bounce($delivery, ($event['type'] ?? 'bounce') === 'blocked' ? 'soft' : 'hard', $event['reason'] ?? null),
                'dropped' => str_contains(strtolower($event['reason'] ?? ''), 'bounce')
                    ? $this->events->bounce($delivery, 'hard', $event['reason']) : null,
                'spamreport' => $this->events->complaint($delivery),
                'unsubscribe', 'group_unsubscribe' => $this->events->unsubscribe($delivery, via: 'provider'),
                default => null,
            };
        }

        return response()->json(['ok' => true]);
    }

    public function mailgun(Request $request, string $server, string $secret): JsonResponse
    {
        $sendingServer = $this->server($server, $secret, 'mailgun');

        $signingKey = $sendingServer->configValue('webhook_signing_key');
        $signature = $request->input('signature', []);
        if ($signingKey) {
            $expected = hash_hmac('sha256', ($signature['timestamp'] ?? '').($signature['token'] ?? ''), $signingKey);
            abort_unless(hash_equals($expected, (string) ($signature['signature'] ?? '')), 403, 'Invalid Mailgun signature');
        }

        $event = $request->input('event-data', []);
        $delivery = $this->events->findDelivery(
            $event['user-variables']['delivery'] ?? null,
            $event['message']['headers']['message-id'] ?? null
        );
        if (! $delivery) {
            return response()->json(['ok' => true]);
        }

        $reason = $event['delivery-status']['description'] ?? $event['delivery-status']['message'] ?? $event['reason'] ?? null;
        match ($event['event'] ?? null) {
            'failed' => $this->events->bounce($delivery, ($event['severity'] ?? '') === 'permanent' ? 'hard' : 'soft', $reason),
            'complained' => $this->events->complaint($delivery),
            'unsubscribed' => $this->events->unsubscribe($delivery, via: 'provider'),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    protected function server(string $uid, string $secret, string $provider): SendingServer
    {
        $server = SendingServer::where('uid', $uid)->first();
        if (! $server || ! hash_equals($server->webhook_secret, $secret) || $server->webhookProvider() !== $provider) {
            Log::warning('Rejected webhook', ['provider' => $provider, 'server' => $uid]);
            abort(404);
        }

        return $server;
    }
}

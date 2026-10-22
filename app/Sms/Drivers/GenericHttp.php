<?php

namespace App\Sms\Drivers;

use App\Models\SmsProvider;
use App\Sms\PhoneNumber;
use App\Sms\SendResult;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Any HTTP SMS gateway (Celcom Africa, Advanta, Onfon, Bulk SMS Kenya, ...). Parameters are
 * "key=value" lines with placeholders: {to} {to_plain} {to_local} {message} {sender}.
 */
class GenericHttp implements SmsDriver
{
    public function send(SmsProvider $provider, string $to, string $body, ?string $from): SendResult
    {
        $replace = ['{to}' => $to, '{to_plain}' => ltrim($to, '+'), '{to_local}' => PhoneNumber::local($to), '{message}' => $body, '{sender}' => (string) $from];
        $params = $this->lines($provider->configValue('params'), $replace);
        $headers = $this->lines($provider->configValue('headers'), $replace);
        $url = (string) $provider->configValue('url');
        $request = Http::timeout(20)->acceptJson()->withHeaders($headers);

        $response = match ($provider->configValue('method', 'post_form')) {
            'get' => $request->get($url, $params),
            'post_json' => $request->asJson()->post($url, $params),
            default => $request->asForm()->post($url, $params),
        };

        $body = $response->body();
        $successText = (string) $provider->configValue('success_contains');
        if (! $response->successful() || ($successText !== '' && ! str_contains($body, $successText))) {
            return SendResult::failed('HTTP '.$response->status().': '.mb_substr(trim(strip_tags($body)), 0, 200));
        }
        $idPath = (string) $provider->configValue('message_id_path');

        return new SendResult(true, $idPath !== '' ? (string) (Arr::get($response->json() ?? [], $idPath) ?? '') ?: null : null);
    }

    public function statusUpdates(Request $request): array
    {
        $id = $request->input('messageId', $request->input('message_id', $request->input('id')));
        $status = strtolower((string) $request->input('status', $request->input('deliveryStatus', '')));
        if (! $id || $status === '') {
            return [];
        }

        return [[
            'id' => (string) $id,
            'status' => match (true) {
                str_contains($status, 'deliver') || $status === 'success' => 'delivered',
                str_contains($status, 'fail') || str_contains($status, 'reject') || str_contains($status, 'expire') => 'failed',
                default => 'sent',
            },
            'error' => $request->input('reason', $request->input('error')),
        ]];
    }

    public function inbound(Request $request): ?array
    {
        $from = $request->input('from', $request->input('msisdn', $request->input('mobile', $request->input('phone'))));
        $text = $request->input('text', $request->input('message', $request->input('body')));

        return $from && $text !== null ? ['from' => (string) $from, 'to' => $request->input('to', $request->input('shortcode')), 'text' => (string) $text, 'id' => $request->input('id')] : null;
    }

    /** @return array<string, string> */
    protected function lines(?string $text, array $replace): array
    {
        return collect(preg_split('/\r?\n/', (string) $text))->map('trim')->filter(fn ($line) => str_contains($line, '='))
            ->mapWithKeys(function ($line) use ($replace) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));

                return [$key => strtr($value, $replace)];
            })->all();
    }
}

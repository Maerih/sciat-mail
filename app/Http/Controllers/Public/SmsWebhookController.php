<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SmsProvider;
use App\Sms\SmsManager;
use App\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Delivery reports and incoming messages posted by SMS gateways. */
class SmsWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, string $secret, string $kind, SmsService $sms): Response
    {
        $provider = SmsProvider::where('uid', $provider)->first();
        abort_unless($provider && hash_equals($provider->secret, $secret), 404);
        $driver = SmsManager::driver($provider->type);

        if ($kind === 'status') {
            $sms->applyStatusUpdates($provider, $driver->statusUpdates($request));
        } elseif ($data = $driver->inbound($request)) {
            $sms->receive($provider, $data);
        }

        // Twilio expects TwiML; an empty response means "no automatic reply"
        return $provider->type === 'twilio'
            ? response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200, ['Content-Type' => 'text/xml'])
            : response('OK', 200);
    }
}

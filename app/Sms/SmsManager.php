<?php

namespace App\Sms;

/** Provider catalogue: form fields per type ('secret' fields are never echoed back) and the driver class. */
class SmsManager
{
    public const TYPES = [
        'africastalking' => [
            'label' => "Africa's Talking", 'region' => 'Kenya & Africa', 'driver' => Drivers\AfricasTalking::class,
            'description' => 'Direct carrier routes in Kenya (Safaricom, Airtel, Telkom), Uganda, Tanzania, Rwanda, Nigeria and more. Supports two-way SMS and delivery reports.',
            'fields' => [
                'username' => ['label' => 'Username', 'rules' => 'required|string', 'help' => 'Your app username (use "sandbox" to test with the sandbox).'],
                'api_key' => ['label' => 'API key', 'rules' => 'required|string', 'secret' => true],
            ],
            'webhooks' => 'In your Africa\'s Talking dashboard: SMS → Callback URLs. Paste the delivery report URL and the incoming messages URL.',
        ],
        'twilio' => [
            'label' => 'Twilio', 'region' => 'Global', 'driver' => Drivers\Twilio::class,
            'description' => 'Worldwide coverage, alphanumeric sender IDs and two-way SMS on Twilio numbers.',
            'fields' => [
                'account_sid' => ['label' => 'Account SID', 'rules' => 'required|string'],
                'auth_token' => ['label' => 'Auth token', 'rules' => 'required|string', 'secret' => true],
                'messaging_service_sid' => ['label' => 'Messaging Service SID (optional)', 'rules' => 'nullable|string', 'help' => 'Use instead of a sender ID to send from a pool of numbers.'],
            ],
            'webhooks' => 'Delivery reports are requested automatically. For replies, set your number\'s "A message comes in" webhook to the incoming messages URL.',
        ],
        'infobip' => [
            'label' => 'Infobip', 'region' => 'Global & Africa', 'driver' => Drivers\Infobip::class,
            'description' => 'Enterprise SMS with direct connections to carriers across Africa, Europe and Asia.',
            'fields' => [
                'base_url' => ['label' => 'Base URL', 'rules' => 'required|string', 'help' => 'Shown on your Infobip dashboard, e.g. xyz123.api.infobip.com'],
                'api_key' => ['label' => 'API key', 'rules' => 'required|string', 'secret' => true],
            ],
            'webhooks' => 'Delivery reports are requested automatically. For replies, forward incoming messages for your number to the incoming messages URL.',
        ],
        'vonage' => [
            'label' => 'Vonage', 'region' => 'Global', 'driver' => Drivers\Vonage::class,
            'description' => 'Vonage (formerly Nexmo) SMS API with global reach.',
            'fields' => [
                'api_key' => ['label' => 'API key', 'rules' => 'required|string'],
                'api_secret' => ['label' => 'API secret', 'rules' => 'required|string', 'secret' => true],
            ],
            'webhooks' => 'Delivery reports are requested automatically. For replies, set your number\'s inbound webhook to the incoming messages URL.',
        ],
        'http' => [
            'label' => 'Custom HTTP API', 'region' => 'Any provider', 'driver' => Drivers\GenericHttp::class,
            'description' => 'Connect any local aggregator — Celcom Africa, Advanta, Onfon Media, Bulk SMS Kenya and others — by describing its HTTP request.',
            'fields' => [
                'url' => ['label' => 'Endpoint URL', 'rules' => 'required|url'],
                'method' => ['label' => 'Request format', 'rules' => 'required|in:post_form,post_json,get', 'options' => ['post_form' => 'POST (form fields)', 'post_json' => 'POST (JSON body)', 'get' => 'GET (query string)']],
                'params' => ['label' => 'Parameters', 'rules' => 'required|string', 'textarea' => true, 'secret' => true, 'help' => 'One key=value per line. Placeholders: {to} (+2547…), {to_plain} (2547…), {to_local} (07…), {message}, {sender}.'],
                'headers' => ['label' => 'Headers (optional)', 'rules' => 'nullable|string', 'textarea' => true, 'secret' => true, 'help' => 'One Name=value per line, e.g. Authorization=Bearer abc123'],
                'success_contains' => ['label' => 'Success text (optional)', 'rules' => 'nullable|string', 'help' => 'If set, the response must contain this text to count as sent (e.g. "success").'],
                'message_id_path' => ['label' => 'Message ID path (optional)', 'rules' => 'nullable|string', 'help' => 'Where the message ID is in a JSON response, e.g. responses.0.messageid'],
            ],
            'webhooks' => 'If your provider can post delivery reports or incoming messages, give it these URLs. Common parameter names (id/messageId, status, from/msisdn, text/message) are understood.',
        ],
        'log' => [
            'label' => 'Test mode', 'region' => 'No sending', 'driver' => Drivers\LogDriver::class,
            'description' => 'Writes messages to the application log instead of sending. Use it to try campaigns and automations for free.',
            'fields' => [],
            'webhooks' => null,
        ],
    ];

    /** Starting points for the Custom HTTP API type — check the values against your provider's documentation. */
    public const HTTP_PRESETS = [
        'celcom' => ['label' => 'Celcom Africa (Kenya)', 'url' => 'https://isms.celcomafrica.com/api/services/sendsms/', 'method' => 'post_json',
            'params' => "apikey=YOUR_API_KEY\npartnerID=YOUR_PARTNER_ID\nshortcode={sender}\nmobile={to_plain}\nmessage={message}", 'success_contains' => '', 'message_id_path' => 'responses.0.messageid'],
        'advanta' => ['label' => 'Advanta SMS (Kenya)', 'url' => 'https://quicksms.advantasms.com/api/services/sendsms/', 'method' => 'post_json',
            'params' => "apikey=YOUR_API_KEY\npartnerID=YOUR_PARTNER_ID\nshortcode={sender}\nmobile={to_plain}\nmessage={message}", 'success_contains' => '', 'message_id_path' => 'responses.0.messageid'],
    ];

    public static function driver(string $type): Drivers\SmsDriver
    {
        return app(self::TYPES[$type]['driver'] ?? throw new \InvalidArgumentException("Unknown SMS provider type [{$type}]"));
    }
}

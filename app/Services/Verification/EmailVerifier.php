<?php

namespace App\Services\Verification;

use App\Models\VerificationServer;
use App\Services\Dns\DnsResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Classifies an address as deliverable, risky, undeliverable or unknown using the
 * configured provider, or the built-in checks when no provider is set up.
 */
class EmailVerifier
{
    public const ROLE_PREFIXES = ['admin', 'administrator', 'abuse', 'billing', 'contact', 'help', 'hostmaster', 'info', 'mail', 'marketing',
        'no-reply', 'noreply', 'office', 'postmaster', 'privacy', 'root', 'sales', 'security', 'support', 'team', 'webmaster'];

    public const DISPOSABLE_DOMAINS = ['10minutemail.com', 'discard.email', 'dispostable.com', 'emailondeck.com', 'fakeinbox.com', 'getairmail.com',
        'getnada.com', 'guerrillamail.com', 'guerrillamail.net', 'mailcatch.com', 'maildrop.cc', 'mailinator.com', 'mailnesia.com', 'mintemail.com',
        'moakt.com', 'mohmal.com', 'mytemp.email', 'sharklasers.com', 'spamgourmet.com', 'temp-mail.org', 'tempmail.com', 'tempmailo.com',
        'tempr.email', 'throwawaymail.com', 'trashmail.com', 'yopmail.com'];

    public function __construct(protected DnsResolver $dns) {}

    /** @return array{0: string, 1: ?string} status and reason */
    public function verify(string $email, VerificationServer $server): array
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['undeliverable', 'Invalid syntax'];
        }

        try {
            return match ($server->provider) {
                'emailable' => $this->emailable($email, (string) $server->api_key),
                'kickbox' => $this->kickbox($email, (string) $server->api_key),
                'zerobounce' => $this->zerobounce($email, (string) $server->api_key),
                default => $this->builtin($email),
            };
        } catch (ConnectionException $e) {
            return ['unknown', 'Provider unreachable'];
        }
    }

    /** @return array{0: string, 1: ?string} */
    public function builtin(string $email): array
    {
        [$local, $domain] = explode('@', $email, 2);

        if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return ['undeliverable', 'Disposable email domain'];
        }
        if (! $this->dns->mx($domain)) {
            return ['undeliverable', 'Domain has no mail server (MX)'];
        }
        if (in_array($local, self::ROLE_PREFIXES, true)) {
            return ['risky', 'Role-based address'];
        }

        return ['deliverable', null];
    }

    /** @return array{0: string, 1: ?string} */
    protected function emailable(string $email, string $key): array
    {
        $r = Http::timeout(30)->get('https://api.emailable.com/v1/verify', ['email' => $email, 'api_key' => $key]);
        if ($r->failed()) {
            return ['unknown', 'Emailable error '.$r->status()];
        }

        return [$this->normalise($r->json('state')), $r->json('reason')];
    }

    /** @return array{0: string, 1: ?string} */
    protected function kickbox(string $email, string $key): array
    {
        $r = Http::timeout(30)->get('https://api.kickbox.com/v2/verify', ['email' => $email, 'apikey' => $key]);
        if ($r->failed() || $r->json('success') === false) {
            return ['unknown', 'Kickbox error '.($r->json('message') ?? $r->status())];
        }

        return [$this->normalise($r->json('result')), $r->json('reason')];
    }

    /** @return array{0: string, 1: ?string} */
    protected function zerobounce(string $email, string $key): array
    {
        $r = Http::timeout(30)->get('https://api.zerobounce.net/v2/validate', ['api_key' => $key, 'email' => $email]);
        if ($r->failed() || $r->json('error')) {
            return ['unknown', 'ZeroBounce error '.($r->json('error') ?? $r->status())];
        }

        $status = match ($r->json('status')) {
            'valid' => 'deliverable',
            'invalid', 'spamtrap', 'abuse', 'do_not_mail' => 'undeliverable',
            'catch-all' => 'risky',
            default => 'unknown',
        };

        return [$status, $r->json('sub_status') ?: $r->json('status')];
    }

    protected function normalise(?string $state): string
    {
        return in_array($state, ['deliverable', 'risky', 'undeliverable', 'unknown'], true) ? $state : 'unknown';
    }
}

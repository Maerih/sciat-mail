<?php

namespace App\Sending;

use App\Models\SendingServer;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Builds a Symfony transport for a sending server. Transports are cached per server for
 * the lifetime of the worker process so SMTP connections are reused across a batch.
 */
class TransportFactory
{
    /** @var array<int, TransportInterface> */
    protected array $cache = [];

    public function for(SendingServer $server): TransportInterface
    {
        $key = $server->id.':'.$server->updated_at?->timestamp;

        return $this->cache[$key] ??= $this->make($server);
    }

    public function make(SendingServer $server): TransportInterface
    {
        $dsn = $this->dsn($server);

        $factory = match (true) {
            str_starts_with($dsn->getScheme(), 'ses') => new SesTransportFactory,
            str_starts_with($dsn->getScheme(), 'sendgrid') => new SendgridTransportFactory,
            str_starts_with($dsn->getScheme(), 'mailgun') => new MailgunTransportFactory,
            default => new EsmtpTransportFactory,
        };

        return $factory->create($dsn);
    }

    public function dsn(SendingServer $server): Dsn
    {
        $c = $server->config ?? [];

        return match ($server->type) {
            'smtp' => new Dsn(
                ($c['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : 'smtp',
                $c['host'],
                ($c['username'] ?? '') ?: null,
                ($c['password'] ?? '') ?: null,
                (int) ($c['port'] ?? 587),
                // 'none' disables opportunistic STARTTLS for plain relays
                ($c['encryption'] ?? 'tls') === 'none' ? ['auto_tls' => 'false'] : []
            ),
            'ses-api' => new Dsn('ses+api', 'default', $c['key'], $c['secret'], null,
                array_filter(['region' => $c['region'], 'configuration_set' => $c['configuration_set'] ?? null])),
            'ses-smtp' => new Dsn('ses+smtp', 'default', $c['username'], $c['password'], null, ['region' => $c['region']]),
            'sendgrid-api' => new Dsn('sendgrid+api', 'default', $c['api_key']),
            'sendgrid-smtp' => new Dsn('sendgrid+smtp', 'default', $c['api_key']),
            'mailgun-api' => new Dsn('mailgun+api', 'default', $c['api_key'], $c['domain'], null, ['region' => $c['region'] ?? 'us']),
            'mailgun-smtp' => new Dsn('mailgun+smtp', 'default', $c['username'], $c['password'], null, ['region' => $c['region'] ?? 'us']),
            default => throw new \InvalidArgumentException("Unsupported sending server type [{$server->type}]"),
        };
    }

    public function forget(SendingServer $server): void
    {
        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $server->id.':')) {
                unset($this->cache[$key]);
            }
        }
    }
}

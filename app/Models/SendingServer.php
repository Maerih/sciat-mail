<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasUid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SendingServer extends Model
{
    use BelongsToWorkspace, HasFactory, HasUid;

    protected $fillable = [
        'workspace_id', 'name', 'type', 'config', 'status', 'hourly_limit', 'daily_limit', 'default_from_email',
        'warmup_strategy_id', 'warmup_started_on', 'bounce_address', 'prewarmed', 'email_warmup',
    ];

    protected $hidden = ['config', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'config' => 'encrypted:array',
            'last_tested_at' => 'datetime',
            'warmup_started_on' => 'date',
            'prewarmed' => 'boolean',
            'email_warmup' => 'array',
        ];
    }

    /**
     * Driver catalogue. 'fields' drive the create/edit form; 'secret' fields are never
     * echoed back into the form. 'webhook' names the bounce/complaint webhook handler.
     */
    public const TYPES = [
        'smtp' => [
            'label' => 'SMTP',
            'description' => 'Any standard SMTP relay (Postfix, provider SMTP, etc.)',
            'webhook' => null,
            'fields' => [
                'host' => ['label' => 'SMTP host', 'rules' => 'required|string'],
                'port' => ['label' => 'SMTP port', 'rules' => 'required|integer', 'default' => 587],
                'encryption' => ['label' => 'Encryption', 'rules' => 'nullable|in:tls,ssl,none', 'options' => ['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'None']],
                'username' => ['label' => 'Username', 'rules' => 'nullable|string'],
                'password' => ['label' => 'Password', 'rules' => 'nullable|string', 'secret' => true],
            ],
        ],
        'ses-api' => [
            'label' => 'Amazon SES API',
            'description' => 'High-throughput delivery via the SES API',
            'webhook' => 'ses',
            'fields' => [
                'key' => ['label' => 'AWS access key ID', 'rules' => 'required|string'],
                'secret' => ['label' => 'AWS secret access key', 'rules' => 'required|string', 'secret' => true],
                'region' => ['label' => 'AWS region', 'rules' => 'required|string', 'options' => self::AWS_REGIONS],
                'configuration_set' => ['label' => 'Configuration set (optional)', 'rules' => 'nullable|string'],
            ],
        ],
        'ses-smtp' => [
            'label' => 'Amazon SES SMTP',
            'description' => 'Amazon SES over SMTP',
            'webhook' => 'ses',
            'fields' => [
                'region' => ['label' => 'AWS region', 'rules' => 'required|string', 'options' => self::AWS_REGIONS],
                'username' => ['label' => 'SMTP username', 'rules' => 'required|string'],
                'password' => ['label' => 'SMTP password', 'rules' => 'required|string', 'secret' => true],
            ],
        ],
        'sendgrid-api' => [
            'label' => 'SendGrid API',
            'description' => 'SendGrid Web API v3',
            'webhook' => 'sendgrid',
            'fields' => [
                'api_key' => ['label' => 'API key', 'rules' => 'required|string', 'secret' => true],
            ],
        ],
        'sendgrid-smtp' => [
            'label' => 'SendGrid SMTP',
            'description' => 'SendGrid over SMTP',
            'webhook' => 'sendgrid',
            'fields' => [
                'api_key' => ['label' => 'API key (used as SMTP password)', 'rules' => 'required|string', 'secret' => true],
            ],
        ],
        'mailgun-api' => [
            'label' => 'Mailgun API',
            'description' => 'Mailgun HTTP API',
            'webhook' => 'mailgun',
            'fields' => [
                'api_key' => ['label' => 'API key', 'rules' => 'required|string', 'secret' => true],
                'domain' => ['label' => 'Sending domain', 'rules' => 'required|string'],
                'region' => ['label' => 'Region', 'rules' => 'required|in:us,eu', 'options' => ['us' => 'US / Global', 'eu' => 'EU']],
                'webhook_signing_key' => ['label' => 'Webhook signing key', 'rules' => 'nullable|string', 'secret' => true],
            ],
        ],
        'mailgun-smtp' => [
            'label' => 'Mailgun SMTP',
            'description' => 'Mailgun over SMTP',
            'webhook' => 'mailgun',
            'fields' => [
                'username' => ['label' => 'SMTP username', 'rules' => 'required|string'],
                'password' => ['label' => 'SMTP password', 'rules' => 'required|string', 'secret' => true],
                'region' => ['label' => 'Region', 'rules' => 'required|in:us,eu', 'options' => ['us' => 'US / Global', 'eu' => 'EU']],
                'webhook_signing_key' => ['label' => 'Webhook signing key', 'rules' => 'nullable|string', 'secret' => true],
            ],
        ],
        'postmark' => [
            'label' => 'Postmark', 'description' => 'Fast, reliable delivery via Postmark SMTP', 'webhook' => null,
            'smtp' => ['host' => 'smtp.postmarkapp.com', 'port' => 587, 'username' => 'server_token', 'password' => 'server_token'], 'spf' => 'spf.mtasv.net',
            'fields' => ['server_token' => ['label' => 'Server API token', 'rules' => 'required|string', 'secret' => true]],
        ],
        'sparkpost' => [
            'label' => 'SparkPost', 'description' => 'Enterprise delivery via SparkPost SMTP', 'webhook' => null,
            'smtp' => ['host' => 'smtp.sparkpostmail.com', 'port' => 587, 'username' => '=SMTP_Injection', 'password' => 'api_key'], 'spf' => '_spf.sparkpostmail.com',
            'fields' => [
                'api_key' => ['label' => 'API key (with Send via SMTP permission)', 'rules' => 'required|string', 'secret' => true],
                'region' => ['label' => 'Region', 'rules' => 'required|in:us,eu', 'options' => ['us' => 'US', 'eu' => 'EU']],
            ],
        ],
        'elasticemail' => [
            'label' => 'Elastic Email', 'description' => 'Affordable pay-as-you-go delivery via Elastic Email SMTP', 'webhook' => null,
            'smtp' => ['host' => 'smtp.elasticemail.com', 'port' => 2525, 'username' => 'username', 'password' => 'password'], 'spf' => '_spf.elasticemail.com',
            'fields' => [
                'username' => ['label' => 'SMTP username (account email)', 'rules' => 'required|string'],
                'password' => ['label' => 'SMTP password', 'rules' => 'required|string', 'secret' => true],
            ],
        ],
        'brevo' => [
            'label' => 'Brevo (Sendinblue)', 'description' => 'Delivery via Brevo SMTP relay', 'webhook' => null,
            'smtp' => ['host' => 'smtp-relay.brevo.com', 'port' => 587, 'username' => 'username', 'password' => 'password'], 'spf' => 'spf.brevo.com',
            'fields' => [
                'username' => ['label' => 'SMTP login', 'rules' => 'required|string'],
                'password' => ['label' => 'SMTP key', 'rules' => 'required|string', 'secret' => true],
            ],
        ],
        'mailjet' => [
            'label' => 'Mailjet', 'description' => 'Delivery via Mailjet SMTP relay', 'webhook' => null,
            'smtp' => ['host' => 'in-v3.mailjet.com', 'port' => 587, 'username' => 'api_key', 'password' => 'secret_key'], 'spf' => 'spf.mailjet.com',
            'fields' => [
                'api_key' => ['label' => 'API key', 'rules' => 'required|string'],
                'secret_key' => ['label' => 'Secret key', 'rules' => 'required|string', 'secret' => true],
            ],
        ],
        'mailersend' => [
            'label' => 'MailerSend', 'description' => 'Delivery via MailerSend SMTP', 'webhook' => null,
            'smtp' => ['host' => 'smtp.mailersend.net', 'port' => 587, 'username' => 'username', 'password' => 'password'], 'spf' => '_spf.mailersend.net',
            'fields' => [
                'username' => ['label' => 'SMTP username', 'rules' => 'required|string'],
                'password' => ['label' => 'SMTP password', 'rules' => 'required|string', 'secret' => true],
            ],
        ],
        'zeptomail' => [
            'label' => 'ZeptoMail (Zoho)', 'description' => 'Delivery via Zoho ZeptoMail SMTP', 'webhook' => null,
            'smtp' => ['host' => 'smtp.zeptomail.com', 'port' => 587, 'username' => '=emailapikey', 'password' => 'token'], 'spf' => null,
            'fields' => ['token' => ['label' => 'Send mail token', 'rules' => 'required|string', 'secret' => true]],
        ],
    ];

    public const AWS_REGIONS = [
        'us-east-1' => 'US East (N. Virginia)', 'us-east-2' => 'US East (Ohio)', 'us-west-1' => 'US West (N. California)',
        'us-west-2' => 'US West (Oregon)', 'af-south-1' => 'Africa (Cape Town)', 'ap-south-1' => 'Asia Pacific (Mumbai)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)', 'ap-southeast-2' => 'Asia Pacific (Sydney)',
        'ap-northeast-1' => 'Asia Pacific (Tokyo)', 'ap-northeast-2' => 'Asia Pacific (Seoul)', 'ca-central-1' => 'Canada (Central)',
        'eu-central-1' => 'Europe (Frankfurt)', 'eu-west-1' => 'Europe (Ireland)', 'eu-west-2' => 'Europe (London)',
        'eu-west-3' => 'Europe (Paris)', 'eu-south-1' => 'Europe (Milan)', 'eu-north-1' => 'Europe (Stockholm)',
        'me-south-1' => 'Middle East (Bahrain)', 'sa-east-1' => 'South America (São Paulo)',
    ];

    protected static function booted(): void
    {
        static::creating(function (SendingServer $server) {
            $server->webhook_secret ??= Str::random(40);
        });
    }

    /** Shared servers (workspace_id NULL) are visible from every workspace. */
    public function resolveRouteBinding($value, $field = null)
    {
        $workspace = Workspace::current();

        return $this->where($field ?? 'uid', $value)
            ->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $workspace?->id))
            ->firstOrFail();
    }

    public function warmupStrategy(): BelongsTo
    {
        return $this->belongsTo(WarmupStrategy::class);
    }

    /** Current warmup day (1-based) in the server's workspace timezone, or null when not warming up. */
    public function warmupDay(): ?int
    {
        if (! $this->warmup_strategy_id || ! $this->warmup_started_on) {
            return null;
        }
        $today = now($this->workspace?->timezone ?? 'UTC')->startOfDay();

        return (int) $this->warmup_started_on->copy()->startOfDay()->diffInDays($today, false) + 1;
    }

    public function isWarmingUp(): bool
    {
        if ($this->prewarmed) {
            return false; // a pre-warmed server already has its reputation; no ramp needed
        }
        $day = $this->warmupDay();

        return $day !== null && $day >= 1 && $day < $this->warmupStrategy->days;
    }

    /** Today's send allowance: the stricter of the daily limit and the warmup quota. */
    public function effectiveDailyLimit(): ?int
    {
        $limits = array_filter([
            $this->daily_limit,
            $this->isWarmingUp() ? $this->warmupStrategy->quotaForDay($this->warmupDay()) : null,
        ], fn ($v) => $v !== null);

        return $limits ? min($limits) : null;
    }

    public function emailWarmup(string $key, $default = null)
    {
        return ($this->email_warmup ?? [])[$key] ?? $default;
    }

    public function emailWarmupDay(): ?int
    {
        $start = $this->emailWarmup('started_on');

        return $this->emailWarmup('enabled') && $start ? (int) Carbon::parse($start)->startOfDay()->diffInDays(now()->startOfDay()) + 1 : null;
    }

    /** Warmup emails to send today: starts at 4 and grows by 3 a day up to the chosen maximum. */
    public function emailWarmupTarget(): int
    {
        $day = $this->emailWarmupDay();

        return $day ? min((int) $this->emailWarmup('daily_max', 40), 1 + 3 * $day) : 0;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isShared(): bool
    {
        return $this->workspace_id === null;
    }

    /** SMTP-based transports are signed by us; API providers sign with their own verified domain keys. */
    public function usesSmtp(): bool
    {
        return in_array($this->type, ['smtp', 'ses-smtp', 'sendgrid-smtp', 'mailgun-smtp'], true) || isset(self::TYPES[$this->type]['smtp']);
    }

    public function webhookProvider(): ?string
    {
        return self::TYPES[$this->type]['webhook'] ?? null;
    }

    public function webhookUrl(): ?string
    {
        $provider = $this->webhookProvider();

        return $provider ? route('webhooks.'.$provider, ['server' => $this->uid, 'secret' => $this->webhook_secret]) : null;
    }

    public function configValue(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /** SPF include mechanism the provider needs, shown on the domain verification page. */
    public function spfInclude(): ?string
    {
        return match (true) {
            str_starts_with($this->type, 'ses') => 'amazonses.com',
            str_starts_with($this->type, 'sendgrid') => 'sendgrid.net',
            str_starts_with($this->type, 'mailgun') => 'mailgun.org',
            default => self::TYPES[$this->type]['spf'] ?? null,
        };
    }
}

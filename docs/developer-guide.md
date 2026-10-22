# Developer guide

## Stack
Laravel 13 · PHP 8.4+ · MySQL/MariaDB · Blade + Tailwind CSS v4 + Alpine.js · Chart.js ·
GrapesJS (email builder) · Symfony Mailer transports · webklex/php-imap (bounce and warmup
mailboxes) · anthropic-ai/sdk (AI). Queue: database (shared hosting) or Redis (VPS).

Project rules (CLAUDE.md / Laravel Boost): follow existing conventions, ask before adding
dependencies or new top-level folders, run `vendor/bin/pint` before committing, cover changes with
PHPUnit feature tests.

## Code map
| Path | Contents |
|---|---|
| `app/Models` | Workspace (client), MailList, ListField, Subscriber, Segment, Campaign (+Variant, Audience), Delivery, DeliveryEvent, Automation (+Run), Template, Form, Website (+Visit), SendingServer, SendingDomain, TrackingDomain, WarmupStrategy, WarmupMailbox/WarmupEmail, BounceHandler, VerificationServer, Blacklist, Signature, SmsProvider/SmsCampaign/SmsMessage, ApiToken, Webhook, Setting |
| `app/Models/Concerns` | `BelongsToWorkspace` (route binding scoped to the current client), `HasUid` |
| `app/Sending` | MessageBuilder, MergeTags, TrackingUrls, Dispatcher, TransportFactory (SMTP/SES/SendGrid/Mailgun/presets), ServerPicker, Throttle (server + domain counters), Spintax, HtmlToText |
| `app/Sms` | SmsManager (provider catalogue), drivers (AfricasTalking, Twilio, Infobip, Vonage, GenericHttp, LogDriver), SmsService, PhoneNumber, SmsText |
| `app/Services` | CampaignService (pre-flight, launch), Automation\Engine + Flow, SubscriptionService, Quota, SendingHealth, ContentReview, Warmup\EmailWarmup, DatabaseBackup, Captcha, SystemMailConfig, Requirements, EnvWriter, Webhooks, Verification, Bounces, Dns, Ai |
| `app/Jobs` | LaunchCampaign, SendCampaignBatch, FinalizeCampaign, PickAbWinner, SendAutomationEmail, ProcessAutomationRun, TriggerAutomations, ProcessImport, VerifySubscribers, SendListEmail, DeliverWebhook, LaunchSmsCampaign, SendSmsBatch, WorkerHeartbeat |
| `app/Http/Controllers` | Team UI; `Public/` (tracking, unsubscribe, forms, site script, provider and SMS webhooks); `Api/V1/` |
| `resources/views` | Blade views; `components/` (layouts, page-header, hero, banner, pill-tabs, wizard-bar, stat, field, toggle, icon…) |
| `resources/css/app.css` | Design system: theme tokens, dark theme remaps the Tailwind palette |
| `routes/web.php`, `routes/api.php`, `routes/console.php` | UI, API v1, schedule |

## Request flow
- `SetCurrentWorkspace` middleware resolves the client; models using `BelongsToWorkspace` 404 for
  other clients. Client-portal users are pinned to their client.
- `RedirectToInstaller` (while `APP_INSTALLER=true`), `CheckSiteOnline` (offline mode).

## Sending pipeline
1. `CampaignService::launch` snapshots recipients into `deliveries` (deduped, suppressed removed).
2. `LaunchCampaign` dispatches `SendCampaignBatch` jobs; each picks a server (`ServerPicker`,
   weighted, throttle/warmup aware), respects domain warmup, quotas and sending windows, builds the
   message (`MessageBuilder`: merge tags, tracking, List-Unsubscribe, DKIM) and sends it.
3. Opens/clicks hit `Public\TrackingController`; bounces/complaints arrive by provider webhooks or
   `bounces:fetch` (IMAP) and go through `EventRecorder` → suppression + webhooks.
4. `FinalizeCampaign` closes the campaign; A/B tests run `PickAbWinner`.

SMS: `SmsService::launch` creates `sms_messages` rows → `SendSmsBatch` → driver → status updates
via `/webhooks/sms/{provider}/{secret}/{status|inbound}`.

## Schedule (`routes/console.php`)
| Every | Task |
|---|---|
| minute | `campaigns:dispatch-scheduled` (email + SMS), `automations:tick`, heartbeats, cron queue worker (when `QUEUE_CRON_WORKER=true`) |
| 5 min | `bounces:fetch` |
| 15 min | `health:check`, `warmup:check` |
| hour | `warmup:send` |
| daily | `queue:prune-failed`, `app:backup` (02:30) |

## Configuration (`.env`)
| Key | Meaning |
|---|---|
| `APP_INSTALLER` | `true` = every page leads to the web installer |
| `QUEUE_CONNECTION` | `database` (shared hosting) or `redis` |
| `QUEUE_CRON_WORKER` | `true` on shared hosting: the scheduler works the queue |
| `CACHE_STORE`, `SESSION_DRIVER` | `file` on shared hosting, `redis` on a VPS |
| `ADMIN_NAME/EMAIL/PASSWORD` | Used by `db:seed` to create the first admin (development/VPS) |
Most other settings live in the database (`settings` table) and are edited in **Settings**.

## Commands
`app:package` · `app:backup` · `campaigns:dispatch-scheduled` · `automations:tick` ·
`bounces:fetch` · `health:check` · `warmup:send` · `warmup:check` · `events:backfill-devices`

## Tests
```bash
php artisan test --compact               # full suite (MariaDB test database mailplatform_test)
php artisan test --compact --filter=Sms  # one area
```
External services are faked (`Http::fake`, fake mail transport, fake IMAP inbox).

## Building releases
`npm run build && php artisan app:package` — one zip for new installs and updates (it ships `.env.install`, which `public/preflight.php` turns into `.env` only on a new install).
The package version is `git describe --tags`; tag releases (`git tag v1.0.0`).

<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Models\BounceHandler;
use App\Models\SendingDomain;
use App\Models\SendingServer;
use App\Models\Setting;
use App\Models\SmsProvider;
use App\Models\TrackingDomain;
use App\Models\VerificationServer;
use App\Models\Webhook;
use App\Models\Website;
use App\Models\Workspace;
use App\Services\Ai\AiAssistant;
use App\Services\Captcha;
use App\Sms\SmsManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** One page listing every integration, whether it is connected, and where to set it up. */
class IntegrationController extends Controller
{
    public function index(): View
    {
        $workspace = Workspace::current();
        $servers = $workspace->availableServers()->get();
        $sms = SmsProvider::availableTo($workspace)->get();
        $admin = request()->user()->isAdmin();
        $card = fn (string $name, string $text, int|bool $connected, string $url, string $icon = '', ?string $badge = null) => compact('name', 'text', 'connected', 'url', 'icon', 'badge');

        $sendingTypes = collect(SendingServer::TYPES)->groupBy(fn ($type, $key) => explode('-', $key)[0], true)
            ->map(fn ($group, $base) => $card(
                preg_replace('/ (API|SMTP)$/', '', $group->first()['label']),
                $group->first()['description'],
                $servers->filter(fn ($s) => explode('-', $s->type)[0] === $base)->count(),
                route('sending.servers.select'),
            ))->values();

        $groups = [
            'Sending services' => ['Route email through any SMTP service or sending API.', $sendingTypes->all()],
            'SMS' => ['Bulk SMS, two-way conversations and SMS in automations.', collect(SmsManager::TYPES)->except('log')->map(fn ($type, $key) => $card(
                $type['label'], $type['description'], $sms->where('type', $key)->count(), route('sms.providers.create', ['type' => $key]), '', $type['region'],
            ))->values()->all()],
            'Warmup & reputation' => ['Build trust with mailbox providers before sending at full volume.', [
                $card('IP warmup', 'Linear or exponential daily quotas for new servers and IPs.', $servers->filter->isWarmingUp()->count(), route('sending.warmup.index')),
                $card('Domain warmup', 'Cap daily sends from a new domain across all servers.', SendingDomain::forWorkspace()->whereNotNull('warmup_strategy_id')->count(), route('sending.domains.index')),
                $card('Email warmup service', 'Automated sends to seed inboxes with spam rescue and inbox-placement tracking.', $servers->filter(fn ($s) => $s->emailWarmup('enabled'))->count(), route('sending.email-warmup.index')),
                $card('Pre-warmed SMTP server', 'Mark a server with an existing reputation to skip warmup limits.', $servers->where('prewarmed', true)->count(), route('sending.servers.index')),
            ]],
            'Deliverability' => ['Authenticate your domains and keep your lists clean.', [
                $card('DKIM, SPF & DMARC', 'Generated DNS records with one-click verification for each sending domain.', SendingDomain::forWorkspace()->get()->filter->isVerified()->count(), route('sending.domains.index')),
                $card('Custom tracking domain', 'Open and click links on your own domain.', TrackingDomain::forWorkspace()->where('verified', true)->count(), route('sending.tracking-domains.index')),
                $card('Email verification', 'Built-in checks plus Emailable, Kickbox and ZeroBounce.', VerificationServer::forWorkspace()->count() ?: true, route('sending.verification.index'), '', 'Built-in included'),
                $card('Bounce & complaint handling', 'Webhooks from SES, SendGrid and Mailgun; IMAP bounce mailboxes for SMTP.', BounceHandler::forWorkspace()->count() + $servers->filter(fn ($s) => $s->webhookProvider())->count(), route('sending.bounce-handlers.index')),
            ]],
            'Websites & developers' => ['Collect subscribers and connect your own systems.', [
                $card('Websites', 'One script tag: popup and inline forms, visitor tracking.', Website::forWorkspace()->count(), route('websites.index')),
                $card('WordPress, Joomla, Drupal & any site', 'Paste the website script or a form embed code into your theme or a custom HTML block.', Website::forWorkspace()->count(), route('websites.index')),
                $card('REST API', 'Lists, subscribers, campaigns, automations, transactional email and SMS.', ApiToken::where('workspace_id', $workspace->id)->count(), route('api.settings')),
                $card('Webhooks & Zapier', 'Push events to your systems — or to Zapier/Make with “Webhooks by Zapier”.', Webhook::where('workspace_id', $workspace->id)->count(), route('api.settings')),
                $card('Google Analytics (UTM)', 'Append UTM parameters to every campaign link.', true, route('campaigns.index'), '', 'Per campaign'),
            ]],
        ];
        if ($admin) {
            $groups['Platform'] = ['Platform-wide services, managed by administrators.', [
                $card('AI assistant', 'Claude, OpenAI or a local Ollama model for subject lines and content.', AiAssistant::isConfigured(), route('platform.ai')),
                $card('Google reCAPTCHA / Cloudflare Turnstile', 'Protect signup forms from bots.', Captcha::enabled(), route('settings.show', 'general')),
                $card('System mail', 'SMTP for password resets and notifications.', filled(Setting::get('mail_driver')), route('settings.show', 'mail')),
                $card('Cron & queue', 'Scheduled sending, automations and background work.', (bool) Cache::get('heartbeat:scheduler'), route('settings.show', 'cron')),
            ]];
        }

        $all = collect($groups)->flatMap(fn ($g) => $g[1]);

        return view('integrations.index', ['groups' => $groups, 'connected' => $all->filter(fn ($c) => (bool) $c['connected'])->count(), 'total' => $all->count()]);
    }
}

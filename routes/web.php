<?php

use App\Http\Controllers;
use App\Http\Controllers\Public as Pub;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
| Public endpoints: tracking, unsubscribe, signup forms, provider webhooks.
| These are hit by mail clients, subscribers and ESPs — no session auth, no CSRF.
*/
Route::get('/t/o/{uid}.gif', [Pub\TrackingController::class, 'open'])->name('track.open');
Route::get('/t/c/{uid}/{sig}', [Pub\TrackingController::class, 'click'])->name('track.click');
Route::get('/w/{uid}/{sig}', [Pub\TrackingController::class, 'webView'])->name('track.webview');

Route::get('/u/s/{uid}/{sig}', [Pub\UnsubscribeController::class, 'showSubscriber'])->name('unsubscribe.subscriber');
Route::post('/u/s/{uid}/{sig}', [Pub\UnsubscribeController::class, 'confirmSubscriber']);
Route::get('/u/{uid}/{sig}', [Pub\UnsubscribeController::class, 'show'])->name('unsubscribe');
Route::post('/u/{uid}/{sig}', [Pub\UnsubscribeController::class, 'confirm']);

Route::get('/subscribe/confirm/{uid}/{sig}', [Pub\SubscribeController::class, 'confirm'])->name('subscribe.confirm');
Route::get('/subscribe/{form}', [Pub\SubscribeController::class, 'show'])->name('subscribe.show');
Route::get('/forms/{form}/popup.js', [Pub\SubscribeController::class, 'popupScript'])->name('subscribe.popup');
Route::post('/subscribe/{form}', [Pub\SubscribeController::class, 'store'])->middleware('throttle:20,1')->name('subscribe.store');

Route::get('/site/{uid}/connect.js', [Pub\SiteController::class, 'script'])->name('site.script');
Route::post('/site/{uid}/collect', [Pub\SiteController::class, 'collect'])->middleware('throttle:120,1')->name('site.collect');

// Hosts that forbid symlinks (no public/storage link): serve public uploads through the app
if (! file_exists(public_path('storage'))) {
    Route::get('/storage/{path}', function (string $path) {
        abort_if(str_contains($path, '..') || ! Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, ['Cache-Control' => 'public, max-age=604800']);
    })->where('path', '.*')->name('storage.fallback');
}

Route::post('/webhooks/ses/{server}/{secret}', [Pub\WebhookController::class, 'ses'])->name('webhooks.ses');
Route::post('/webhooks/sendgrid/{server}/{secret}', [Pub\WebhookController::class, 'sendgrid'])->name('webhooks.sendgrid');
Route::post('/webhooks/mailgun/{server}/{secret}', [Pub\WebhookController::class, 'mailgun'])->name('webhooks.mailgun');

/*
| Web installer (only while APP_INSTALLER=true)
*/
Route::prefix('install')->name('install.')->controller(Controllers\InstallController::class)->group(function () {
    Route::get('/', 'requirements')->name('requirements');
    Route::get('database', 'database')->name('database');
    Route::post('database', 'saveDatabase')->middleware('throttle:10,1')->name('database.save');
    Route::get('admin', 'admin')->name('admin');
    Route::post('admin', 'saveAdmin')->middleware('throttle:10,1')->name('admin.save');
});

/*
| Team authentication
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [Controllers\AuthController::class, 'show'])->name('login');
    Route::post('/login', [Controllers\AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [Controllers\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [Controllers\PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [Controllers\PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [Controllers\PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});
Route::post('/logout', [Controllers\AuthController::class, 'logout'])->middleware('auth')->name('logout');

/*
| Client portal (read-only, one client per user)
*/
Route::middleware(['auth', 'client'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [Controllers\PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/campaigns', [Controllers\PortalController::class, 'campaigns'])->name('campaigns');
    Route::get('/campaigns/{campaign}', [Controllers\PortalController::class, 'campaign'])->name('campaign');
    Route::get('/lists', [Controllers\PortalController::class, 'lists'])->name('lists');
    Route::get('/account', [Controllers\PortalController::class, 'account'])->name('account');
    Route::put('/account', [Controllers\ProfileController::class, 'update'])->name('account.update');
});

/*
| Application (team only)
*/
Route::middleware(['auth', 'team'])->group(function () {
    Route::get('/workspaces/create', [Controllers\WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('/workspaces', [Controllers\WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/{workspace}/switch', [Controllers\WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::get('/profile', [Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [Controllers\ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('admin')->group(function () {
        Route::get('/workspaces', [Controllers\WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::get('/settings', [Controllers\SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/general', [Controllers\SettingsController::class, 'updateGeneral'])->name('settings.general');
        Route::put('/settings/sending', [Controllers\SettingsController::class, 'updateSending'])->name('settings.sending');
        Route::put('/settings/mail', [Controllers\SettingsController::class, 'updateMail'])->name('settings.mail');
        Route::put('/settings/health', [Controllers\SettingsController::class, 'updateHealth'])->name('settings.health');
        Route::post('/settings/health/{workspace}/lift', [Controllers\SettingsController::class, 'liftSuspension'])->name('settings.health.lift');
        Route::post('/settings/mail/test', [Controllers\SettingsController::class, 'testMail'])->name('settings.mail.test');
        Route::post('/settings/cron', [Controllers\SettingsController::class, 'cron'])->name('settings.cron');
        Route::post('/settings/maintenance', [Controllers\SettingsController::class, 'maintenance'])->name('settings.maintenance');
        Route::get('/settings/backups/{name}', [Controllers\SettingsController::class, 'downloadBackup'])->name('settings.backups.download');
        Route::delete('/settings/backups/{name}', [Controllers\SettingsController::class, 'deleteBackup'])->name('settings.backups.delete');
        Route::get('/settings/{tab}', [Controllers\SettingsController::class, 'show'])->name('settings.show');
        Route::get('/platform/ai', [Controllers\AiController::class, 'settings'])->name('platform.ai');
        Route::put('/platform/ai', [Controllers\AiController::class, 'saveSettings'])->name('platform.ai.update');
        Route::get('/platform', [Controllers\PlatformSettingsController::class, 'edit'])->name('platform.edit');
        Route::put('/platform', [Controllers\PlatformSettingsController::class, 'update'])->name('platform.update');
        Route::resource('team', Controllers\TeamController::class)->only(['index', 'store', 'destroy'])
            ->parameters(['team' => 'user']);
    });

    Route::middleware('workspace')->group(function () {
        Route::get('/', [Controllers\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/workspace/settings', [Controllers\WorkspaceController::class, 'edit'])->name('workspaces.edit');
        Route::post('/workspace/portal-users', [Controllers\PortalUserController::class, 'store'])->name('portal-users.store');
        Route::delete('/workspace/portal-users/{user}', [Controllers\PortalUserController::class, 'destroy'])->name('portal-users.destroy');
        Route::put('/workspace/settings', [Controllers\WorkspaceController::class, 'update'])->name('workspaces.update');
        Route::delete('/workspace', [Controllers\WorkspaceController::class, 'destroy'])->middleware('admin')->name('workspaces.destroy');

        // AI assistant
        Route::middleware('throttle:30,1')->prefix('ai')->name('ai.')->group(function () {
            Route::post('subjects', [Controllers\AiController::class, 'subjects'])->name('subjects');
            Route::post('rewrite', [Controllers\AiController::class, 'rewrite'])->name('rewrite');
            Route::post('draft', [Controllers\AiController::class, 'draft'])->name('draft');
        });

        // API access & webhooks
        Route::get('/developers', [Controllers\ApiSettingsController::class, 'index'])->name('api.settings');
        Route::get('/developers/docs', [Controllers\ApiSettingsController::class, 'docs'])->name('api.docs');
        Route::post('/developers/tokens', [Controllers\ApiSettingsController::class, 'storeToken'])->name('api.tokens.store');
        Route::delete('/developers/tokens/{token}', [Controllers\ApiSettingsController::class, 'destroyToken'])->name('api.tokens.destroy');
        Route::post('/developers/webhooks', [Controllers\ApiSettingsController::class, 'storeWebhook'])->name('api.webhooks.store');
        Route::post('/developers/webhooks/{webhook}/toggle', [Controllers\ApiSettingsController::class, 'toggleWebhook'])->name('api.webhooks.toggle');
        Route::post('/developers/webhooks/{webhook}/test', [Controllers\ApiSettingsController::class, 'testWebhook'])->name('api.webhooks.test');
        Route::delete('/developers/webhooks/{webhook}', [Controllers\ApiSettingsController::class, 'destroyWebhook'])->name('api.webhooks.destroy');

        // Lists & subscribers
        Route::get('audience', [Controllers\AudienceController::class, 'overview'])->name('audience.overview');
        Route::get('audience/subscribers', [Controllers\AudienceController::class, 'subscribers'])->name('audience.subscribers');
        Route::resource('lists', Controllers\ListController::class)->except(['show']);
        Route::prefix('lists/{list}')->name('lists.')->scopeBindings()->group(function () {
            Route::get('/', [Controllers\ListController::class, 'show'])->name('show');
            Route::get('fields', [Controllers\ListFieldController::class, 'index'])->name('fields');
            Route::post('verify', [Controllers\VerificationServerController::class, 'verifyList'])->name('verify');
            Route::put('fields', [Controllers\ListFieldController::class, 'update'])->name('fields.update');

            Route::get('subscribers/export', [Controllers\SubscriberController::class, 'export'])->name('subscribers.export');
            Route::post('subscribers/bulk', [Controllers\SubscriberController::class, 'bulk'])->name('subscribers.bulk');
            Route::resource('subscribers', Controllers\SubscriberController::class)->except(['show']);
            Route::get('subscribers/{subscriber}', [Controllers\SubscriberController::class, 'show'])->name('subscribers.show');

            Route::get('import', [Controllers\ImportController::class, 'create'])->name('import.create');
            Route::post('import', [Controllers\ImportController::class, 'upload'])->name('import.upload');
            Route::get('import/{import}/map', [Controllers\ImportController::class, 'map'])->name('import.map');
            Route::post('import/{import}/map', [Controllers\ImportController::class, 'start'])->name('import.start');
            Route::get('import/{import}', [Controllers\ImportController::class, 'show'])->name('import.show');
            Route::get('import/{import}/status', [Controllers\ImportController::class, 'status'])->name('import.status');

            Route::post('segments/count', [Controllers\SegmentController::class, 'count'])->name('segments.count');
            Route::resource('segments', Controllers\SegmentController::class)->except(['show']);
        });

        Route::resource('forms', Controllers\FormController::class)->except(['show']);

        // Websites
        Route::resource('websites', Controllers\WebsiteController::class)->only(['index', 'create', 'store', 'show', 'update', 'destroy']);
        Route::post('websites/{website}/verify', [Controllers\WebsiteController::class, 'verify'])->name('websites.verify');
        Route::post('websites/{website}/forms', [Controllers\WebsiteController::class, 'connectForm'])->name('websites.forms.connect');
        Route::delete('websites/{website}/forms/{form}', [Controllers\WebsiteController::class, 'disconnectForm'])->name('websites.forms.disconnect');
        Route::get('forms/{form}/embed', [Controllers\FormController::class, 'embed'])->name('forms.embed');

        // Campaigns
        Route::get('campaigns', [Controllers\CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('campaigns/create', [Controllers\CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('campaigns', [Controllers\CampaignController::class, 'store'])->name('campaigns.store');
        Route::prefix('campaigns/{campaign}')->name('campaigns.')->group(function () {
            Route::get('/', [Controllers\CampaignController::class, 'show'])->name('show');
            foreach (['recipients', 'setup', 'content', 'variants', 'schedule'] as $step) {
                Route::get($step, [Controllers\CampaignWizardController::class, $step])->name($step);
                Route::post($step, [Controllers\CampaignWizardController::class, 'save'.ucfirst($step)])->name($step.'.save');
            }
            Route::post('content/template', [Controllers\CampaignWizardController::class, 'useTemplate'])->name('content.template');
            Route::get('preview', [Controllers\CampaignWizardController::class, 'preview'])->name('preview');
            Route::get('confirm', [Controllers\CampaignWizardController::class, 'confirm'])->name('confirm');
            Route::post('test', [Controllers\CampaignWizardController::class, 'sendTest'])->name('test');
            Route::post('launch', [Controllers\CampaignWizardController::class, 'launch'])->name('launch');

            Route::get('report/{log}', [Controllers\CampaignReportController::class, 'log'])
                ->whereIn('log', ['deliveries', 'opens', 'clicks', 'bounces', 'complaints', 'unsubscribes'])->name('log');
            Route::get('links', [Controllers\CampaignReportController::class, 'links'])->name('links');
            Route::get('map', [Controllers\CampaignReportController::class, 'map'])->name('map');
            Route::get('review', [Controllers\CampaignReportController::class, 'review'])->name('review');
            Route::get('report-chart', [Controllers\CampaignReportController::class, 'chart'])->name('chart');
            Route::post('winner', [Controllers\CampaignController::class, 'winner'])->name('winner');
            Route::post('pause', [Controllers\CampaignController::class, 'pause'])->name('pause');
            Route::post('resume', [Controllers\CampaignController::class, 'resume'])->name('resume');
            Route::post('cancel', [Controllers\CampaignController::class, 'cancel'])->name('cancel');
            Route::post('duplicate', [Controllers\CampaignController::class, 'duplicate'])->name('duplicate');
            Route::delete('/', [Controllers\CampaignController::class, 'destroy'])->name('destroy');
        });

        // Automations
        Route::resource('automations', Controllers\AutomationController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::prefix('automations/{automation}')->name('automations.')->group(function () {
            Route::get('settings', [Controllers\AutomationController::class, 'settings'])->name('settings');
            Route::put('settings', [Controllers\AutomationController::class, 'updateSettings'])->name('settings.update');
            Route::post('activate', [Controllers\AutomationController::class, 'activate'])->name('activate');
            Route::post('pause', [Controllers\AutomationController::class, 'pause'])->name('pause');
            Route::post('enroll', [Controllers\AutomationController::class, 'enrollExisting'])->name('enroll');
            Route::get('contacts', [Controllers\AutomationController::class, 'contacts'])->name('contacts');
            Route::post('steps', [Controllers\AutomationController::class, 'addStep'])->name('steps.store');
            Route::get('steps/{step}', [Controllers\AutomationController::class, 'editStep'])->name('step');
            Route::put('steps/{step}', [Controllers\AutomationController::class, 'updateStep'])->name('step.update');
            Route::delete('steps/{step}', [Controllers\AutomationController::class, 'deleteStep'])->name('step.destroy');
            Route::get('steps/{step}/email', [Controllers\AutomationController::class, 'email'])->name('email');
            Route::put('steps/{step}/email', [Controllers\AutomationController::class, 'updateEmail'])->name('email.update');
        });

        // Drag-and-drop builder
        Route::get('templates/{template}/builder', [Controllers\BuilderController::class, 'template'])->name('templates.builder');
        Route::put('templates/{template}/builder', [Controllers\BuilderController::class, 'saveTemplate'])->name('templates.builder.save');
        Route::get('campaigns/{campaign}/builder', [Controllers\BuilderController::class, 'campaign'])->name('campaigns.builder');
        Route::put('campaigns/{campaign}/builder', [Controllers\BuilderController::class, 'saveCampaign'])->name('campaigns.builder.save');
        Route::post('builder/upload', [Controllers\BuilderController::class, 'upload'])->name('builder.upload');

        Route::resource('templates', Controllers\TemplateController::class)->except(['show']);
        Route::get('assets', [Controllers\AssetController::class, 'index'])->name('assets.index');
        Route::post('assets', [Controllers\AssetController::class, 'store'])->name('assets.store');
        Route::delete('assets/{name}', [Controllers\AssetController::class, 'destroy'])->name('assets.destroy');
        Route::get('templates/{template}/preview', [Controllers\TemplateController::class, 'preview'])->name('templates.preview');

        // Sending infrastructure
        Route::prefix('sending')->name('sending.')->group(function () {
            Route::get('servers/select', [Controllers\SendingServerController::class, 'select'])->name('servers.select');
            Route::post('servers/{server}/test', [Controllers\SendingServerController::class, 'test'])->name('servers.test');
            Route::resource('servers', Controllers\SendingServerController::class)->except(['show']);

            Route::post('domains/{domain}/verify', [Controllers\SendingDomainController::class, 'verify'])->name('domains.verify');
            Route::resource('domains', Controllers\SendingDomainController::class)->only(['index', 'store', 'show', 'destroy']);

            Route::post('tracking-domains/{trackingDomain}/verify', [Controllers\TrackingDomainController::class, 'verify'])->name('tracking-domains.verify');
            Route::post('tracking-domains/{trackingDomain}/default', [Controllers\TrackingDomainController::class, 'makeDefault'])->name('tracking-domains.default');
            Route::resource('tracking-domains', Controllers\TrackingDomainController::class)->only(['index', 'store', 'destroy']);

            Route::post('signatures/{signature}/default', [Controllers\SignatureController::class, 'makeDefault'])->name('signatures.default');
            Route::resource('signatures', Controllers\SignatureController::class)->except(['show']);
            Route::resource('blacklist', Controllers\BlacklistController::class)->only(['index', 'store', 'destroy']);
            Route::resource('warmup', Controllers\WarmupStrategyController::class)->except(['show']);
            Route::post('bounce-handlers/{bounceHandler}/check', [Controllers\BounceHandlerController::class, 'check'])->name('bounce-handlers.check');
            Route::resource('bounce-handlers', Controllers\BounceHandlerController::class)->only(['index', 'store', 'destroy']);
            Route::post('verification/{verification}/default', [Controllers\VerificationServerController::class, 'makeDefault'])->name('verification.default');
            Route::resource('verification', Controllers\VerificationServerController::class)->only(['index', 'store', 'destroy']);
        });
    });
});

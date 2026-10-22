<?php

namespace App\Providers;

use App\Sending\Throttle;
use App\Sending\TrackingUrls;
use App\Sending\TransportFactory;
use App\Services\Ai\AiAssistant;
use App\Services\Bounces\ImapMailbox;
use App\Services\Bounces\Mailbox;
use App\Services\Dns\DnsResolver;
use App\Services\Dns\SystemDnsResolver;
use App\Services\SystemMailConfig;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
        $this->app->bind(Mailbox::class, ImapMailbox::class);
        // One per worker process so SMTP connections are reused across messages
        $this->app->singleton(TransportFactory::class);
        $this->app->singleton(Throttle::class);
        $this->app->singleton(TrackingUrls::class);
        $this->app->bind(AiAssistant::class, fn () => new AiAssistant);
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        try {
            SystemMailConfig::apply();
        } catch (\Throwable) {
            // settings table not migrated yet (fresh install)
        }

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->attributes->get('api_token')?->id ?: $request->ip()));
    }
}

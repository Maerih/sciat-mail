<?php

namespace App\Providers;

use App\Sending\Throttle;
use App\Sending\TrackingUrls;
use App\Sending\TransportFactory;
use App\Services\Dns\DnsResolver;
use App\Services\Dns\SystemDnsResolver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
        // One per worker process so SMTP connections are reused across messages
        $this->app->singleton(TransportFactory::class);
        $this->app->singleton(Throttle::class);
        $this->app->singleton(TrackingUrls::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');
    }
}

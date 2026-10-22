<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

/** Maintenance switch (Settings → General): signed-in non-admins see the offline page. */
class CheckSiteOnline
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->isAdmin() && ! Setting::bool('site_online', true) && ! $request->routeIs('logout')) {
            return response()->view('public.offline', [
                'message' => Setting::get('offline_message') ?: 'We are performing scheduled maintenance. Please check back soon.',
            ], 503);
        }

        return $next($request);
    }
}

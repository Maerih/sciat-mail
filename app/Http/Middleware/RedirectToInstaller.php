<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** While APP_INSTALLER=true, every page leads to the web installer; otherwise the installer does not exist. */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        $installing = $request->is('install', 'install/*');
        if (config('app.installer') && ! $installing) {
            return redirect('/install');
        }
        abort_if($installing && ! config('app.installer'), 404);

        return $next($request);
    }
}

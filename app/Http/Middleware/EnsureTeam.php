<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Client portal users may never reach team pages. */
class EnsureTeam
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->isClient()) {
            return $request->expectsJson() ? abort(403) : redirect()->route('portal.dashboard');
        }

        return $next($request);
    }
}

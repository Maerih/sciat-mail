<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/** Portal pages: only client users, always scoped to the one client they belong to. */
class EnsureClient
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user?->isClient()) {
            return redirect()->route('dashboard');
        }

        $workspace = $user->workspace ?? abort(403, 'This account is not linked to a client.');
        Workspace::setCurrent($workspace);
        View::share('portalWorkspace', $workspace);

        return $next($request);
    }
}

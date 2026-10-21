<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;

/**
 * Bearer-token authentication for /api/v1. The token decides which client (workspace)
 * every request acts on. Runs before route-model binding so bindings are scoped too.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::with('workspace')->where('token_hash', hash('sha256', $plain))->first() : null;

        if (! $token || ! $token->workspace) {
            return response()->json(['message' => 'Unauthenticated. Send a valid API token as "Authorization: Bearer <token>".'], 401);
        }

        Workspace::setCurrent($token->workspace);
        $request->attributes->set('api_token', $token);

        if (! $token->last_used_at || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}

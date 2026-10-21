<?php

use App\Http\Middleware\AuthenticateApiToken;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\SetCurrentWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API tokens must resolve the client before route-model binding runs
        $middleware->api(prepend: [AuthenticateApiToken::class]);
        $middleware->alias([
            'workspace' => SetCurrentWorkspace::class,
            'admin' => EnsureAdmin::class,
        ]);
        // Hit by subscribers' mail clients, embedded forms on client sites and ESP webhooks
        $middleware->preventRequestForgery(except: ['subscribe/*', 'webhooks/*', 'u/*']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

<?php

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
        // The web app calls /api with its session cookie; the mobile app uses bearer tokens.
        $middleware->statefulApi();

        // In production the app sits behind Cloudflare and/or the host's load balancer,
        // which end HTTPS and pass the real scheme and visitor IP in X-Forwarded-* headers.
        // Their addresses change, so any proxy is trusted; PHP must not be reachable directly.
        $middleware->trustProxies(at: '*');

        // Set by the browser (Settings > Appearance) and read when rendering <html>.
        $middleware->encryptCookies(except: ['theme']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

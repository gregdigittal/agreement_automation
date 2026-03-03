<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Force HTTPS detection before anything else — required so that
        // Livewire signed-URL validation ($request->url()) matches the
        // URL that was signed with URL::forceScheme('https').
        $middleware->prepend(\App\Http\Middleware\ForceHttpsScheme::class);

        // Trust all proxies (Cloudflare → K8s Ingress → nginx)
        // Required for correct $request->isSecure(), ->ip(), ->getHost()
        // Without this, Livewire AJAX fails due to http/https scheme mismatch
        $middleware->trustProxies(at: '*');

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->appendToGroup('web', [\App\Http\Middleware\AuditMiddleware::class]);
        $middleware->alias([
            'tito.auth' => \App\Http\Middleware\TitoApiKeyMiddleware::class,
            'feature' => \App\Http\Middleware\FeatureGate::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

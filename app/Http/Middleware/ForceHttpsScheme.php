<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force correct scheme, host, and port on requests behind Cloudflare.
 *
 * URL::forceScheme() only affects URL *generation*. Livewire's signed-URL
 * validation uses $request->url() which rebuilds the URL from the request.
 * Symfony's Request::isSecure() prioritises X-Forwarded-Proto from trusted
 * proxies over the HTTPS server variable, so simply setting HTTPS=on is
 * not enough — TrustProxies overrides it.
 *
 * This middleware injects the correct X-Forwarded-* headers BEFORE
 * TrustProxies runs, so that $request->url() returns the same URL
 * that URL::temporarySignedRoute() generated.
 */
class ForceHttpsScheme
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production', 'staging', 'sandbox')) {
            $host = parse_url(config('app.url'), PHP_URL_HOST) ?: $request->getHost();

            // Set X-Forwarded-* headers so TrustProxies reads them correctly
            $request->headers->set('X-Forwarded-Proto', 'https');
            $request->headers->set('X-Forwarded-Host', $host);
            $request->headers->set('X-Forwarded-Port', '443');

            // Belt-and-suspenders: also set server vars as fallback
            $request->server->set('HTTPS', 'on');
            $request->server->set('SERVER_PORT', 443);
        }

        return $next($request);
    }
}

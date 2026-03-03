<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force the request to be detected as HTTPS when behind Cloudflare.
 *
 * URL::forceScheme() only affects URL *generation*. Livewire's signed-URL
 * validation uses $request->url() which reads from the actual request
 * object.  If X-Forwarded-Proto is stripped or rewritten somewhere in the
 * Cloudflare → K8s Ingress → nginx → PHP-FPM chain, the reconstructed
 * URL won't match the signed URL and the upload gets a 401.
 *
 * This middleware runs before TrustProxies and hardcodes HTTPS + port 443
 * on the request, which is always correct because the sandbox and
 * production environments sit behind Cloudflare TLS termination.
 */
class ForceHttpsScheme
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production', 'staging', 'sandbox')) {
            $request->server->set('HTTPS', 'on');
            $request->server->set('SERVER_PORT', 443);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TitoApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-TiTo-API-Key');
        $configured = config('ccrs.tito_api_key');

        if (! $apiKey || $configured === '' || $configured === null || ! hash_equals((string) $configured, $apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

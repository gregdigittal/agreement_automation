<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!str_starts_with($request->path(), 'admin')) {
            return $response;
        }

        // Log GET requests only for individual record views (e.g., admin/contracts/{id})
        if ($request->isMethod('GET')) {
            $recordId = $request->route('record');
            if (!$recordId) {
                return $response;
            }
        }

        $path = $request->path();
        $parts = explode('/', $path);
        $resourceType = $parts[1] ?? 'unknown';
        $recordId = $request->route('record');

        AuditService::log(
            action: $request->method() . ':' . $path,
            resourceType: $resourceType,
            resourceId: is_object($recordId) ? ($recordId->getKey() ?? null) : $recordId,
            details: [],
            actor: auth()->user(),
        );

        return $response;
    }
}

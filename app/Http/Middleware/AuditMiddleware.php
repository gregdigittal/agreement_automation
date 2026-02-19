<?php
namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;

class AuditMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            AuditService::log(
                strtolower($request->method()) . '_' . $request->path(),
                'http_request',
                null,
                ['method' => $request->method(), 'path' => $request->path(), 'status' => $response->getStatusCode()]
            );
        }
        return $response;
    }
}

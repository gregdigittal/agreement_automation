<?php

namespace App\Http\Middleware;

use App\Helpers\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Feature::disabled('vendor_portal')) {
            abort(503, 'Vendor portal is temporarily unavailable.');
        }
        return $next($request);
    }
}

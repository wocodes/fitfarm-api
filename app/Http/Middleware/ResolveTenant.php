<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        // 1. Check HTTP header X-Tenant-UUID or X-Tenant-Slug
        if ($tenantUuid = $request->header('X-Tenant-UUID')) {
            $tenant = Tenant::where('uuid', $tenantUuid)->first();
        } elseif ($tenantSlug = $request->header('X-Tenant-Slug')) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
        }

        // 2. Check authenticated user's tenant if not resolved yet
        if (!$tenant && $user = $request->user()) {
            if ($user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
            }
        }

        if ($tenant) {
            // Bind resolved tenant instance into application container
            app()->instance('tenant', $tenant);
        }

        return $next($request);
    }
}

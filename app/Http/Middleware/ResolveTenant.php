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

        // 1. Check HTTP header X-Tenant-UUID, X-Tenant-Slug, or X-Tenant-ID
        if ($tenantUuid = $request->header('X-Tenant-UUID')) {
            $tenant = Tenant::where('uuid', $tenantUuid)->first();
        } elseif ($tenantSlug = $request->header('X-Tenant-Slug')) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();
        } elseif ($tenantId = $request->header('X-Tenant-ID') ?? $request->get('tenant_id')) {
            $tenant = Tenant::find($tenantId);
        }

        // 2. Check authenticated user's tenant if not resolved yet
        if (!$tenant && $user = $request->user()) {
            if ($user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
            }
        }

        if ($tenant) {
            // Bind resolved tenant instance into application container and request
            app()->instance('tenant', $tenant);
            $request->merge(['tenant_id' => $tenant->id]);
        }

        return $next($request);
    }
}

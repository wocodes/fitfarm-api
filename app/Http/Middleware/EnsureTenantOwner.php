<?php

namespace App\Http\Middleware;

use App\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantOwner
{
    /**
     * Ensure the authenticated principal owns the active gym tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof TenantUser || ! $user->isTenantOwner()) {
            return response()->json([
                'success' => false,
                'message' => 'Gym owner access is required for this action.',
            ], 403);
        }

        if (! app()->bound('tenant')) {
            return response()->json([
                'success' => false,
                'message' => 'Select a gym before performing this action.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Ensure the authenticated principal is a platform super admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof TenantUser || ! $user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Platform administrator access is required.',
            ], 403);
        }

        return $next($request);
    }
}

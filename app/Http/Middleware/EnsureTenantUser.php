<?php

namespace App\Http\Middleware;

use App\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantUser
{
    /**
     * Ensure the authenticated principal is a gym staff member or owner.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof TenantUser) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only available to gym staff and owners.',
            ], 403);
        }

        return $next($request);
    }
}

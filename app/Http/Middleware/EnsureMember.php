<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMember
{
    /**
     * Ensure the authenticated principal is a gym member.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof Member) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only available to gym members.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFeature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (!app()->bound('tenant') || !$tenant = app('tenant')) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context is missing for feature verification.',
            ], 400);
        }

        if (!$tenant->hasFeature($feature)) {
            return response()->json([
                'success' => false,
                'message' => "The feature '{$feature}' is not enabled for your gym subscription plan.",
                'feature' => $feature,
            ], 403);
        }

        return $next($request);
    }
}

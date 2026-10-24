<?php

namespace App\Http\Middleware;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\TenantUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Resolve the active tenant for the request.
     *
     * Authenticated staff and members are always pinned to their own tenant.
     * A client-supplied tenant header is only honoured for platform super admins
     * and unauthenticated public read endpoints.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = null;

        if ($user instanceof TenantUser) {
            if ($user->isSuperAdmin()) {
                $tenant = $this->resolveRequestedTenant($request);
            } else {
                $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;

                if (! $tenant) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is not attached to a gym yet.',
                    ], 403);
                }

                if (in_array($tenant->status, ['suspended', 'cancelled'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This gym account is currently suspended.',
                    ], 403);
                }
            }
        } elseif ($user instanceof Member) {
            $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;

            if (! $tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your member account is not attached to a gym.',
                ], 403);
            }

            if (in_array($tenant->status, ['suspended', 'cancelled'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This gym account is currently suspended.',
                ], 403);
            }
        } else {
            $tenant = $this->resolveRequestedTenant($request);
        }

        if ($tenant) {
            app()->instance('tenant', $tenant);
            $request->merge(['tenant_id' => $tenant->id]);
        }

        return $next($request);
    }

    /**
     * Resolve a tenant supplied by a slug or UUID.
     *
     * This is used for public read endpoints and explicit super-admin support
     * access. Numeric IDs and arbitrary request parameters are intentionally
     * not accepted.
     */
    private function resolveRequestedTenant(Request $request): ?Tenant
    {
        $slug = $request->header('X-Tenant-Slug') ?? $request->query('tenant');

        if (is_string($slug) && trim($slug) !== '') {
            return Tenant::where('slug', trim($slug))->first();
        }

        $uuid = $request->header('X-Tenant-UUID');

        if (is_string($uuid) && trim($uuid) !== '') {
            return Tenant::where('uuid', trim($uuid))->first();
        }

        return null;
    }
}

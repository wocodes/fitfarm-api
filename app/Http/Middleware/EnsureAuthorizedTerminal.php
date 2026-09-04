<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\Terminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthorizedTerminal
{
    /**
     * Handle an incoming request.
     * Enforces front-desk terminal binding for staff when enabled by the gym owner.
     * Owners and Super Admins bypass this restriction unconditionally.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Resolve current tenant
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        if (!$tenant && $user = $request->user()) {
            if ($user->tenant_id) {
                $tenant = Tenant::find($user->tenant_id);
            }
        }

        // If tenant does not enforce terminal lock, proceed normally
        if (!$tenant || !$tenant->enforce_terminal_lock) {
            return $next($request);
        }

        // 2. Owners and Super Admins have unrestricted remote access from any device
        $user = $request->user();
        if ($user) {
            $role = strtolower($user->role ?? '');
            if (in_array($role, ['owner', 'super_admin', 'superadmin'])) {
                return $next($request);
            }
            if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('bypass_terminal_lock')) {
                return $next($request);
            }
        }

        // 3. For staff/front-desk users, check for a valid enrolled terminal token
        $token = $request->header('X-Terminal-Token')
            ?? $request->bearerToken() // in case sent as terminal auth
            ?? $request->cookie('fitcloud_terminal_token');

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED_TERMINAL_DEVICE',
                'message' => 'Staff actions are restricted to authorized gym terminals. Please perform this action from a registered front-desk computer, or ask the gym owner to authorize this device.',
            ], 403);
        }

        $tokenHash = hash('sha256', $token);
        $terminal = Terminal::where('tenant_id', $tenant->id)
            ->where('token_hash', $tokenHash)
            ->where('status', 'active')
            ->first();

        if (!$terminal) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'UNAUTHORIZED_TERMINAL_DEVICE',
                'message' => 'This device is not an authorized front-desk terminal, or its authorization has been revoked by the gym owner.',
            ], 403);
        }

        // Record terminal activity heartbeat
        $terminal->update([
            'last_seen_at' => now(),
            'last_ip_address' => $request->ip(),
        ]);

        $request->merge(['active_terminal_id' => $terminal->id]);
        app()->instance('terminal', $terminal);

        return $next($request);
    }
}

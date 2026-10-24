<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Terminal;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    /**
     * List all registered terminals and current enforcement status for tenant.
     */
    public function index(Request $request)
    {
        $tenantId = app('tenant')->id;
        $tenant = Tenant::find($tenantId);

        $terminals = Terminal::where('tenant_id', $tenantId)
            ->with('registeredByUser:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'enforceTerminalLock' => (bool) ($tenant?->enforce_terminal_lock),
                'terminals' => $terminals,
            ],
        ]);
    }

    /**
     * Check if current browser holds a valid enrolled terminal token.
     */
    public function checkCurrent(Request $request)
    {
        $tenantId = app('tenant')->id;
        $token = $request->header('X-Terminal-Token');

        if (! $token) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'data' => [
                    'isEnrolled' => false,
                    'terminal' => null,
                ],
            ]);
        }

        $tokenHash = hash('sha256', $token);
        $terminal = Terminal::where('tenant_id', $tenantId)
            ->where('token_hash', $tokenHash)
            ->where('status', 'active')
            ->first();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'isEnrolled' => (bool) $terminal,
                'terminal' => $terminal,
            ],
        ]);
    }

    /**
     * Authorize / Enroll the current browser as a registered front-desk terminal.
     * Restricted to Gym Owners and Super Admins.
     */
    public function enroll(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'device_fingerprint' => 'nullable|string|max:1000',
        ]);

        $tenantId = app('tenant')->id;

        [$plainToken, $tokenHash] = Terminal::generateToken();

        $terminal = Terminal::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'token_hash' => $tokenHash,
            'device_fingerprint' => $validated['device_fingerprint'] ?? null,
            'registered_by' => $user->id,
            'last_ip_address' => $request->ip(),
            'last_seen_at' => now(),
            'status' => 'active',
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "Terminal '{$terminal->name}' successfully authorized!",
            'data' => [
                'terminal' => $terminal,
                'terminal_token' => $plainToken, // Client must save this in localStorage/cookie
            ],
        ], 201);
    }

    /**
     * Toggle enforcement of terminal lock for staff actions.
     * Restricted to Gym Owners and Super Admins.
     */
    public function toggleLock(Request $request)
    {
        $validated = $request->validate([
            'enforce' => 'required|boolean',
        ]);

        $tenantId = app('tenant')->id;
        $tenant = Tenant::findOrFail($tenantId);

        $tenant->update([
            'enforce_terminal_lock' => $validated['enforce'],
        ]);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => $validated['enforce']
                ? 'Front-desk terminal lock is now ENFORCED. Staff must use registered terminals.'
                : 'Front-desk terminal lock has been DISABLED. Staff can access from any device.',
            'data' => [
                'enforceTerminalLock' => (bool) $tenant->enforce_terminal_lock,
            ],
        ]);
    }

    /**
     * Revoke or delete a registered terminal.
     * Restricted to Gym Owners and Super Admins.
     */
    public function destroy(Request $request, string $identifier)
    {
        $tenantId = app('tenant')->id;

        $terminal = Terminal::where('tenant_id', $tenantId)
            ->where(function ($q) use ($identifier) {
                $q->where('id', $identifier)
                    ->orWhere('uuid', $identifier);
            })
            ->first();

        if (! $terminal) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'Terminal not found.',
            ], 404);
        }

        $name = $terminal->name;
        $terminal->delete();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => "Terminal '{$name}' authorization revoked successfully.",
        ]);
    }
}

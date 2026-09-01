<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use App\Services\LicenseService;

class AuthController extends Controller
{
    protected LicenseService $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Register a new developer / gym owner account on central platform fitcloud.io
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenant_users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = TenantUser::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'owner',
        ]);

        // Automatically generate 30-Day Trial License key for new signup
        $license = $this->licenseService->generateLicense(
            'starter',
            100,
            null,
            'trial',
            $user->email,
            30
        );

        $token = $user->createToken('central-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully. Your 30-day Trial License Key has been generated.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'license_key' => $license->key,
                'license_mode' => $license->mode,
                'license_expires_at' => $license->expires_at ? $license->expires_at->toIso8601String() : null,
                'license' => $license,
            ]
        ], 201);
    }

    /**
     * Login developer / gym owner
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = TenantUser::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address or password.',
            ], 401);
        }

        $dbPassword = $user->password ?? '';
        if (str_contains($dbPassword, '{bcrypt}')) {
            $dbPassword = str_replace('{bcrypt}', '', $dbPassword);
        }
        if (str_starts_with($dbPassword, '$2a$')) {
            $dbPassword = '$2y$' . substr($dbPassword, 4);
            $user->update(['password' => $dbPassword]);
        }

        $passwordValid = false;
        if (str_starts_with($dbPassword, '$2y$') || str_starts_with($dbPassword, '$2b$')) {
            try {
                $passwordValid = Hash::check($validated['password'], $dbPassword);
            } catch (\Throwable $e) {
                $passwordValid = false;
            }
        }

        if (!$passwordValid && ($validated['password'] === $dbPassword || md5($validated['password']) === $dbPassword)) {
            $passwordValid = true;
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        if (!$passwordValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email address or password.',
            ], 401);
        }

        $token = $user->createToken('central-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'user' => $this->resolveUserWithTenant($user),
                'token' => $token,
            ]
        ]);
    }

    /**
     * Get authenticated developer profile
     */
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->resolveUserWithTenant($request->user()),
        ]);
    }

    /**
     * Resolve user model with attached tenant info
     */
    private function resolveUserWithTenant($user)
    {
        $user->load('tenant.features', 'tenant.theme', 'tenant.license');
        $userData = $user->toArray();

        if (!$user->tenant) {
            $tenantUser = \Illuminate\Support\Facades\DB::table('tenant_users')->where('email', $user->email)->first();
            if ($tenantUser && $tenantUser->tenant_id) {
                $tenant = \App\Models\Tenant::with(['features', 'theme', 'license'])->find($tenantUser->tenant_id);
            } else {
                $tenant = \App\Models\Tenant::with(['features', 'theme', 'license'])->first();
            }
            if ($tenant) {
                $userData['tenant'] = $tenant;
                $userData['tenant_id'] = $tenant->id;
            }
        }

        return $userData;
    }

    /**
     * Log out authenticated user (revoke current token)
     */
    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}

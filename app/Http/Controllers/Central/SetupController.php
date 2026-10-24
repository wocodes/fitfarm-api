<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\TenantUser;
use App\Services\LicenseService;
use App\Services\TenantProvisioningService;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    protected LicenseService $licenseService;

    protected TenantProvisioningService $provisioningService;

    public function __construct(LicenseService $licenseService, TenantProvisioningService $provisioningService)
    {
        $this->licenseService = $licenseService;
        $this->provisioningService = $provisioningService;
    }

    /**
     * Step 1: Verify License Key
     */
    public function verifyLicense(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        $user = $request->user();

        if (! $user instanceof TenantUser) {
            return response()->json([
                'success' => false,
                'message' => 'Sign in before verifying a license key.',
            ], 403);
        }

        $result = $this->licenseService->verifyKey($request->input('key'));

        if (! $result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
        }

        if ($result['license']->user_email
            && strtolower($result['license']->user_email) !== strtolower($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'This license key belongs to a different account.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['license'],
        ]);
    }

    /**
     * Final Step: Complete Setup & Provision Gym Tenant
     */
    public function completeSetup(Request $request)
    {
        $validated = $request->validate([
            'license_key' => 'required|string',
            'gym_name' => 'required|string|max:255',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email',
            'admin_password' => 'required|string|min:8',
            'timezone' => 'nullable|string',
            'currency' => 'nullable|string',
            'pricing_path' => 'nullable|in:free_tier,annual_subscription',
            'plan_tier' => 'nullable|string',
            'primary_color' => 'nullable|string',
            'accent_color' => 'nullable|string',
            'features' => 'nullable|array',
        ]);

        // First verify license key
        $user = $request->user();

        if (! $user instanceof TenantUser) {
            return response()->json([
                'success' => false,
                'message' => 'Sign in before completing setup.',
            ], 403);
        }

        if ($user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'This account is already attached to a gym.',
            ], 422);
        }

        if (strtolower($validated['admin_email']) !== strtolower($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'The owner email must match the signed-in account.',
            ], 422);
        }

        $verify = $this->licenseService->verifyKey($validated['license_key']);
        if (! $verify['valid']) {
            return response()->json([
                'success' => false,
                'message' => $verify['message'],
            ], 422);
        }

        if ($verify['license']->user_email
            && strtolower($verify['license']->user_email) !== strtolower($user->email)) {
            return response()->json([
                'success' => false,
                'message' => 'This license key belongs to a different account.',
            ], 403);
        }

        $result = $this->provisioningService->provisionTenant($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Gym management system setup successfully!',
            'data' => $result,
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
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

        $result = $this->licenseService->verifyKey($request->input('key'));

        if (!$result['valid']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 422);
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
        $verify = $this->licenseService->verifyKey($validated['license_key']);
        if (!$verify['valid']) {
            return response()->json([
                'success' => false,
                'message' => $verify['message'],
            ], 422);
        }

        $result = $this->provisioningService->provisionTenant($validated);

        return response()->json([
            'success' => true,
            'message' => 'Gym management system setup successfully!',
            'data' => $result,
        ], 201);
    }
}

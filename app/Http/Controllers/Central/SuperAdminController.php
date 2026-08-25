<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Tenant;
use App\Services\LicenseService;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    protected LicenseService $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * Get platform overview stats
     */
    public function dashboard()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'trialing_tenants' => Tenant::where('status', 'trialing')->count(),
                'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
                'total_licenses' => License::count(),
                'active_licenses' => License::where('is_active', true)->count(),
            ]
        ]);
    }

    /**
     * List all gym tenants
     */
    public function listTenants(Request $request)
    {
        $tenants = Tenant::with(['license', 'users', 'theme', 'features'])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }

    /**
     * Generate a new license key
     */
    public function generateLicense(Request $request)
    {
        $validated = $request->validate([
            'plan_tier' => 'nullable|string|in:starter,pro,enterprise',
            'max_members' => 'nullable|integer|min:10',
            'allowed_features' => 'nullable|array',
        ]);

        $license = $this->licenseService->generateLicense(
            $validated['plan_tier'] ?? 'starter',
            $validated['max_members'] ?? 100,
            $validated['allowed_features'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'License key generated successfully.',
            'data' => $license,
        ], 201);
    }

    /**
     * Toggle tenant status (suspend/activate)
     */
    public function toggleTenantStatus(Request $request, string $uuid)
    {
        $tenant = Tenant::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|in:trialing,active,suspended,cancelled',
        ]);

        $tenant->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => "Tenant status updated to {$validated['status']}.",
            'data' => $tenant,
        ]);
    }
}

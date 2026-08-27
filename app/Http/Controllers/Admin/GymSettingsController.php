<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;

class GymSettingsController extends Controller
{
    /**
     * Get gym operating settings & hardware configuration.
     */
    public function show(Request $request)
    {
        $tenantId = $request->get('tenant_id');

        $tenant = Tenant::with(['theme', 'features'])->findOrFail($tenantId);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
                'theme' => $tenant->theme,
                'features' => $tenant->features,
                'biometric_device' => [
                    'device_ip' => '192.168.1.120',
                    'device_port' => '4370',
                    'connection_type' => 'TCP/IP USB',
                    'status' => 'online',
                ],
            ]
        ]);
    }

    /**
     * Update gym settings & branding.
     */
    public function update(Request $request)
    {
        $tenantId = $request->get('tenant_id');
        $tenant = Tenant::findOrFail($tenantId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'logo_url' => 'nullable|string',
            'timezone' => 'nullable|string',
            'currency' => 'nullable|string',
            'primary_color' => 'nullable|string',
            'accent_color' => 'nullable|string',
        ]);

        if (isset($validated['name'])) {
            $tenant->update([
                'name' => $validated['name'],
                'logo_url' => $validated['logo_url'] ?? $tenant->logo_url,
                'timezone' => $validated['timezone'] ?? $tenant->timezone,
                'currency' => $validated['currency'] ?? $tenant->currency,
            ]);
        }

        if (isset($validated['primary_color']) || isset($validated['accent_color'])) {
            ThemeSetting::updateOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'primary_color' => $validated['primary_color'] ?? '#fa6a2e',
                    'accent_color' => $validated['accent_color'] ?? '#1e293b',
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Gym settings and branding updated successfully.',
            'data' => $tenant->fresh(['theme']),
        ]);
    }
}

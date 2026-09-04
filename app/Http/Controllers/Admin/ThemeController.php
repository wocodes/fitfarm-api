<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    /**
     * Get theme settings for current tenant
     */
    public function show(Request $request)
    {
        $tenantId = 1;
        if (app()->bound('tenant') && app('tenant')) {
            $tenantId = app('tenant')->id;
        } elseif ($request->user() && $request->user()->tenant_id) {
            $tenantId = $request->user()->tenant_id;
        } else {
            $slug = $request->query('tenant');
            if ($slug) {
                $t = \App\Models\Tenant::where('slug', $slug)->first();
                if ($t) $tenantId = $t->id;
            }
        }

        $theme = ThemeSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'primary_color' => '#fa6a2e',
                'accent_color' => '#1e293b',
                'bg_color' => '#0f0f11',
                'text_color' => '#ffffff',
                'font_family' => 'Inter',
                'dark_mode' => true,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $theme,
        ]);
    }

    /**
     * Update theme settings for current tenant
     */
    public function update(Request $request)
    {
        $tenantId = 1;
        if (app()->bound('tenant') && app('tenant')) {
            $tenantId = app('tenant')->id;
        } elseif ($request->user() && $request->user()->tenant_id) {
            $tenantId = $request->user()->tenant_id;
        }

        $validated = $request->validate([
            'primary_color' => 'required|string|max:20',
            'accent_color' => 'required|string|max:20',
            'bg_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'font_family' => 'nullable|string|max:50',
            'dark_mode' => 'nullable|boolean',
            'logo_url' => 'nullable|string|max:500',
        ]);

        $theme = ThemeSetting::updateOrCreate(
            ['tenant_id' => $tenantId],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Theme settings updated successfully.',
            'data' => $theme,
        ]);
    }
}

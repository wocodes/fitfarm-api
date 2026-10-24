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
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found.',
            ], 404);
        }

        $theme = ThemeSetting::firstOrCreate(
            ['tenant_id' => $tenant->id],
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
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found.',
            ], 404);
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
            ['tenant_id' => $tenant->id],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Theme settings updated successfully.',
            'data' => $theme,
        ]);
    }
}

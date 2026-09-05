<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantCmsContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CmsController extends Controller
{
    private array $validSections = [
        'hero',
        'amenities',
        'classes',
        'trainers',
        'testimonials',
        'faqs',
        'contact_socials',
        'section_visibility',
    ];

    /**
     * Get all CMS sections for a tenant with fallback to rich defaults.
     */
    public function index(Request $request)
    {
        $tenantId = $request->get('tenant_id') ?? Tenant::first()?->id ?? 1;
        $tenant = Tenant::find($tenantId) ?? Tenant::first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No active tenant found.',
            ], 404);
        }

        $sections = [];
        foreach ($this->validSections as $sec) {
            $sections[$sec] = $tenant->getCmsSection($sec);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'sections' => $sections,
            ],
        ]);
    }

    /**
     * Get a specific CMS section.
     */
    public function show(Request $request, string $section)
    {
        if (!in_array($section, $this->validSections)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid CMS section: {$section}",
            ], 422);
        }

        $tenantId = $request->get('tenant_id') ?? Tenant::first()?->id ?? 1;
        $tenant = Tenant::find($tenantId) ?? Tenant::first();

        return response()->json([
            'success' => true,
            'data' => [
                'section' => $section,
                'content' => $tenant ? $tenant->getCmsSection($section) : [],
            ],
        ]);
    }

    /**
     * Save or update a specific CMS section.
     */
    public function updateSection(Request $request, string $section)
    {
        if (!in_array($section, $this->validSections)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid CMS section: {$section}",
            ], 422);
        }

        $tenantId = $request->get('tenant_id') ?? Tenant::first()?->id ?? 1;
        $tenant = Tenant::find($tenantId) ?? Tenant::first();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No tenant found.',
            ], 404);
        }

        $payload = $request->input('content') ?? $request->all();
        // Strip tenant_id if present in payload
        if (isset($payload['tenant_id'])) {
            unset($payload['tenant_id']);
        }

        $cmsRecord = TenantCmsContent::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'section_key' => $section,
            ],
            [
                'content' => $payload,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "CMS section '{$section}' updated successfully.",
            'data' => [
                'section' => $section,
                'content' => $cmsRecord->content,
            ],
        ]);
    }

    /**
     * Upload an image/media asset for CMS sections (trainers, slides, amenities).
     */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,webp,svg|max:5120',
        ]);

        if (!$request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'No image file uploaded.',
            ], 422);
        }

        $file = $request->file('file');
        $fileName = Str::random(24) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('public/cms', $fileName);

        $url = url(Storage::url('cms/' . $fileName));

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully.',
            'data' => [
                'url' => $url,
                'path' => $path,
            ],
        ]);
    }
}

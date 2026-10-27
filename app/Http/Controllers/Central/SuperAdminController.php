<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\DemoTenantService;
use App\Services\LicenseService;
use App\Services\PlatformSettingsService;
use App\Services\TenantPurgeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

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
                'demo_tenants' => Tenant::where('is_demo', true)->count(),
            ],
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
            $validated['allowed_features'] ?? null,
            'trial',
            null,
            app(PlatformSettingsService::class)->trialDays()
        );

        return response()->json([
            'success' => true,
            'message' => 'License key generated successfully.',
            'data' => $license,
        ], 201);
    }

    /**
     * Renew an existing license key & switch mode (e.g. trial -> full)
     */
    public function renewLicense(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'mode' => 'nullable|string|in:trial,full',
            'extension_days' => 'nullable|integer|min:1',
        ]);

        $result = $this->licenseService->renewLicense(
            $validated['key'],
            $validated['mode'] ?? 'full',
            $validated['extension_days'] ?? 365
        );

        if (! $result['success']) {
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
     * Read the editable public landing/platform settings.
     */
    public function platformContent(PlatformSettingsService $settings)
    {
        return response()->json([
            'success' => true,
            'data' => $settings->all(),
        ]);
    }

    /**
     * Update the editable public landing/platform settings.
     */
    public function updatePlatformContent(Request $request, PlatformSettingsService $settings)
    {
        $validated = $request->validate([
            'trial_days' => 'required|integer|min:1|max:365',
            'default_currency' => 'required|in:NGN,USD',
            'usd_rate' => 'required|numeric|min:0.0001',
            'landing' => 'required|array',
        ]);

        $content = $settings->save($validated);

        return response()->json([
            'success' => true,
            'message' => 'Landing page settings updated.',
            'data' => $content,
        ]);
    }

    public function listDemos()
    {
        $demos = Tenant::with(['users', 'theme'])
            ->where('is_demo', true)
            ->latest()
            ->get()
            ->map(fn (Tenant $tenant) => $this->demoPayload($tenant));

        return response()->json([
            'success' => true,
            'data' => $demos,
        ]);
    }

    public function createDemo(Request $request, DemoTenantService $demoService)
    {
        $validated = $request->validate([
            'gym_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'owner_email' => 'required|email|max:255|unique:tenant_users,email',
            'owner_phone' => 'nullable|string|max:50',
            'duration_days' => 'nullable|integer|min:1|max:365',
            'notes' => 'nullable|string|max:2000',
            'primary_color' => 'nullable|string|max:32',
            'accent_color' => 'nullable|string|max:32',
        ]);

        $result = $demoService->create($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Demo gym created.',
            'data' => array_merge($this->demoPayload($result['tenant']), [
                'access_url' => $result['access_url'],
                'member_portal_url' => $result['member_portal_url'],
            ]),
        ], 201);
    }

    public function extendDemo(Request $request, string $uuid, DemoTenantService $demoService)
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $tenant = Tenant::where('uuid', $uuid)->where('is_demo', true)->firstOrFail();
        $tenant = $demoService->extend($tenant, (int) ($validated['days'] ?? 30), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Demo extended.',
            'data' => $this->demoPayload($tenant),
        ]);
    }

    public function convertDemo(Request $request, string $uuid, DemoTenantService $demoService)
    {
        $tenant = Tenant::where('uuid', $uuid)->where('is_demo', true)->firstOrFail();
        $tenant = $demoService->convert($tenant, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Demo converted to a live gym.',
            'data' => $this->demoPayload($tenant),
        ]);
    }

    public function deleteDemo(Request $request, string $uuid, TenantPurgeService $purgeService)
    {
        $tenant = Tenant::where('uuid', $uuid)->where('is_demo', true)->firstOrFail();

        $validated = $request->validate([
            'confirmation' => 'required|string',
        ]);

        if ($validated['confirmation'] !== $tenant->slug) {
            return response()->json([
                'success' => false,
                'message' => 'Type the gym subdomain exactly to confirm deletion.',
            ], 422);
        }

        $counts = $purgeService->purge($tenant, 'manual-demo-delete');

        return response()->json([
            'success' => true,
            'message' => 'Demo gym and its data have been deleted.',
            'data' => $counts,
        ]);
    }

    public function demoAccessLink(string $uuid, DemoTenantService $demoService)
    {
        $tenant = Tenant::where('uuid', $uuid)->where('is_demo', true)->firstOrFail();
        $owner = $tenant->users()->where('role', 'owner')->firstOrFail();
        $token = $demoService->issueAccessToken($owner);

        return response()->json([
            'success' => true,
            'data' => [
                'access_url' => $demoService->accessUrl($tenant, $token),
                'member_portal_url' => $tenant->memberPortalUrl(),
            ],
        ]);
    }

    private function demoPayload(Tenant $tenant): array
    {
        $owner = $tenant->users->firstWhere('role', 'owner') ?: $tenant->users->first();

        return [
            'uuid' => $tenant->uuid,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'is_demo' => $tenant->is_demo,
            'demo_status' => $tenant->demo_status,
            'demo_expires_at' => $tenant->demo_expires_at?->toIso8601String(),
            'demo_purge_after' => $tenant->demo_purge_after?->toIso8601String(),
            'demo_notes' => $tenant->demo_notes,
            'owner_name' => $owner?->name,
            'owner_email' => $owner?->email,
            'days_remaining' => $this->daysUntil($tenant->demo_expires_at),
            'purge_in_days' => $this->daysUntil($tenant->demo_purge_after),
            'member_portal_url' => $tenant->memberPortalUrl(),
            'created_at' => $tenant->created_at?->toIso8601String(),
        ];
    }

    private function daysUntil(?Carbon $date): ?int
    {
        if (! $date) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInSeconds($date, false) / 86400));
    }

    /**
     * Destructively remove every gym tenant and all tenant-owned data.
     * The platform super-admin accounts and global permissions are preserved.
     */
    public function resetPlatform(Request $request)
    {
        $validated = $request->validate([
            'confirmation' => 'required|string',
        ]);

        if ($validated['confirmation'] !== 'RESET ALL GYMS') {
            return response()->json([
                'success' => false,
                'message' => 'Type RESET ALL GYMS exactly to confirm this action.',
            ], 422);
        }

        $counts = DB::transaction(function () {
            $tenantIds = Tenant::query()->pluck('id');
            $superAdminIds = TenantUser::query()
                ->whereNull('tenant_id')
                ->whereIn('role', ['ROLE_SUPER_ADMIN', 'ROLE_SUPERADMIN', 'super_admin', 'superadmin'])
                ->pluck('id');

            $counts = [
                'tenants' => Tenant::count(),
                'licenses' => License::count(),
            ];

            Tenant::query()->get()->each->delete();

            License::query()->delete();
            TenantUser::query()
                ->whereNull('tenant_id')
                ->whereNotIn('id', $superAdminIds)
                ->delete();
            Role::query()->whereNotNull('tenant_id')->delete();

            PersonalAccessToken::query()
                ->where('tokenable_type', Member::class)
                ->delete();
            PersonalAccessToken::query()
                ->where('tokenable_type', TenantUser::class)
                ->whereNotIn('tokenable_id', $superAdminIds)
                ->delete();

            return $counts;
        });

        Storage::disk('local')->deleteDirectory('backups');
        Storage::disk('public')->deleteDirectory('cms');
        Cache::flush();

        Log::warning('Platform data reset executed.', [
            'super_admin_id' => $request->user()?->id,
            'counts' => $counts,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'All gym tenants and related data have been removed.',
            'data' => $counts,
        ]);
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

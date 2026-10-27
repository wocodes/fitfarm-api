<?php

namespace App\Services;

use App\Models\License;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\TenantLifecycleLog;
use App\Models\TenantUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

class TenantPurgeService
{
    /**
     * Permanently remove a tenant and every piece of tenant-owned data.
     */
    public function purge(Tenant $tenant, string $reason = 'manual'): array
    {
        $tenant->loadMissing('users');
        $owner = $tenant->users->firstWhere('role', 'owner') ?: $tenant->users->first();
        $tenantId = $tenant->id;
        $slug = $tenant->slug;
        $metadata = [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'uuid' => $tenant->uuid,
            'owner_name' => $owner?->name,
            'owner_email' => $owner?->email,
        ];

        $counts = DB::transaction(function () use ($tenant, $tenantId) {
            $memberIds = Member::where('tenant_id', $tenantId)->pluck('id');
            $userIds = TenantUser::where('tenant_id', $tenantId)->pluck('id');
            $roleIds = Role::where('tenant_id', $tenantId)->pluck('id');

            $counts = [
                'members' => $memberIds->count(),
                'tenant_users' => $userIds->count(),
                'roles' => $roleIds->count(),
                'licenses' => License::where('tenant_id', $tenantId)->count(),
            ];

            PersonalAccessToken::query()
                ->where('tokenable_type', Member::class)
                ->whereIn('tokenable_id', $memberIds)
                ->delete();

            PersonalAccessToken::query()
                ->where('tokenable_type', TenantUser::class)
                ->whereIn('tokenable_id', $userIds)
                ->delete();

            if ($userIds->isNotEmpty()) {
                DB::table('model_has_roles')
                    ->where('model_type', TenantUser::class)
                    ->whereIn('model_id', $userIds)
                    ->delete();

                DB::table('model_has_permissions')
                    ->where('model_type', TenantUser::class)
                    ->whereIn('model_id', $userIds)
                    ->delete();
            }

            if ($roleIds->isNotEmpty()) {
                DB::table('role_has_permissions')->whereIn('role_id', $roleIds)->delete();
                DB::table('roles')->whereIn('id', $roleIds)->delete();
            }

            // This table has no cascading foreign key.
            DB::table('gym_locations')->where('tenant_id', $tenantId)->delete();

            // Licenses use ON DELETE SET NULL, so remove them explicitly.
            License::where('tenant_id', $tenantId)->delete();

            $tenant->delete();

            return $counts;
        });

        $this->deleteFiles($slug);
        Cache::flush();

        TenantLifecycleLog::create([
            'tenant_uuid' => $metadata['uuid'],
            'gym_name' => $metadata['name'],
            'slug' => $metadata['slug'],
            'owner_name' => $metadata['owner_name'],
            'owner_email' => $metadata['owner_email'],
            'action' => 'deleted',
            'notes' => $reason,
            'metadata' => $counts,
        ]);

        return $counts;
    }

    private function deleteFiles(string $slug): void
    {
        Storage::disk('public')->deleteDirectory('cms/'.$slug);
        Storage::disk('public')->deleteDirectory('branding/'.$slug);
        Storage::disk('local')->deleteDirectory('backups/'.$slug);
    }
}

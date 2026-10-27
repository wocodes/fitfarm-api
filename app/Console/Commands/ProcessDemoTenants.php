<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantLifecycleLog;
use App\Services\TenantPurgeService;
use Illuminate\Console\Command;

class ProcessDemoTenants extends Command
{
    protected $signature = 'demos:process {--dry-run : Show what would be expired or purged}';

    protected $description = 'Expire demo tenants and purge demos whose grace period has ended';

    public function handle(TenantPurgeService $purgeService): int
    {
        $now = now();
        $dryRun = (bool) $this->option('dry-run');
        $expired = 0;
        $purged = 0;

        Tenant::where('is_demo', true)
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($now, $dryRun, $purgeService, &$expired, &$purged) {
                if ($tenant->demo_purge_after && $tenant->demo_purge_after->lte($now)) {
                    $this->line("Purge {$tenant->name} ({$tenant->slug})");

                    if (! $dryRun) {
                        $counts = $purgeService->purge($tenant, 'demo-expired');
                        $this->line('  Removed '.json_encode($counts));
                    }

                    $purged++;

                    return;
                }

                if ($tenant->demo_expires_at
                    && $tenant->demo_expires_at->lte($now)
                    && $tenant->demo_status === 'active') {
                    $this->line("Expire {$tenant->name} ({$tenant->slug})");
                    $expired++;

                    if (! $dryRun) {
                        $tenant->update(['demo_status' => 'expired']);
                        TenantLifecycleLog::create([
                            'tenant_uuid' => $tenant->uuid,
                            'gym_name' => $tenant->name,
                            'slug' => $tenant->slug,
                            'owner_name' => $tenant->users()->where('role', 'owner')->value('name'),
                            'owner_email' => $tenant->users()->where('role', 'owner')->value('email'),
                            'action' => 'expired',
                            'metadata' => [
                                'demo_expires_at' => $tenant->demo_expires_at?->toIso8601String(),
                                'demo_purge_after' => $tenant->demo_purge_after?->toIso8601String(),
                            ],
                        ]);
                    }
                }
            });

        $this->info(($dryRun ? '[dry run] ' : '')."Expired: {$expired}; purged: {$purged}");

        return self::SUCCESS;
    }
}

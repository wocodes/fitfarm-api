<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTenantTest extends TestCase
{
    use RefreshDatabase;

    private TenantUser $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = TenantUser::create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'superadmin-demo@example.test',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);
    }

    public function test_super_admin_can_create_a_30_day_demo_with_sample_data(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/super-admin/demos', [
                'gym_name' => 'Peak Performance Demo',
                'owner_name' => 'Demo Owner',
                'owner_email' => 'demo-owner@example.test',
                'owner_phone' => '08011223322',
                'duration_days' => 30,
                'notes' => 'Prospect requested a walkthrough',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_demo', true)
            ->assertJsonPath('data.demo_status', 'active')
            ->assertJsonPath('data.owner_email', 'demo-owner@example.test')
            ->assertJsonPath('data.days_remaining', 30)
            ->assertJsonPath('data.purge_in_days', 37);

        $tenant = Tenant::where('slug', $response->json('data.slug'))->firstOrFail();

        $this->assertTrue($tenant->is_demo);
        $this->assertNotNull($tenant->demo_expires_at);
        $this->assertNotNull($tenant->demo_purge_after);
        $this->assertSame(8, Member::where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('tenant_features', [
            'tenant_id' => $tenant->id,
            'feature_key' => 'payments',
            'enabled' => true,
        ]);
        $this->assertStringContainsString('#token=', $response->json('data.access_url'));
        $this->assertDatabaseHas('tenant_lifecycle_logs', [
            'tenant_uuid' => $tenant->uuid,
            'action' => 'created',
        ]);
    }

    public function test_demo_can_be_extended_and_converted(): void
    {
        $create = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/super-admin/demos', [
                'gym_name' => 'Convertible Demo',
                'owner_name' => 'Owner',
                'owner_email' => 'convertible@example.test',
                'duration_days' => 30,
            ])
            ->assertCreated();

        $uuid = $create->json('data.uuid');

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/super-admin/demos/{$uuid}/extend", ['days' => 30])
            ->assertOk()
            ->assertJsonPath('success', true);

        $tenant = Tenant::where('uuid', $uuid)->firstOrFail();
        $this->assertTrue($tenant->demo_expires_at->greaterThan(now()->addDays(59)));

        $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson("/api/v1/super-admin/demos/{$uuid}/convert")
            ->assertOk()
            ->assertJsonPath('data.is_demo', false);

        $tenant->refresh();
        $this->assertFalse($tenant->is_demo);
        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->demo_expires_at);
        $this->assertDatabaseHas('tenant_lifecycle_logs', [
            'tenant_uuid' => $tenant->uuid,
            'action' => 'converted',
        ]);
    }

    public function test_demo_can_be_deleted_with_typed_confirmation(): void
    {
        $create = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/super-admin/demos', [
                'gym_name' => 'Disposable Demo',
                'owner_name' => 'Owner',
                'owner_email' => 'disposable@example.test',
            ])
            ->assertCreated();

        $uuid = $create->json('data.uuid');
        $slug = $create->json('data.slug');

        $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/super-admin/demos/{$uuid}", ['confirmation' => 'wrong-slug'])
            ->assertUnprocessable();

        $this->actingAs($this->superAdmin, 'sanctum')
            ->deleteJson("/api/v1/super-admin/demos/{$uuid}", ['confirmation' => $slug])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('tenants', ['uuid' => $uuid]);
        $this->assertDatabaseHas('tenant_lifecycle_logs', [
            'tenant_uuid' => $uuid,
            'action' => 'deleted',
        ]);
    }

    public function test_expired_demo_is_purged_after_grace_period(): void
    {
        $create = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/v1/super-admin/demos', [
                'gym_name' => 'Expired Demo',
                'owner_name' => 'Owner',
                'owner_email' => 'expired-demo@example.test',
                'duration_days' => 30,
            ])
            ->assertCreated();

        $tenant = Tenant::where('uuid', $create->json('data.uuid'))->firstOrFail();
        $tenant->update([
            'demo_expires_at' => now()->subDays(2),
            'demo_purge_after' => now()->subDay(),
        ]);

        $this->artisan('demos:process')->assertSuccessful();

        $this->assertDatabaseMissing('tenants', ['uuid' => $tenant->uuid]);
    }
}

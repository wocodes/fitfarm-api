<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_reset_all_tenants_while_keeping_platform_admin(): void
    {
        $superAdmin = TenantUser::create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'platform@example.com',
            'password' => 'password',
            'role' => 'ROLE_SUPER_ADMIN',
        ]);

        $tenant = Tenant::create([
            'name' => 'Gym To Delete',
            'slug' => 'gym-to-delete',
            'status' => 'active',
        ]);

        $owner = TenantUser::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
            'email' => 'owner@gym.test',
            'password' => 'password',
            'role' => 'owner',
        ]);

        Member::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Member',
            'last_name' => 'One',
            'email' => 'member@gym.test',
            'phone' => '08000000000',
            'status' => 'active',
        ]);

        License::create([
            'key' => 'FC-RESET-TEST',
            'mode' => 'trial',
            'plan_tier' => 'starter',
            'is_active' => true,
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/v1/super-admin/system/reset', [
                'confirmation' => 'RESET ALL GYMS',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('licenses', 0);
        $this->assertDatabaseMissing('tenant_users', ['id' => $owner->id]);
        $this->assertDatabaseHas('tenant_users', ['id' => $superAdmin->id]);
    }

    public function test_reset_requires_exact_confirmation_phrase(): void
    {
        $superAdmin = TenantUser::create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'platform2@example.com',
            'password' => 'password',
            'role' => 'ROLE_SUPER_ADMIN',
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson('/api/v1/super-admin/system/reset', [
                'confirmation' => 'reset',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}

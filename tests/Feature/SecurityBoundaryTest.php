<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\PricingPlan;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private TenantUser $ownerA;

    private TenantUser $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Alpha Gym',
            'slug' => 'alpha-gym',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Beta Gym',
            'slug' => 'beta-gym',
            'status' => 'active',
        ]);

        $this->ownerA = TenantUser::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Owner A',
            'email' => 'owner-a@example.com',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $this->superAdmin = TenantUser::create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'platform@example.com',
            'password' => 'password',
            'role' => 'ROLE_SUPER_ADMIN',
        ]);
    }

    public function test_owner_cannot_access_platform_super_admin_routes(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);
    }

    public function test_super_admin_can_access_platform_routes(): void
    {
        $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(200);
    }

    public function test_unauthenticated_requests_cannot_mutate_theme(): void
    {
        $this->postJson('/api/v1/admin/theme', [
            'primary_color' => '#000000',
            'accent_color' => '#ffffff',
        ])->assertStatus(401);
    }

    public function test_member_can_login_and_read_only_their_own_profile(): void
    {
        $member = Member::create([
            'tenant_id' => $this->tenantA->id,
            'first_name' => 'Member',
            'last_name' => 'One',
            'email' => 'member@example.com',
            'phone' => '08000000000',
            'password' => 'password',
            'status' => 'active',
        ]);

        Member::create([
            'tenant_id' => $this->tenantB->id,
            'first_name' => 'Member',
            'last_name' => 'Two',
            'email' => 'member@example.com',
            'phone' => '08000000001',
            'password' => 'password',
            'status' => 'active',
        ]);

        $login = $this->withHeader('X-Tenant-Slug', $this->tenantA->slug)
            ->postJson('/api/v1/member/auth/login', [
                'email' => 'member@example.com',
                'password' => 'password',
            ]);

        $login->assertStatus(200)
            ->assertJsonPath('data.member.id', $member->id);

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/member/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.member.id', $member->id);
    }

    public function test_member_token_cannot_access_admin_routes(): void
    {
        $member = Member::create([
            'tenant_id' => $this->tenantA->id,
            'first_name' => 'Member',
            'last_name' => 'One',
            'email' => 'member-token@example.com',
            'phone' => '08000000000',
            'password' => 'password',
            'status' => 'active',
        ]);

        $token = $member->createToken('member-portal-token', ['member'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/members')
            ->assertStatus(401);
    }

    public function test_member_purchase_rejects_amount_mismatch(): void
    {
        $member = Member::create([
            'tenant_id' => $this->tenantA->id,
            'first_name' => 'Member',
            'last_name' => 'One',
            'email' => 'buyer@example.com',
            'phone' => '08000000000',
            'password' => 'password',
            'status' => 'active',
        ]);

        $plan = PricingPlan::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Monthly',
            'price' => 10000,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $token = $member->createToken('member-portal-token', ['member'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/member/plans/purchase', [
                'pricing_plan_id' => $plan->id,
                'payment_reference' => 'TEST-REFERENCE',
                'amount' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_member_cannot_buy_another_tenants_plan(): void
    {
        $member = Member::create([
            'tenant_id' => $this->tenantA->id,
            'first_name' => 'Member',
            'last_name' => 'One',
            'email' => 'cross-tenant-buyer@example.com',
            'phone' => '08000000000',
            'password' => 'password',
            'status' => 'active',
        ]);

        $foreignPlan = PricingPlan::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Foreign Plan',
            'price' => 10000,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        $token = $member->createToken('member-portal-token', ['member'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/member/plans/purchase', [
                'pricing_plan_id' => $foreignPlan->id,
                'payment_reference' => 'TEST-FOREIGN-REFERENCE',
                'amount' => 10000,
            ])
            ->assertStatus(404);
    }

    public function test_setup_completion_requires_authentication(): void
    {
        $this->postJson('/api/v1/setup/complete', [
            'license_key' => 'FC-DEMO-2026-KEYS',
            'gym_name' => 'No Auth Gym',
            'admin_name' => 'No Auth',
            'admin_email' => 'no-auth@example.com',
            'admin_password' => 'Password123!',
        ])->assertStatus(401);
    }
}

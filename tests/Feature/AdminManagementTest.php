<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected TenantUser $userA;
    protected TenantUser $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'PolanFit Gym',
            'slug' => 'polanfit-gym',
            'status' => 'active',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Iron Fitness',
            'slug' => 'iron-fitness',
            'status' => 'active',
        ]);

        $this->userA = TenantUser::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'adminA@polanfit.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $this->userB = TenantUser::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'adminB@ironfitness.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);
    }

    public function test_tenant_a_cannot_see_tenant_b_members(): void
    {
        // Create member for Tenant A
        app()->instance('tenant', $this->tenantA);
        Member::create([
            'first_name' => 'Gabriel',
            'last_name' => 'O.',
            'email' => 'gabriel@gmail.com',
        ]);

        // Create member for Tenant B
        app()->instance('tenant', $this->tenantB);
        Member::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@gmail.com',
        ]);

        // Query API as Tenant A
        $responseA = $this->actingAs($this->userA, 'sanctum')
            ->withHeaders(['X-Tenant-UUID' => $this->tenantA->uuid])
            ->getJson('/api/v1/admin/members');

        $responseA->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.first_name', 'Gabriel');

        // Query API as Tenant B
        $responseB = $this->actingAs($this->userB, 'sanctum')
            ->withHeaders(['X-Tenant-UUID' => $this->tenantB->uuid])
            ->getJson('/api/v1/admin/members');

        $responseB->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.first_name', 'John');
    }

    public function test_can_process_check_in_and_retrieve_leaderboard(): void
    {
        app()->instance('tenant', $this->tenantA);
        $member = Member::create([
            'first_name' => 'Kelechi',
            'last_name' => 'Oshoname',
            'email' => 'kelechi@gmail.com',
        ]);

        $checkInRes = $this->actingAs($this->userA, 'sanctum')
            ->withHeaders(['X-Tenant-UUID' => $this->tenantA->uuid])
            ->postJson('/api/v1/admin/check-ins', [
                'member_id' => $member->id,
                'method' => 'manual',
            ]);

        $checkInRes->assertStatus(201)
            ->assertJsonPath('data.member.display_name', 'Kelechi O.');

        $leaderboardRes = $this->actingAs($this->userA, 'sanctum')
            ->withHeaders(['X-Tenant-UUID' => $this->tenantA->uuid])
            ->getJson('/api/v1/admin/campaigns/leaderboard');

        $leaderboardRes->assertStatus(200)
            ->assertJsonPath('data.top10.0.fullName', 'Kelechi O.')
            ->assertJsonPath('data.top10.0.checkInCount', 1)
            ->assertJsonPath('data.top10.0.points', 10);
    }
}

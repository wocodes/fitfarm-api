<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected TenantUser $user;
    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Biometric Gym',
            'slug' => 'biometric-gym',
            'status' => 'active',
        ]);

        app()->instance('tenant', $this->tenant);

        // Enable biometric feature for tenant
        TenantFeature::create([
            'tenant_id' => $this->tenant->id,
            'feature_key' => 'biometric_access',
            'enabled' => true,
        ]);

        $this->user = TenantUser::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Bio',
            'email' => 'admin@biometric.com',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $this->member = Member::create([
            'first_name' => 'Kelechi',
            'last_name' => 'Oshoname',
            'email' => 'kelechi@gmail.com',
            'status' => 'active',
            'fingerprint_template' => 'FP_TEMPLATE_KELECHI_12345',
        ]);

        $plan = PricingPlan::create([
            'name' => 'Monthly Standard',
            'price' => 10000.00,
            'duration_days' => 30,
        ]);

        MemberPlan::create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'pricing_plan_id' => $plan->id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
            'status' => 'active',
        ]);
    }

    public function test_can_process_fingerprint_check_in_scan(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Tenant-UUID' => $this->tenant->uuid])
            ->postJson('/api/v1/admin/biometrics/check-in', [
                'fingerprint_template' => 'FP_TEMPLATE_KELECHI_12345',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Access granted! Welcome, Kelechi O..',
            ])
            ->assertJsonPath('data.check_in.method', 'fingerprint');

        $this->assertDatabaseHas('attendance_logs', [
            'member_id' => $this->member->id,
            'method' => 'fingerprint',
        ]);
    }

    public function test_rejects_fingerprint_scan_when_feature_disabled(): void
    {
        // Disable feature
        TenantFeature::where('tenant_id', $this->tenant->id)
            ->where('feature_key', 'biometric_access')
            ->update(['enabled' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeaders(['X-Tenant-UUID' => $this->tenant->uuid])
            ->postJson('/api/v1/admin/biometrics/check-in', [
                'fingerprint_template' => 'FP_TEMPLATE_KELECHI_12345',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'feature' => 'biometric_access',
            ]);
    }
}

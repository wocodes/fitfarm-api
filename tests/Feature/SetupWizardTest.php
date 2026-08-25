<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_verify_valid_license_key(): void
    {
        $response = $this->postJson('/api/v1/setup/verify-license', [
            'key' => 'FC-DEMO-2026-KEYS',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'License key verified successfully.',
            ])
            ->assertJsonPath('data.key', 'FC-DEMO-2026-KEYS');
    }

    public function test_fails_verification_on_invalid_license_key(): void
    {
        $response = $this->postJson('/api/v1/setup/verify-license', [
            'key' => 'FC-INVALID-KEY',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'License key not found. Please check and try again.',
            ]);
    }

    public function test_can_complete_setup_and_provision_gym_tenant(): void
    {
        $response = $this->postJson('/api/v1/setup/complete', [
            'license_key' => 'FC-DEMO-2026-KEYS',
            'gym_name' => 'PolanFit Arena',
            'admin_name' => 'Kelechi Gabriel',
            'admin_email' => 'kelechi@polanfit.com',
            'admin_password' => 'SecurePass123!',
            'timezone' => 'Africa/Lagos',
            'currency' => 'NGN',
            'pricing_path' => 'free_tier',
            'primary_color' => '#fa6a2e',
            'accent_color' => '#1e293b',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Gym management system setup successfully!',
            ])
            ->assertJsonPath('data.tenant.slug', 'polanfit-arena')
            ->assertJsonPath('data.tenant.currency', 'NGN')
            ->assertJsonPath('data.urls.admin_app', 'https://admin.polanfit-arena.fitcloud.io')
            ->assertJsonPath('data.urls.member_app', 'https://polanfit-arena.fitcloud.io');

        $this->assertDatabaseHas('tenants', [
            'slug' => 'polanfit-arena',
            'name' => 'PolanFit Arena',
        ]);

        $this->assertDatabaseHas('tenant_users', [
            'email' => 'kelechi@polanfit.com',
            'role' => 'owner',
        ]);
    }
}

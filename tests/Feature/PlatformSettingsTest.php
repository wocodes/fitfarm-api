<?php

namespace Tests\Feature;

use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_platform_settings_expose_defaults(): void
    {
        $this->getJson('/api/v1/platform')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.trial_days', 90)
            ->assertJsonPath('data.default_currency', 'NGN')
            ->assertJsonPath('data.landing.pricing.1.price_usd', 299);
    }

    public function test_super_admin_can_update_landing_pricing_and_trial_days(): void
    {
        $superAdmin = TenantUser::create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'settings-admin@example.com',
            'password' => 'password',
            'role' => 'ROLE_SUPER_ADMIN',
        ]);

        $landing = [
            'hero' => [
                'badge' => 'Updated badge',
                'title' => 'Updated title',
                'highlight' => 'Updated highlight',
                'subtitle' => 'Updated subtitle',
                'primary_cta' => 'Start now',
                'secondary_cta' => 'Explore',
            ],
            'stats' => [
                ['value' => '100%', 'label' => 'Custom stat'],
            ],
            'pricing' => [
                [
                    'id' => 'annual',
                    'label' => 'Plan',
                    'name' => 'Custom Annual',
                    'price_ngn' => 500000,
                    'price_usd' => 350,
                    'period' => '/ year',
                    'description' => 'Custom pricing',
                    'features' => ['Feature one'],
                    'featured' => true,
                    'cta' => 'Choose',
                ],
            ],
            'faqs' => [
                ['q' => 'Question?', 'a' => 'Answer.'],
            ],
        ];

        $this->actingAs($superAdmin, 'sanctum')
            ->putJson('/api/v1/super-admin/platform-content', [
                'trial_days' => 120,
                'default_currency' => 'USD',
                'usd_rate' => 1500,
                'landing' => $landing,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/platform')
            ->assertStatus(200)
            ->assertJsonPath('data.trial_days', 120)
            ->assertJsonPath('data.default_currency', 'USD')
            ->assertJsonPath('data.landing.pricing.0.price_ngn', 500000)
            ->assertJsonPath('data.landing.pricing.0.price_usd', 350);
    }

    public function test_new_registration_uses_configured_trial_length(): void
    {
        $response = $this->postJson('/api/v1/central/auth/register', [
            'name' => 'New Owner',
            'email' => 'new-trial-owner@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);

        $expiresAt = Carbon::parse($response->json('data.license_expires_at'));

        $this->assertTrue($expiresAt->between(now()->addDays(89), now()->addDays(91)));
    }
}

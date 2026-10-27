<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GymSettingsContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_details_saved_in_admin_are_visible_on_member_portal(): void
    {
        $tenant = Tenant::create([
            'name' => 'Contact Consistency Gym',
            'slug' => 'contact-consistency-gym',
            'status' => 'active',
            'currency' => 'NGN',
        ]);

        $owner = TenantUser::create([
            'tenant_id' => $tenant->id,
            'name' => 'Gym Owner',
            'email' => 'owner@contact-consistency.test',
            'password' => bcrypt('password'),
            'role' => 'owner',
        ]);

        $save = $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Tenant-Slug', $tenant->slug)
            ->postJson('/api/v1/admin/settings', [
                'phone' => '+234 801 122 3322',
                'email' => 'hello@example.com',
                'address' => '123 Fitness Avenue, Lagos',
                'contact_hours' => 'Monday - Sunday: 06:00 AM - 11:00 PM',
                'tax_rate' => 9.5,
                'custom_tagline' => 'Train With Purpose',
                'terms_conditions' => '<p>Updated terms.</p>',
            ]);

        $save->assertOk()
            ->assertJsonPath('success', true);

        $adminSettings = $this->actingAs($owner, 'sanctum')
            ->withHeader('X-Tenant-Slug', $tenant->slug)
            ->getJson('/api/v1/admin/settings');

        $adminSettings->assertOk()
            ->assertJsonPath('data.contact_socials.phone', '+234 801 122 3322')
            ->assertJsonPath('data.contact_socials.contact_phone', '+234 801 122 3322')
            ->assertJsonPath('data.contact_socials.address', '123 Fitness Avenue, Lagos')
            ->assertJsonPath('data.operating_settings.phone', '+234 801 122 3322')
            ->assertJsonPath('data.operating_settings.tax_rate', 9.5)
            ->assertJsonPath('data.operating_settings.custom_tagline', 'Train With Purpose')
            ->assertJsonPath('data.legal_docs.terms_conditions', '<p>Updated terms.</p>');

        $portal = $this->getJson('/api/v1/member/portal/info?tenant='.$tenant->slug);

        $portal->assertOk()
            ->assertJsonPath('data.cms.contact_socials.phone', '+234 801 122 3322')
            ->assertJsonPath('data.cms.contact_socials.email', 'hello@example.com')
            ->assertJsonPath('data.cms.contact_socials.address', '123 Fitness Avenue, Lagos')
            ->assertJsonPath('data.tenant.phone', '+234 801 122 3322')
            ->assertJsonPath('data.tenant.email', 'hello@example.com');
    }
}

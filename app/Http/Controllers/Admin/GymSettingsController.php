<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantCmsContent;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GymSettingsController extends Controller
{
    /**
     * Standard pre-populated legal templates
     */
    private static function getDefaultTerms(): string
    {
        return '<h2>1. Facility Membership & Code of Conduct</h2><p>By entering our gym facilities, members agree to adhere to all facility rules, hygiene protocols, and proper equipment operation guidelines.</p><h2>2. Access Controls & Biometric Verification</h2><p>Access is restricted strictly to active subscription holders. Access gates require a valid biometric fingerprint scan or dynamic encrypted QR badge verification at entrance.</p><h2>3. Fees & Membership Renewals</h2><p>Subscription fees are billed according to chosen plan cycles (Monthly, Quarterly, Annual). Expired memberships incur access freeze following a 3-day grace period.</p>';
    }

    private static function getDefaultPrivacy(): string
    {
        return '<h2>1. Biometric Data Protection Notice</h2><p>Biometric fingerprint templates are stored using encrypted 256-bit hashing algorithms strictly for gate access authentication. Raw fingerprint imagery is never stored or transmitted.</p><h2>2. Member Information Security</h2><p>Personal profile data, email addresses, and transaction receipts are stored in compliance with NDPR and international data privacy benchmarks.</p>';
    }

    private static function getDefaultWaiver(): string
    {
        return '<h2>1. Physical Fitness & Medical Clearance</h2><p>Members confirm they are in sound physical health to engage in cardiovascular and resistance weight training activities.</p><h2>2. Assumption of Risk</h2><p>Members participate at their own risk and release the gym from liability regarding accidental personal injury occurring during unsupervised workout routines.</p>';
    }

    private static function getDefaultRefund(): string
    {
        return '<h2>1. Subscription Cancellations</h2><p>Memberships may be cancelled at any time prior to automatic renewal billing date.</p><h2>2. Refund Policy</h2><p>Subscription payments are non-refundable once activated, except in cases of double payment processing errors verified by administrative audit.</p>';
    }

    /**
     * Get gym operating settings, legal agreements & hardware configuration.
     */
    public function show(Request $request)
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found.',
            ], 404);
        }

        $tenant->load(['theme', 'features']);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => array_merge([
                'tenant' => $tenant,
                'theme' => $tenant->theme,
                'features' => $tenant->features,
            ], $this->settingsPayload($tenant)),
        ]);
    }

    /**
     * Get public settings for landing page footer, legal docs & contact info.
     */
    public function publicSettings(Request $request)
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found.',
            ], 404);
        }

        $tenant->load('theme');
        $payload = $this->settingsPayload($tenant);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'tenant' => [
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'logo_url' => $tenant->logo_url,
                    'timezone' => $tenant->timezone,
                    'currency' => $tenant->currency,
                ],
                'theme' => $tenant->theme,
                'contact_socials' => $payload['contact_socials'],
                'legal_docs' => $payload['legal_docs'],
            ],
        ]);
    }

    /**
     * Upload a tenant logo and store its public URL on the tenant.
     */
    public function uploadLogo(Request $request)
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return response()->json(['success' => false, 'message' => 'Gym not found.'], 404);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $file = $request->file('logo');
        $extension = $file->extension() ?: 'png';
        $path = $file->storeAs('public/branding/'.$tenant->slug, 'logo.'.$extension);
        $url = url(Storage::url('branding/'.$tenant->slug.'/logo.'.$extension));

        $tenant->update(['logo_url' => $url]);

        AuditLogController::log(
            $tenant->id,
            $request->user()?->name ?? 'Owner',
            'TENANT_LOGO_UPDATED',
            'Tenant',
            (string) $tenant->id,
            'Updated the gym logo.'
        );

        return response()->json([
            'success' => true,
            'message' => 'Gym logo updated successfully.',
            'data' => [
                'logo_url' => $url,
                'tenant' => $tenant->fresh(['theme']),
            ],
        ]);
    }

    /**
     * Update gym settings, contact details, legal docs & hardware configuration.
     */
    public function update(Request $request)
    {
        $tenant = $this->resolveTenant();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'logo_url' => 'nullable|string',
            'timezone' => 'nullable|string',
            'currency' => 'nullable|string|max:8',
            'primary_color' => 'nullable|string|max:32',
            'accent_color' => 'nullable|string|max:32',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'contact_address' => 'nullable|string|max:500',
            'contact_hours' => 'nullable|string|max:120',
            'hours_weekday' => 'nullable|string|max:120',
            'hours_weekend' => 'nullable|string|max:120',
            'hours_holidays' => 'nullable|string|max:120',
            'gym_description' => 'nullable|string|max:2000',
            'google_maps_embed' => 'nullable|string|max:2000',
            'social_fb' => 'nullable|string|max:500',
            'social_ig' => 'nullable|string|max:500',
            'social_x' => 'nullable|string|max:500',
            'social_yt' => 'nullable|string|max:500',
            'social_wa' => 'nullable|string|max:500',
            'social_tk' => 'nullable|string|max:500',
            'social_sc' => 'nullable|string|max:500',
            'tax_rate' => 'nullable|numeric',
            'grace_period_days' => 'nullable|integer|min:0',
            'auto_freeze_plans' => 'nullable|boolean',
            'auto_send_welcome_email' => 'nullable|boolean',
            'custom_tagline' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:500',
            'device_ip' => 'nullable|string|max:100',
            'device_port' => 'nullable|string|max:20',
            'unlock_duration' => 'nullable|integer|min:1',
            'relay_mode' => 'nullable|string|max:100',
            'terms_conditions' => 'nullable|string',
            'privacy_policy' => 'nullable|string',
            'liability_waiver' => 'nullable|string',
            'refund_policy' => 'nullable|string',
            'contact_socials' => 'nullable|array',
            'operating_settings' => 'nullable|array',
            'biometric_device' => 'nullable|array',
            'legal_docs' => 'nullable|array',
        ]);

        if (isset($validated['name'])) {
            $tenant->update([
                'name' => $validated['name'],
                'logo_url' => $validated['logo_url'] ?? $tenant->logo_url,
                'timezone' => $validated['timezone'] ?? $tenant->timezone,
                'currency' => $validated['currency'] ?? $tenant->currency,
            ]);
        }

        if (isset($validated['primary_color']) || isset($validated['accent_color'])) {
            $themeData = [
                'primary_color' => $validated['primary_color'] ?? '#fa6a2e',
                'accent_color' => $validated['accent_color'] ?? '#1e293b',
            ];

            if (array_key_exists('logo_url', $validated)) {
                $themeData['logo_url'] = $validated['logo_url'];
            }

            ThemeSetting::updateOrCreate(
                ['tenant_id' => $tenant->id],
                $themeData
            );
        }

        $this->persistContactSettings($request, $tenant);
        $this->persistOperatingSettings($request, $tenant);
        $this->persistBiometricSettings($request, $tenant);
        $this->persistLegalDocuments($request, $tenant);

        AuditLogController::log(
            $tenant->id,
            $request->user()?->name ?? 'Staff',
            'GYM_SETTINGS_UPDATED',
            'GymSettings',
            (string) $tenant->id,
            'Updated gym contact details, operating settings, legal documents, or hardware configuration.'
        );

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Gym settings, contact details, and member portal content updated successfully.',
            'data' => array_merge([
                'tenant' => $tenant->fresh(['theme']),
                'theme' => $tenant->fresh('theme')->theme,
            ], $this->settingsPayload($tenant->fresh())),
        ]);
    }

    private function resolveTenant(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }

    /**
     * Return the complete admin settings payload. Contact data is sourced from
     * the same tenant CMS section used by the public member portal.
     */
    private function settingsPayload(Tenant $tenant): array
    {
        $contact = $this->contactPayload($tenant);
        $legal = $this->legalDocuments($tenant);

        return [
            'terms_conditions' => $legal['terms_conditions'],
            'privacy_policy' => $legal['privacy_policy'],
            'operating_settings' => [
                'phone' => $contact['phone'] ?? '',
                'email' => $contact['email'] ?? '',
                'address' => $contact['address'] ?? '',
                'tax_rate' => (float) $this->storedSetting($tenant, 'operating_tax_rate', 7.5),
                'grace_period_days' => (int) $this->storedSetting($tenant, 'operating_grace_period_days', 3),
                'auto_freeze_plans' => (bool) $this->storedSetting($tenant, 'operating_auto_freeze_plans', true),
                'auto_send_welcome_email' => (bool) $this->storedSetting($tenant, 'operating_auto_send_welcome_email', true),
                'custom_tagline' => (string) $this->storedSetting($tenant, 'operating_custom_tagline', 'Transform Your Fitness Journey'),
                'hero_subtitle' => (string) $this->storedSetting($tenant, 'operating_hero_subtitle', 'Premium Gym & Facility Management System'),
            ],
            'contact_socials' => $contact,
            'legal_docs' => $legal,
            'biometric_device' => [
                'device_ip' => (string) $this->storedSetting($tenant, 'biometric_device_ip', '192.168.1.120'),
                'device_port' => (string) $this->storedSetting($tenant, 'biometric_device_port', '4370'),
                'connection_type' => (string) $this->storedSetting($tenant, 'biometric_connection_type', 'TCP/IP USB Relay'),
                'unlock_duration' => (int) $this->storedSetting($tenant, 'biometric_unlock_duration', 5),
                'relay_mode' => (string) $this->storedSetting($tenant, 'biometric_relay_mode', 'N.O. (Normally Open)'),
                'status' => 'online',
                'latency' => '12ms',
            ],
        ];
    }

    private function contactPayload(Tenant $tenant): array
    {
        $contact = $tenant->getCmsSection('contact_socials');
        $contact['contact_phone'] = $contact['phone'] ?? '';
        $contact['contact_email'] = $contact['email'] ?? '';
        $contact['contact_address'] = $contact['address'] ?? '';
        $contact['contact_hours'] = $contact['contact_hours'] ?? $contact['hours_weekday'] ?? '';

        return $contact;
    }

    private function legalDocuments(Tenant $tenant): array
    {
        return [
            'terms_conditions' => (string) $this->storedSetting($tenant, 'legal_terms_conditions', self::getDefaultTerms()),
            'privacy_policy' => (string) $this->storedSetting($tenant, 'legal_privacy_policy', self::getDefaultPrivacy()),
            'liability_waiver' => (string) $this->storedSetting($tenant, 'legal_liability_waiver', self::getDefaultWaiver()),
            'refund_policy' => (string) $this->storedSetting($tenant, 'legal_refund_policy', self::getDefaultRefund()),
        ];
    }

    private function persistContactSettings(Request $request, Tenant $tenant): void
    {
        $updates = $request->input('contact_socials', []);
        $updates = is_array($updates) ? $updates : [];

        $aliases = [
            'contact_phone' => 'phone',
            'contact_email' => 'email',
            'contact_address' => 'address',
            'contact_hours' => 'contact_hours',
        ];

        foreach ($aliases as $alias => $canonical) {
            if (array_key_exists($alias, $updates) && ! array_key_exists($canonical, $updates)) {
                $updates[$canonical] = $updates[$alias];
            }
        }

        $flatFields = [
            'phone' => ['phone', 'contact_phone'],
            'email' => ['email', 'contact_email'],
            'address' => ['address', 'contact_address'],
            'hours_weekday' => ['hours_weekday', 'contact_hours'],
            'hours_weekend' => ['hours_weekend'],
            'hours_holidays' => ['hours_holidays'],
            'gym_description' => ['gym_description'],
            'google_maps_embed' => ['google_maps_embed'],
            'social_fb' => ['social_fb'],
            'social_ig' => ['social_ig'],
            'social_x' => ['social_x'],
            'social_yt' => ['social_yt'],
            'social_wa' => ['social_wa'],
            'social_tk' => ['social_tk'],
            'social_sc' => ['social_sc'],
        ];

        foreach ($flatFields as $canonical => $requestFields) {
            foreach ($requestFields as $field) {
                if ($request->exists($field)) {
                    $updates[$canonical] = $request->input($field);
                    break;
                }
            }
        }

        if (array_key_exists('hours_weekday', $updates)) {
            $updates['contact_hours'] = $updates['hours_weekday'];
        } elseif (array_key_exists('contact_hours', $updates)) {
            $updates['hours_weekday'] = $updates['contact_hours'];
        }

        $allowed = [
            'phone', 'email', 'address', 'contact_hours',
            'hours_weekday', 'hours_weekend', 'hours_holidays',
            'gym_description', 'google_maps_embed',
            'social_fb', 'social_ig', 'social_x', 'social_yt',
            'social_wa', 'social_tk', 'social_sc',
        ];

        $updates = array_intersect_key($updates, array_flip($allowed));

        if ($updates === []) {
            return;
        }

        $content = array_replace($tenant->getCmsSection('contact_socials'), $updates);

        TenantCmsContent::updateOrCreate(
            ['tenant_id' => $tenant->id, 'section_key' => 'contact_socials'],
            ['content' => $content]
        );
    }

    private function persistOperatingSettings(Request $request, Tenant $tenant): void
    {
        $operating = $request->input('operating_settings', []);
        $operating = is_array($operating) ? $operating : [];

        foreach (['tax_rate', 'grace_period_days', 'auto_freeze_plans', 'auto_send_welcome_email', 'custom_tagline', 'hero_subtitle'] as $key) {
            if ($request->exists($key)) {
                $operating[$key] = $request->input($key);
            }
        }

        foreach (['tax_rate', 'grace_period_days', 'auto_freeze_plans', 'auto_send_welcome_email', 'custom_tagline', 'hero_subtitle'] as $key) {
            if (array_key_exists($key, $operating)) {
                $this->storeSetting($tenant, 'operating_'.$key, $operating[$key], 'operations');
            }
        }
    }

    private function persistBiometricSettings(Request $request, Tenant $tenant): void
    {
        $device = $request->input('biometric_device', []);
        $device = is_array($device) ? $device : [];

        foreach (['device_ip', 'device_port', 'unlock_duration', 'relay_mode'] as $key) {
            if ($request->exists($key)) {
                $device[$key] = $request->input($key);
            }
        }

        foreach (['device_ip', 'device_port', 'unlock_duration', 'relay_mode'] as $key) {
            if (array_key_exists($key, $device)) {
                $this->storeSetting($tenant, 'biometric_'.$key, $device[$key], 'biometric');
            }
        }
    }

    private function persistLegalDocuments(Request $request, Tenant $tenant): void
    {
        $legal = $request->input('legal_docs', []);
        $legal = is_array($legal) ? $legal : [];

        foreach (['terms_conditions', 'privacy_policy', 'liability_waiver', 'refund_policy'] as $key) {
            if ($request->exists($key)) {
                $legal[$key] = $request->input($key);
            }
        }

        foreach (['terms_conditions', 'privacy_policy', 'liability_waiver', 'refund_policy'] as $key) {
            if (array_key_exists($key, $legal)) {
                $this->storeSetting($tenant, 'legal_'.$key, $legal[$key], 'legal');
            }
        }
    }

    private function storedSetting(Tenant $tenant, string $key, mixed $default): mixed
    {
        $row = DB::table('gym_settings')
            ->where('tenant_id', $tenant->id)
            ->where('setting_key', $key)
            ->first();

        if (! $row || $row->setting_value === null) {
            return $default;
        }

        $decoded = json_decode((string) $row->setting_value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $row->setting_value;
    }

    private function storeSetting(Tenant $tenant, string $key, mixed $value, string $category): void
    {
        $existing = DB::table('gym_settings')
            ->where('tenant_id', $tenant->id)
            ->where('setting_key', $key)
            ->exists();

        $payload = [
            'setting_value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'category' => $category,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('gym_settings')
                ->where('tenant_id', $tenant->id)
                ->where('setting_key', $key)
                ->update($payload);

            return;
        }

        DB::table('gym_settings')->insert(array_merge($payload, [
            'tenant_id' => $tenant->id,
            'setting_key' => $key,
            'created_at' => now(),
        ]));
    }
}

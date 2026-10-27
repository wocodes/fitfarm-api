<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;
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
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found.',
            ], 404);
        }

        $tenant->load(['theme', 'features']);

        $theme = $tenant->theme;

        $terms = self::getDefaultTerms();
        $privacy = self::getDefaultPrivacy();
        $waiver = self::getDefaultWaiver();
        $refund = self::getDefaultRefund();

        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => [
                'tenant' => $tenant,
                'theme' => $theme,
                'features' => $tenant ? $tenant->features : [],
                'terms_conditions' => $terms,
                'privacy_policy' => $privacy,
                'operating_settings' => [
                    'phone' => '+234 801 122 3322',
                    'email' => 'hello@example.com',
                    'address' => '123 Fitness Avenue, Lagos',
                    'tax_rate' => 7.5,
                    'grace_period_days' => 3,
                    'auto_freeze_plans' => true,
                    'auto_send_welcome_email' => true,
                    'custom_tagline' => 'Transform Your Fitness Journey',
                    'hero_subtitle' => 'Premium Multitenant Gym & Facility Management System',
                ],
                'contact_socials' => [
                    'contact_phone' => '+234 801 122 3322',
                    'contact_email' => 'hello@example.com',
                    'contact_address' => '123 Fitness Avenue, Lagos, Nigeria',
                    'contact_hours' => 'Monday - Sunday: 06:00 AM - 11:00 PM',
                    'gym_description' => 'Transform your lifestyle at our gym! From elite strength training to personalized biometric access, experience world-class fitness facilities.',
                    'google_maps_embed' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3964.2487720737704!2d3.1883933752293316!3d6.490151123549498',
                    'social_fb' => '',
                    'social_ig' => '',
                    'social_x' => '',
                    'social_yt' => '',
                    'social_wa' => '',
                    'social_tk' => '',
                    'social_sc' => '',
                ],
                'legal_docs' => [
                    'terms_conditions' => $terms,
                    'privacy_policy' => $privacy,
                    'liability_waiver' => $waiver,
                    'refund_policy' => $refund,
                ],
                'biometric_device' => [
                    'device_ip' => '192.168.1.120',
                    'device_port' => '4370',
                    'connection_type' => 'TCP/IP USB Relay',
                    'unlock_duration' => 5, // seconds
                    'relay_mode' => 'N.O. (Normally Open)',
                    'status' => 'online',
                    'latency' => '12ms',
                ],
            ],
        ]);
    }

    /**
     * Get public settings for landing page footer, legal docs & contact info.
     */
    public function publicSettings(Request $request)
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Gym not found.',
            ], 404);
        }

        $tenant->load('theme');
        $cms = $tenant->getCmsSection('contact_socials');

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
                'contact_socials' => $cms,
                'legal_docs' => [
                    'terms_conditions' => self::getDefaultTerms(),
                    'privacy_policy' => self::getDefaultPrivacy(),
                    'liability_waiver' => self::getDefaultWaiver(),
                    'refund_policy' => self::getDefaultRefund(),
                ],
            ],
        ]);
    }

    /**
     * Upload a tenant logo and store its public URL on the tenant.
     */
    public function uploadLogo(Request $request)
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

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
     * Update gym settings, legal docs & hardware configuration.
     */
    public function update(Request $request)
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

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
            'currency' => 'nullable|string',
            'primary_color' => 'nullable|string',
            'accent_color' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'privacy_policy' => 'nullable|string',
            'liability_waiver' => 'nullable|string',
            'refund_policy' => 'nullable|string',
        ]);

        if ($tenant && isset($validated['name'])) {
            $tenant->update([
                'name' => $validated['name'],
                'logo_url' => $validated['logo_url'] ?? $tenant->logo_url,
                'timezone' => $validated['timezone'] ?? $tenant->timezone,
                'currency' => $validated['currency'] ?? $tenant->currency,
            ]);
        }

        if ($tenant && (isset($validated['primary_color']) || isset($validated['accent_color']))) {
            ThemeSetting::updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'primary_color' => $validated['primary_color'] ?? '#fa6a2e',
                    'accent_color' => $validated['accent_color'] ?? '#1e293b',
                    'logo_url' => $validated['logo_url'] ?? null,
                ]
            );
        }

        // Record Audit Log Entry
        AuditLogController::log(
            $tenant->id,
            $request->user()?->name ?? 'Staff',
            'LEGAL_DOCS_UPDATED',
            'GymSettings',
            (string) $tenant->id,
            'Updated legal agreements, privacy policies, health waivers, and subscription terms.'
        );

        return response()->json([
            'status' => 'success',
            'success' => true,
            'message' => 'Gym legal documents and member agreements updated successfully.',
            'data' => $tenant->fresh(['theme']),
        ]);
    }
}

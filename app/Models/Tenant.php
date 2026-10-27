<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'logo_url',
        'timezone',
        'currency',
        'pricing_path',
        'plan_tier',
        'status',
        'paystack_subaccount_code',
        'enforce_terminal_lock',
        'trial_ends_at',
        'setup_completed_at',
        'is_demo',
        'demo_status',
        'demo_expires_at',
        'demo_purge_after',
        'demo_converted_at',
        'demo_notes',
        'demo_created_by',
    ];

    protected $casts = [
        'enforce_terminal_lock' => 'boolean',
        'trial_ends_at' => 'datetime',
        'setup_completed_at' => 'datetime',
        'is_demo' => 'boolean',
        'demo_expires_at' => 'datetime',
        'demo_purge_after' => 'datetime',
        'demo_converted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });
    }

    public function users()
    {
        return $this->hasMany(TenantUser::class);
    }

    public function license()
    {
        return $this->hasOne(License::class);
    }

    public function features()
    {
        return $this->hasMany(TenantFeature::class);
    }

    public function theme()
    {
        return $this->hasOne(ThemeSetting::class);
    }

    public function pricingPlans()
    {
        return $this->hasMany(PricingPlan::class);
    }

    public function terminals()
    {
        return $this->hasMany(Terminal::class);
    }

    public function cmsContents()
    {
        return $this->hasMany(TenantCmsContent::class);
    }

    public function getCmsSection(string $sectionKey): array
    {
        $defaults = TenantCmsContent::getDefaultSectionContent($sectionKey, $this);
        $record = $this->cmsContents()->where('section_key', $sectionKey)->first();

        if ($record && ! empty($record->content) && is_array($record->content)) {
            // Contact information is edited from several admin screens. Merge
            // saved values over defaults so a partially saved section never
            // hides the remaining public contact fields.
            if ($sectionKey === 'contact_socials') {
                return array_replace($defaults, $record->content);
            }

            return $record->content;
        }

        return $defaults;
    }

    public function hasFeature(string $featureKey): bool
    {
        return $this->features()->where('feature_key', $featureKey)->where('enabled', true)->exists();
    }

    /**
     * Public member portal URL for this tenant.
     * Prefers MEMBER_APP_URL override; otherwise derives {slug}.{PLATFORM_DOMAIN}.
     */
    public function memberPortalUrl(): string
    {
        $base = (string) config('fitcloud.app_urls.member', '');

        if ($base === '') {
            $platform = (string) config('fitcloud.platform_domain', '');
            $base = $platform !== ''
                ? 'https://'.$this->slug.'.'.trim($platform, '.')
                : 'http://localhost:3002';

            return rtrim($base, '/');
        }

        // A custom member-app override may serve multiple tenants from one host.
        return rtrim($base, '/').'?tenant='.$this->slug;
    }

    /**
     * Gym owner admin console URL for this tenant.
     * Prefers ADMIN_APP_URL override; otherwise derives admin.{PLATFORM_DOMAIN}.
     */
    public function adminConsoleUrl(): string
    {
        $base = (string) config('fitcloud.app_urls.admin', '');

        if ($base === '') {
            $platform = (string) config('fitcloud.platform_domain', '');
            $base = $platform !== ''
                ? 'https://admin.'.trim($platform, '.')
                : 'http://localhost:3001';
        }

        return rtrim($base, '/').'?tenant='.$this->slug;
    }
}

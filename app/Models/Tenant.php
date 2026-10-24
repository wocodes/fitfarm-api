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
    ];

    protected $casts = [
        'enforce_terminal_lock' => 'boolean',
        'trial_ends_at' => 'datetime',
        'setup_completed_at' => 'datetime',
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
        $record = $this->cmsContents()->where('section_key', $sectionKey)->first();
        if ($record && ! empty($record->content)) {
            return $record->content;
        }

        return TenantCmsContent::getDefaultSectionContent($sectionKey, $this);
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
        }

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

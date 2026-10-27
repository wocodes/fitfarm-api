<?php

namespace App\Services;

use App\Models\PlatformSetting;

class PlatformSettingsService
{
    public const CONTENT_KEY = 'landing';

    /**
     * Defaults keep the public site working before the first super-admin edit.
     */
    public function defaults(): array
    {
        return [
            'trial_days' => 90,
            'default_currency' => 'NGN',
            'usd_rate' => 1600,
            'landing' => [
                'hero' => [
                    'badge' => 'Optical Access Gates • Smart Passes • Automated Payouts',
                    'title' => 'The Intelligent Cloud OS for',
                    'highlight' => 'Modern Fitness Centers',
                    'subtitle' => 'Unify high-speed access gates, encrypted biometric member entry, automated subscription freeze/resume controls, and automated revenue settlement—all from one branded multi-tenant dashboard.',
                    'primary_cta' => 'Launch Your Gym in 2 Minutes',
                    'secondary_cta' => 'Test Gate Simulator',
                ],
                'stats' => [
                    ['value' => '99.99%', 'label' => 'Uptime SLA'],
                    ['value' => '<35ms', 'label' => 'Access-check latency'],
                    ['value' => '1%', 'label' => 'Platform fee'],
                    ['value' => '24/7', 'label' => 'Audit trail & backups'],
                ],
                'pricing' => [
                    [
                        'id' => 'free',
                        'label' => 'Option 1',
                        'name' => 'Free Setup Tier',
                        'price_ngn' => 0,
                        'price_usd' => 0,
                        'period' => 'upfront cost',
                        'description' => '1% automated transaction split fee. Zero monthly subscription.',
                        'features' => [
                            '$0 Upfront Hardware Setup Cost',
                            'Unlimited Members & Staff Accounts',
                            'Automated 1% Split Payment',
                            'Optical Access Gates & Biometrics Included',
                            'Full Audit Logs & Smart Pass Engine',
                        ],
                        'featured' => true,
                        'cta' => 'Start Free in 2 Minutes',
                    ],
                    [
                        'id' => 'annual',
                        'label' => 'Option 2',
                        'name' => 'Annual Enterprise',
                        'price_ngn' => 450000,
                        'price_usd' => 299,
                        'period' => '/ year',
                        'description' => '0% transaction split fee. Flat annual license key for high-volume facilities.',
                        'features' => [
                            '0% Transaction Split Fee',
                            'Unlimited Locations & Branches',
                            'Priority 24/7 Technical Support',
                            'Custom Domain SSL Configuration',
                            'Dedicated Access Gate Hardware Engineer',
                        ],
                        'featured' => false,
                        'cta' => 'Get Enterprise License',
                    ],
                ],
                'faqs' => [
                    [
                        'q' => 'What is the difference between the Free Setup Tier and Annual Enterprise?',
                        'a' => 'The Free Setup Tier has $0 upfront cost and a 1% transaction split on member subscription payments. Annual Enterprise is a flat annual license with 0% transaction split fee.',
                    ],
                    [
                        'q' => 'Does FitFarmHQ support multiple branches?',
                        'a' => 'Yes. Tenant accounts can manage multiple locations, staff, terminals, and member records from one admin dashboard.',
                    ],
                    [
                        'q' => 'Can each gym have its own branding and member portal?',
                        'a' => 'Yes. Each tenant gets its own branded member portal, colors, logo, plans, and content.',
                    ],
                    [
                        'q' => 'How does member access control work?',
                        'a' => 'Members receive a QR pass and can be issued biometric access. Active plans are validated before an entrance relay is opened.',
                    ],
                ],
            ],
        ];
    }

    public function all(): array
    {
        $stored = PlatformSetting::query()
            ->where('key', self::CONTENT_KEY)
            ->first()?->value;

        $stored = is_array($stored) ? $stored : [];

        return $this->merge($this->defaults(), $stored);
    }

    public function save(array $content): array
    {
        $merged = $this->merge($this->defaults(), $content);

        PlatformSetting::updateOrCreate(
            ['key' => self::CONTENT_KEY],
            ['value' => $merged]
        );

        return $merged;
    }

    /**
     * Merge editable objects while replacing list content wholesale.
     */
    private function merge(array $defaults, array $overrides): array
    {
        $result = $defaults;

        foreach ($overrides as $key => $value) {
            if (in_array($key, ['pricing', 'stats', 'faqs', 'features'], true)) {
                $result[$key] = $value;

                continue;
            }

            if (is_array($value) && isset($result[$key]) && is_array($result[$key]) && ! array_is_list($value)) {
                $result[$key] = $this->merge($result[$key], $value);

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public function trialDays(): int
    {
        return max(1, (int) ($this->all()['trial_days'] ?? 90));
    }

    public function usdRate(): float
    {
        return max(0.0001, (float) ($this->all()['usd_rate'] ?? 1600));
    }

    public function defaultCurrency(): string
    {
        $currency = strtoupper((string) ($this->all()['default_currency'] ?? 'NGN'));

        return in_array($currency, ['NGN', 'USD'], true) ? $currency : 'NGN';
    }

    public function publicPayload(): array
    {
        $content = $this->all();

        return [
            'trial_days' => $content['trial_days'],
            'default_currency' => $content['default_currency'],
            'usd_rate' => $content['usd_rate'],
            'landing' => $content['landing'],
        ];
    }
}

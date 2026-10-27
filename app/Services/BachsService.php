<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Throwable;

class BachsService
{
    private string $secretKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->secretKey = (string) config('fitcloud.bachs.secret_key', '');
        $this->baseUrl = rtrim((string) config('fitcloud.bachs.base_url', 'https://api.bachs.io'), '/');
    }

    public function isConfigured(?Tenant $tenant = null): bool
    {
        return $this->credentialsFor($tenant)[0] !== '';
    }

    /**
     * Demo tenants are always pinned to sandbox credentials. If dedicated
     * demo credentials are not configured, fall back to the platform key only
     * when that key is itself a sandbox key.
     *
     * @return array{0: string, 1: string}
     */
    private function credentialsFor(?Tenant $tenant = null): array
    {
        if (! $tenant?->is_demo) {
            return [$this->secretKey, $this->baseUrl];
        }

        $key = (string) config('fitcloud.bachs.demo_secret_key', '');
        $base = rtrim((string) config('fitcloud.bachs.demo_base_url', 'https://sandbox-api.bachs.io'), '/');

        if ($key === '' && str_starts_with($this->secretKey, 'sk_sandbox_')) {
            $key = $this->secretKey;
            $base = $this->baseUrl;
        }

        return [$key, $base];
    }

    /**
     * Create a hosted Bachs checkout for a pending subscription transaction.
     */
    public function createCheckout(Transaction $transaction, Member $member, Tenant $tenant): array
    {
        [$secretKey, $baseUrl] = $this->credentialsFor($tenant);

        if ($secretKey === '') {
            return [
                'success' => false,
                'message' => $tenant->is_demo
                    ? 'Demo payment testing is not configured. Add sandbox Bachs credentials.'
                    : 'Online payments are not configured.',
            ];
        }

        $portalUrl = $tenant->memberPortalUrl();
        $cancelUrl = $portalUrl.(str_contains($portalUrl, '?') ? '&' : '?').'payment=cancelled';

        $payload = [
            'pricing' => [
                'currency' => $transaction->currency ?: 'NGN',
                'amount' => number_format((float) $transaction->amount, 2, '.', ''),
            ],
            'customer' => [
                'email' => $member->email,
                'name' => trim($member->first_name.' '.$member->last_name),
            ],
            'success_url' => $portalUrl,
            'cancel_url' => $cancelUrl,
            'reference' => $transaction->reference,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_slug' => $tenant->slug,
                'member_id' => (string) $member->id,
                'transaction_id' => (string) $transaction->id,
                'pricing_plan_id' => (string) $transaction->pricing_plan_id,
            ],
        ];

        try {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->timeout(20)
                ->post($baseUrl.'/v1/checkout-sessions', $payload);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'success' => false,
                'message' => 'Could not start the payment checkout. Please try again.',
            ];
        }

        if (! $response->successful() || ! $response->json('checkout_url')) {
            return [
                'success' => false,
                'message' => $response->json('detail')
                    ?? $response->json('message')
                    ?? 'Could not start the payment checkout.',
            ];
        }

        return [
            'success' => true,
            'checkout_id' => $response->json('checkout_id'),
            'checkout_url' => $response->json('checkout_url'),
            'reference' => $response->json('reference', $transaction->reference),
        ];
    }

    /**
     * Retrieve a checkout session from Bachs.
     */
    public function getCheckout(string $checkoutId, ?Tenant $tenant = null): array
    {
        [$secretKey, $baseUrl] = $this->credentialsFor($tenant);

        if ($secretKey === '') {
            return [
                'success' => false,
                'message' => $tenant?->is_demo
                    ? 'Demo payment testing is not configured. Add sandbox Bachs credentials.'
                    : 'Online payments are not configured.',
            ];
        }

        try {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->timeout(20)
                ->get($baseUrl.'/v1/checkout-sessions/'.$checkoutId);
        } catch (Throwable $exception) {
            report($exception);

            return [
                'success' => false,
                'message' => 'Could not verify the payment checkout.',
            ];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => $response->json('detail')
                    ?? $response->json('message')
                    ?? 'Could not verify the payment checkout.',
            ];
        }

        return [
            'success' => true,
            'data' => $response->json('data', $response->json()),
        ];
    }

    public function webhookSecret(): string
    {
        return (string) config('fitcloud.bachs.webhook_secret', '');
    }

    /**
     * Accept both live and demo webhook signatures so sandbox demo payments
     * can be completed while production remains on live credentials.
     *
     * @return array<int, string>
     */
    public function webhookSecrets(): array
    {
        return array_values(array_unique(array_filter([
            (string) config('fitcloud.bachs.webhook_secret', ''),
            (string) config('fitcloud.bachs.demo_webhook_secret', ''),
        ])));
    }
}

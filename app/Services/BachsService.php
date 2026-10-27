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

    public function isConfigured(): bool
    {
        return $this->secretKey !== '';
    }

    /**
     * Create a hosted Bachs checkout for a pending subscription transaction.
     */
    public function createCheckout(Transaction $transaction, Member $member, Tenant $tenant): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Online payments are not configured.',
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
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->timeout(20)
                ->post($this->baseUrl.'/v1/checkout-sessions', $payload);
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
    public function getCheckout(string $checkoutId): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Online payments are not configured.',
            ];
        }

        try {
            $response = Http::withToken($this->secretKey)
                ->acceptJson()
                ->timeout(20)
                ->get($this->baseUrl.'/v1/checkout-sessions/'.$checkoutId);
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
}

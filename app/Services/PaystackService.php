<?php

namespace App\Services;

use App\Models\Member;
use App\Models\PricingPlan;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PaystackService
{
    protected string $secretKey;

    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('fitcloud.paystack.secret_key', '');
    }

    /**
     * Create a Paystack Subaccount for a gym tenant with a 1% fee split
     */
    public function createSubaccount(Tenant $tenant, string $businessName, string $settlementBank, string $accountNumber): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/subaccount", [
                'business_name' => $businessName,
                'settlement_bank' => $settlementBank,
                'account_number' => $accountNumber,
                'percentage_charge' => config('fitcloud.platform_fee_percentage', 1.0), // 1%
                'primary_contact_email' => $tenant->users()->first()?->email ?? 'billing@fitcloud.io',
            ]);

        if ($response->successful() && $response->json('status')) {
            $subaccountCode = $response->json('data.subaccount_code');
            $tenant->update(['paystack_subaccount_code' => $subaccountCode]);

            return [
                'success' => true,
                'subaccount_code' => $subaccountCode,
                'data' => $response->json('data'),
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Failed to create Paystack subaccount.',
        ];
    }

    /**
     * Initialize payment transaction for member subscription with automatic 1% split
     */
    public function initializeTransaction(Member $member, PricingPlan $plan, Tenant $tenant): array
    {
        $reference = 'FC-TX-'.strtoupper(Str::random(10));
        $amountInKobo = (int) ($plan->price * 100);

        // Platform fee calculation (1%)
        $platformFee = $tenant->pricing_path === 'free_tier'
            ? ($plan->price * (config('fitcloud.platform_fee_percentage', 1.0) / 100))
            : 0.00;

        $netAmount = $plan->price - $platformFee;

        // Record pending transaction
        $transaction = Transaction::create([
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'pricing_plan_id' => $plan->id,
            'amount' => $plan->price,
            'platform_fee' => $platformFee,
            'net_amount' => $netAmount,
            'currency' => $tenant->currency,
            'payment_method' => 'paystack',
            'reference' => $reference,
            'status' => 'pending',
        ]);

        $callbackUrl = $tenant->memberPortalUrl();

        $payload = [
            'email' => $member->email ?? "member{$member->id}@example.com",
            'amount' => $amountInKobo,
            'reference' => $reference,
            'callback_url' => "{$callbackUrl}&payment=success",
            'metadata' => [
                'tenant_uuid' => $tenant->uuid,
                'member_uuid' => $member->uuid,
                'pricing_plan_id' => $plan->id,
                'transaction_id' => $transaction->id,
            ],
        ];

        // Attach subaccount split if tenant is on free_tier with Paystack subaccount
        if ($tenant->pricing_path === 'free_tier' && $tenant->paystack_subaccount_code) {
            $payload['subaccount'] = $tenant->paystack_subaccount_code;
        }

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        if ($response->successful() && $response->json('status')) {
            return [
                'success' => true,
                'authorization_url' => $response->json('data.authorization_url'),
                'reference' => $reference,
                'transaction' => $transaction,
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Failed to initialize Paystack transaction.',
        ];
    }

    /**
     * Verify a transaction reference with Paystack.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if ($response->successful() && $response->json('status')) {
            return [
                'success' => true,
                'status' => $response->json('data.status'),
                'amount' => $response->json('data.amount'),
                'reference' => $response->json('data.reference'),
                'authorization' => $response->json('data.authorization'),
                'data' => $response->json('data'),
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('message') ?? 'Unable to verify Paystack transaction.',
        ];
    }
}

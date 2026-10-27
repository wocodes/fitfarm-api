<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\PricingPlan;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BachsPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Member $member;

    private PricingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Bachs Test Gym',
            'slug' => 'bachs-test-gym',
            'status' => 'active',
            'currency' => 'NGN',
        ]);

        $this->member = Member::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'Test',
            'last_name' => 'Member',
            'email' => 'bachs-member@example.com',
            'phone' => '08000000000',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->plan = PricingPlan::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Monthly',
            'price' => 10000,
            'duration_days' => 30,
            'is_active' => true,
        ]);

        config([
            'fitcloud.bachs.secret_key' => 'sk_sandbox_test',
            'fitcloud.bachs.base_url' => 'https://api.bachs.io',
            'fitcloud.bachs.webhook_secret' => 'whsec_test',
        ]);
    }

    public function test_initializes_a_bachs_checkout(): void
    {
        Http::fake([
            'api.bachs.io/v1/checkout-sessions' => Http::response([
                'checkout_id' => 'chk_test_123',
                'checkout_url' => 'https://checkout.bachs.io/c/test',
                'status' => 'open',
                'reference' => 'FFH-TEST',
            ], 201),
        ]);

        $token = $this->member->createToken('member-portal-token', ['member'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/member/plans/initialize', [
                'pricing_plan_id' => $this->plan->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checkout_url', 'https://checkout.bachs.io/c/test');

        $this->assertDatabaseHas('transactions', [
            'member_id' => $this->member->id,
            'provider_checkout_id' => 'chk_test_123',
            'payment_method' => 'bachs',
            'status' => 'pending',
        ]);
    }

    public function test_confirms_a_completed_bachs_checkout(): void
    {
        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'pricing_plan_id' => $this->plan->id,
            'amount' => 10000,
            'platform_fee' => 0,
            'net_amount' => 10000,
            'currency' => 'NGN',
            'payment_method' => 'bachs',
            'reference' => 'FFH-CONFIRM-TEST',
            'provider_checkout_id' => 'chk_confirm_test',
            'status' => 'pending',
        ]);

        Http::fake([
            'api.bachs.io/v1/checkout-sessions/chk_confirm_test' => Http::response([
                'id' => 'chk_confirm_test',
                'status' => 'completed',
            ], 200),
        ]);

        $token = $this->member->createToken('member-portal-token', ['member'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/member/plans/purchase', [
                'checkout_id' => 'chk_confirm_test',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'successful',
        ]);

        $this->assertDatabaseHas('member_plans', [
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'status' => 'active',
        ]);
    }

    public function test_handles_signed_bachs_webhook_once(): void
    {
        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'pricing_plan_id' => $this->plan->id,
            'amount' => 10000,
            'platform_fee' => 0,
            'net_amount' => 10000,
            'currency' => 'NGN',
            'payment_method' => 'bachs',
            'reference' => 'FFH-WEBHOOK-TEST',
            'provider_checkout_id' => 'chk_webhook_test',
            'status' => 'pending',
        ]);

        $payload = [
            'id' => 'evt_test_1',
            'type' => 'collection.succeeded',
            'data' => [
                'checkout_id' => 'chk_webhook_test',
                'reference' => 'FFH-WEBHOOK-TEST',
                'status' => 'SUCCEEDED',
                'amount' => '10000.00',
                'currency' => 'NGN',
            ],
        ];

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.json_encode($payload), 'whsec_test');

        $send = fn () => $this->withHeaders([
            'X-Bachs-Timestamp' => $timestamp,
            'X-Bachs-Signature-V2' => 't='.$timestamp.',v1='.$signature,
        ])->postJson('/api/v1/webhooks/bachs', $payload);

        $send()->assertStatus(200);
        $send()->assertStatus(200);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'successful',
        ]);

        $this->assertDatabaseCount('member_plans', 1);
    }
}

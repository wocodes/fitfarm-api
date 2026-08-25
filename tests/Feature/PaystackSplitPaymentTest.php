<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\PricingPlan;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaystackSplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Member $member;
    protected PricingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'PolanFit Arena',
            'slug' => 'polanfit-arena',
            'pricing_path' => 'free_tier',
            'status' => 'active',
            'paystack_subaccount_code' => 'ACCT_DEMO12345',
        ]);

        app()->instance('tenant', $this->tenant);

        $this->member = Member::create([
            'first_name' => 'Kelechi',
            'last_name' => 'Oshoname',
            'email' => 'kelechi@gmail.com',
            'status' => 'inactive',
        ]);

        $this->plan = PricingPlan::create([
            'name' => 'Monthly Standard',
            'price' => 10000.00,
            'duration_days' => 30,
            'is_active' => true,
        ]);
    }

    public function test_calculates_platform_fee_split_correctly(): void
    {
        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'pricing_plan_id' => $this->plan->id,
            'amount' => 10000.00,
            'platform_fee' => 100.00, // 1% of 10,000
            'net_amount' => 9900.00,   // 99% to gym
            'currency' => 'NGN',
            'payment_method' => 'paystack',
            'reference' => 'FC-TX-TEST12345',
            'status' => 'pending',
        ]);

        $this->assertEquals(100.00, $transaction->platform_fee);
        $this->assertEquals(9900.00, $transaction->net_amount);
    }

    public function test_handles_paystack_successful_webhook(): void
    {
        $transaction = Transaction::create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'pricing_plan_id' => $this->plan->id,
            'amount' => 10000.00,
            'platform_fee' => 100.00,
            'net_amount' => 9900.00,
            'currency' => 'NGN',
            'payment_method' => 'paystack',
            'reference' => 'FC-TX-WEBHOOK123',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'FC-TX-WEBHOOK123',
                'amount' => 1000000, // In kobo
                'status' => 'success',
            ]
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('transactions', [
            'reference' => 'FC-TX-WEBHOOK123',
            'status' => 'successful',
        ]);

        $this->assertDatabaseHas('members', [
            'id' => $this->member->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('member_plans', [
            'member_id' => $this->member->id,
            'status' => 'active',
        ]);
    }
}

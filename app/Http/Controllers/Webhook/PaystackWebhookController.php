<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    /**
     * Handle incoming Paystack Webhooks
     */
    public function handle(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $secretKey = config('fitcloud.paystack.secret_key', '');

        // Verify webhook signature if secret key is configured
        if ($secretKey && $signature !== hash_hmac('sha512', $request->getContent(), $secretKey)) {
            Log::warning('Paystack webhook signature verification failed.');
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.success') {
            $reference = $data['reference'] ?? null;
            $metadata = $data['metadata'] ?? [];

            $transaction = Transaction::where('reference', $reference)->first();

            if ($transaction) {
                // 1. Mark transaction as successful
                $transaction->update(['status' => 'successful']);

                // 2. Activate or extend member subscription
                if ($transaction->pricing_plan_id && $transaction->member_id) {
                    $plan = PricingPlan::find($transaction->pricing_plan_id);
                    $durationDays = $plan ? $plan->duration_days : 30;

                    MemberPlan::create([
                        'tenant_id' => $transaction->tenant_id,
                        'member_id' => $transaction->member_id,
                        'pricing_plan_id' => $transaction->pricing_plan_id,
                        'starts_at' => Carbon::now(),
                        'expires_at' => Carbon::now()->addDays($durationDays),
                        'status' => 'active',
                    ]);

                    // Ensure member status is active
                    Member::where('id', $transaction->member_id)->update(['status' => 'active']);
                }
            }
        }

        return response()->json(['status' => 'success']);
    }
}

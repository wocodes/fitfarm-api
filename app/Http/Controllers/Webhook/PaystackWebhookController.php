<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    /**
     * Handle incoming Paystack Webhooks
     */
    public function handle(Request $request)
    {
        $signature = (string) $request->header('x-paystack-signature');
        $secretKey = (string) config('fitcloud.paystack.secret_key', '');

        if ($secretKey === '') {
            Log::error('Paystack webhook rejected because PAYSTACK_SECRET_KEY is not configured.');

            return response()->json(['message' => 'Webhook is not configured'], 503);
        }

        $expectedSignature = hash_hmac('sha512', $request->getContent(), $secretKey);

        if ($signature === '' || ! hash_equals($expectedSignature, $signature)) {
            Log::warning('Paystack webhook signature verification failed.');

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $data = $request->input('data', []);

        if ($event !== 'charge.success') {
            return response()->json(['status' => 'ignored']);
        }

        $reference = $data['reference'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return response()->json(['message' => 'Missing transaction reference'], 422);
        }

        $processed = DB::transaction(function () use ($reference, $data) {
            $transaction = Transaction::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                Log::warning('Paystack webhook received for an unknown transaction.', [
                    'reference' => $reference,
                ]);

                return false;
            }

            if ($transaction->status === 'successful') {
                return true;
            }

            $expectedAmount = (int) round(((float) $transaction->amount) * 100);

            if ((int) ($data['amount'] ?? 0) !== $expectedAmount) {
                Log::error('Paystack webhook amount mismatch.', [
                    'reference' => $reference,
                    'expected' => $expectedAmount,
                    'received' => $data['amount'] ?? null,
                ]);

                return false;
            }

            $transaction->update(['status' => 'successful']);

            if (! $transaction->pricing_plan_id || ! $transaction->member_id) {
                return true;
            }

            $plan = PricingPlan::where('tenant_id', $transaction->tenant_id)
                ->whereKey($transaction->pricing_plan_id)
                ->first();

            if (! $plan) {
                return true;
            }

            $alreadyActive = MemberPlan::where('tenant_id', $transaction->tenant_id)
                ->where('member_id', $transaction->member_id)
                ->where('pricing_plan_id', $plan->id)
                ->where('status', 'active')
                ->exists();

            if (! $alreadyActive) {
                MemberPlan::create([
                    'tenant_id' => $transaction->tenant_id,
                    'member_id' => $transaction->member_id,
                    'pricing_plan_id' => $plan->id,
                    'starts_at' => Carbon::now(),
                    'expires_at' => Carbon::now()->addDays($plan->duration_days ?: 30),
                    'status' => 'active',
                ]);
            }

            Member::whereKey($transaction->member_id)->update(['status' => 'active']);

            return true;
        });

        if (! $processed) {
            return response()->json(['message' => 'Transaction could not be processed'], 422);
        }

        return response()->json(['status' => 'success']);
    }
}

<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\BachsService;
use App\Services\MembershipActivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BachsWebhookController extends Controller
{
    public function handle(Request $request, BachsService $bachs, MembershipActivationService $activation)
    {
        $secret = $bachs->webhookSecret();

        if ($secret === '') {
            Log::error('Bachs webhook rejected because BACHS_WEBHOOK_SECRET is not configured.');

            return response()->json(['message' => 'Webhook is not configured'], 503);
        }

        $rawBody = $request->getContent();
        $timestamp = (string) $request->header('X-Bachs-Timestamp', '');
        $v2 = (string) $request->header('X-Bachs-Signature-V2', '');
        $v1 = (string) $request->header('X-Bachs-Signature', '');

        if (! $this->hasValidSignature($rawBody, $timestamp, $v2, $v1, $secret)) {
            Log::warning('Bachs webhook signature verification failed.');

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->json()->all();
        $type = (string) ($event['type'] ?? '');
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];

        if ($type !== 'collection.succeeded') {
            return response()->json(['status' => 'ignored']);
        }

        if (strtoupper((string) ($data['status'] ?? '')) !== 'SUCCEEDED') {
            return response()->json(['status' => 'ignored']);
        }

        $transaction = null;
        $checkoutId = $data['checkout_id'] ?? null;
        $reference = $data['reference'] ?? null;

        if (is_string($checkoutId) && $checkoutId !== '') {
            $transaction = Transaction::query()
                ->where('provider_checkout_id', $checkoutId)
                ->first();
        }

        if (! $transaction && is_string($reference) && $reference !== '') {
            $transaction = Transaction::query()
                ->where('reference', $reference)
                ->first();
        }

        if (! $transaction) {
            Log::warning('Bachs webhook received for an unknown checkout.', [
                'checkout_id' => $checkoutId,
                'reference' => $reference,
            ]);

            return response()->json(['status' => 'ignored']);
        }

        if ($transaction->status === 'successful') {
            return response()->json(['status' => 'success']);
        }

        $paidAmount = $data['amount'] ?? null;

        if ($paidAmount !== null
            && abs((float) $paidAmount - (float) $transaction->amount) > 0.009) {
            Log::error('Bachs webhook amount mismatch.', [
                'transaction_id' => $transaction->id,
                'expected' => (float) $transaction->amount,
                'received' => $paidAmount,
            ]);

            return response()->json(['message' => 'Amount mismatch'], 422);
        }

        $memberPlan = $activation->activateFromTransaction($transaction);

        if (! $memberPlan) {
            Log::error('Bachs webhook could not activate membership.', [
                'transaction_id' => $transaction->id,
            ]);

            return response()->json(['message' => 'Could not apply payment'], 500);
        }

        return response()->json(['status' => 'success']);
    }

    private function hasValidSignature(
        string $rawBody,
        string $timestamp,
        string $v2Header,
        string $v1Header,
        string $secret
    ): bool {
        if ($timestamp === '' || ! ctype_digit($timestamp)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        if ($v2Header !== '') {
            foreach (explode(',', $v2Header) as $part) {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                if ($key === 'v1' && is_string($value) && hash_equals($expected, $value)) {
                    return true;
                }
            }
        }

        return $v1Header !== '' && hash_equals($expected, $v1Header);
    }
}

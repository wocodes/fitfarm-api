<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberPlan;
use App\Models\PricingPlan;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MembershipActivationService
{
    /**
     * Mark a pending transaction successful and create/extend the member plan.
     * Safe to call more than once for the same transaction.
     */
    public function activateFromTransaction(Transaction $transaction): ?MemberPlan
    {
        return DB::transaction(function () use ($transaction) {
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return null;
            }

            if ($locked->status === 'successful') {
                return MemberPlan::query()
                    ->where('tenant_id', $locked->tenant_id)
                    ->where('member_id', $locked->member_id)
                    ->where('status', 'active')
                    ->latest('id')
                    ->first();
            }

            if ($locked->status !== 'pending') {
                return null;
            }

            $plan = PricingPlan::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereKey($locked->pricing_plan_id)
                ->first();

            if (! $plan) {
                return null;
            }

            $locked->update(['status' => 'successful']);

            $existing = MemberPlan::query()
                ->where('tenant_id', $locked->tenant_id)
                ->where('member_id', $locked->member_id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->latest('expires_at')
                ->first();

            $startsAt = $existing?->expires_at ?? Carbon::now();
            $expiresAt = (clone $startsAt)->addDays($plan->duration_days ?: 30);

            $memberPlan = MemberPlan::create([
                'tenant_id' => $locked->tenant_id,
                'member_id' => $locked->member_id,
                'pricing_plan_id' => $plan->id,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            Member::query()
                ->whereKey($locked->member_id)
                ->update(['status' => 'active']);

            return $memberPlan;
        });
    }
}

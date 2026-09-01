<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'member_id',
        'pricing_plan_id',
        'amount',
        'platform_fee',
        'net_amount',
        'currency',
        'payment_method',
        'reference',
        'status',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = (string) Str::uuid();
            }
        });
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function pricingPlan()
    {
        return $this->belongsTo(PricingPlan::class);
    }
}

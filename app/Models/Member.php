<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'gender',
        'date_of_birth',
        'address',
        'photo_url',
        'qr_code',
        'fingerprint_template',
        'blood_group',
        'genotype',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'occupation',
        'nin',
        'blood_pressure',
        'medical_conditions',
        'other_ailments',
        'hmo_provider',
        'hmo_number',
        'next_of_kin_name',
        'next_of_kin_phone',
        'next_of_kin_relationship',
        'next_of_kin_address',
        'status',
        'registration_source',
        'registration_status',
        'biometric_hash',
        'registration_expiry_date',
    ];

    protected static function booted()
    {
        static::creating(function ($member) {
            if (empty($member->uuid)) {
                $member->uuid = (string) Str::uuid();
            }
        });
    }

    protected $hidden = [
        'password',
        'remember_token',
        'fingerprint_template',
        'biometric_hash',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function activePlan()
    {
        return $this->hasOne(MemberPlan::class)->where('status', 'active')->latestOfMany();
    }

    public function latestPlan()
    {
        return $this->hasOne(MemberPlan::class)->latestOfMany();
    }

    public function activePaidPlans()
    {
        return $this->hasMany(MemberPlan::class)
            ->whereIn('status', ['active', 'paused'])
            ->whereHas('pricingPlan', function ($q) {
                $q->whereNotIn(DB::raw('LOWER(name)'), ['registration', 'system-rg', 'system-fr', 'registration fee', 'system']);
            });
    }

    public function activePaidPlan()
    {
        return $this->hasOne(MemberPlan::class)
            ->ofMany(['id' => 'max'], function ($q) {
                $q->where('status', 'active')
                    ->whereHas('pricingPlan', function ($pq) {
                        $pq->whereNotIn(DB::raw('LOWER(name)'), ['registration', 'system-rg', 'system-fr', 'registration fee', 'system']);
                    });
            });
    }

    public function latestPaidPlan()
    {
        return $this->hasOne(MemberPlan::class)
            ->ofMany(['id' => 'max'], function ($q) {
                $q->whereHas('pricingPlan', function ($pq) {
                    $pq->whereNotIn(DB::raw('LOWER(name)'), ['registration', 'system-rg', 'system-fr', 'registration fee', 'system']);
                });
            });
    }

    public function registrationPlan()
    {
        return $this->hasOne(MemberPlan::class)
            ->ofMany(['id' => 'max'], function ($q) {
                $q->whereHas('pricingPlan', function ($pq) {
                    $pq->whereIn(DB::raw('LOWER(name)'), ['registration', 'system-rg', 'system-fr', 'registration fee']);
                });
            });
    }

    public function plans()
    {
        return $this->hasMany(MemberPlan::class);
    }

    public function checkIns()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function getFormattedDisplayNameAttribute(): string
    {
        $lastInitial = $this->last_name ? ' '.mb_substr($this->last_name, 0, 1).'.' : '';

        return $this->first_name.$lastInitial;
    }
}

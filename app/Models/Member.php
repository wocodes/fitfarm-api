<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'phone',
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
        'status',
    ];

    protected static function booted()
    {
        static::creating(function ($member) {
            if (empty($member->uuid)) {
                $member->uuid = (string) Str::uuid();
            }
        });
    }

    public function activePlan()
    {
        return $this->hasOne(MemberPlan::class)->where('status', 'active')->latestOfMany();
    }

    public function plans()
    {
        return $this->hasMany(MemberPlan::class);
    }

    public function checkIns()
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function getFormattedDisplayNameAttribute(): string
    {
        $lastInitial = $this->last_name ? ' ' . mb_substr($this->last_name, 0, 1) . '.' : '';
        return $this->first_name . $lastInitial;
    }
}

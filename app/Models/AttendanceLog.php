<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'check_in_time',
        'check_out_time',
        'method',
        'is_active_session',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'is_active_session' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}

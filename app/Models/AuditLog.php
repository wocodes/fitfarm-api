<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'user_name',
        'action',
        'target_type',
        'target_id',
        'description',
        'previous_state',
        'new_state',
    ];

    protected $casts = [
        'previous_state' => 'array',
        'new_state' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(TenantUser::class, 'user_id');
    }
}

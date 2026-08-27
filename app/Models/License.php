<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'user_email',
        'tenant_id',
        'mode',
        'plan_tier',
        'allowed_features',
        'max_members',
        'is_active',
        'activated_at',
        'expires_at',
    ];

    protected $casts = [
        'allowed_features' => 'array',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantLifecycleLog extends Model
{
    protected $fillable = [
        'tenant_uuid',
        'gym_name',
        'slug',
        'owner_name',
        'owner_email',
        'action',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}

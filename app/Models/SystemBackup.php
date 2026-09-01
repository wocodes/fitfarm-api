<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemBackup extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'filename',
        'file_size',
        'type',
        'status',
        'record_count',
        'tables_backed_up',
        'checksum',
        'created_by',
    ];

    protected $casts = [
        'tables_backed_up' => 'array',
        'file_size' => 'integer',
        'record_count' => 'integer',
    ];
}

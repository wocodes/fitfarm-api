<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GymLocation extends Model
{
    use HasFactory;

    protected $table = 'gym_locations';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'radius',
        'active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius' => 'float',
        'active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}

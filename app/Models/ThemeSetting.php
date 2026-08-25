<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThemeSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'primary_color',
        'accent_color',
        'bg_color',
        'text_color',
        'font_family',
        'dark_mode',
        'logo_url',
        'favicon_url',
    ];

    protected $casts = [
        'dark_mode' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}

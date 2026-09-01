<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingPlan extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'plan_name',
        'category',
        'description',
        'price',
        'duration_days',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = ['formatted_name', 'filter_label'];

    public function getFormattedNameAttribute()
    {
        $category = strtoupper($this->category ?? '');
        $name = $this->name ?? '';

        if (in_array($category, ['EXCLUSIVE', 'GENERIC'])) {
            $formattedCategory = ucfirst(strtolower($category));
            if (str_contains(strtolower($name), strtolower($category))) {
                return $name;
            }
            return $formattedCategory . ' ' . $name;
        }

        return $name;
    }

    public function getFilterLabelAttribute()
    {
        $category = strtoupper($this->category ?? 'SUBSCRIPTION');
        $name = $this->name ?? '';
        return "{$category} - {$name}";
    }
}

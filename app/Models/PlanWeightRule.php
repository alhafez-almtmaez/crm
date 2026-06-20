<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanWeightRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'pattern',
        'keyword',
        'weight',
        'is_standalone',
        'min_pages',
        'max_pages',
        'priority',
        'is_active',
        'classification',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'is_standalone' => 'bool',
        'min_pages' => 'int',
        'max_pages' => 'int',
        'priority' => 'int',
        'is_active' => 'bool',
    ];

    public function planPoints(): HasMany
    {
        return $this->hasMany(PlanPoint::class, 'plan_weight_rule_id');
    }
}

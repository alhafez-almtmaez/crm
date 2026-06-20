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
        'weight',
        'is_standalone',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'is_standalone' => 'bool',
        'is_active' => 'bool',
    ];

    public function planPoints(): HasMany
    {
        return $this->hasMany(PlanPoint::class, 'plan_weight_rule_id');
    }
}

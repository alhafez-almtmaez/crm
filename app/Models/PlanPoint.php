<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'sort_order',
        'name',
        'points',
        'weight',
        'is_standalone',
        'requires_certificate',
        'surah_name',
        'part_name',
        'three_parts',
    ];

    protected $casts = [
        'plan_id' => 'int',
        'sort_order' => 'int',
        'points' => 'int',
        'weight' => 'decimal:2',
        'is_standalone' => 'bool',
        'requires_certificate' => 'bool',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }
}

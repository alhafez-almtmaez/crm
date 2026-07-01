<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'year',
        'center_id',
        'group_id',
        'admin_id',
        'students_count',
        'generated_items_count',
        'skipped_items_count',
        'generated_at',
    ];

    protected $casts = [
        'month' => 'int',
        'year' => 'int',
        'center_id' => 'int',
        'group_id' => 'int',
        'admin_id' => 'int',
        'students_count' => 'int',
        'generated_items_count' => 'int',
        'skipped_items_count' => 'int',
        'generated_at' => 'datetime',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function studentPlans(): HasMany
    {
        return $this->hasMany(StudentMonthlyPlan::class);
    }
}

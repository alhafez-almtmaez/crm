<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentMonthlyPlanDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_monthly_plan_id',
        'date',
        'day_number',
        'total_weight',
    ];

    protected $casts = [
        'student_monthly_plan_id' => 'int',
        'date' => 'date',
        'day_number' => 'int',
        'total_weight' => 'decimal:2',
    ];

    public function monthlyPlan(): BelongsTo
    {
        return $this->belongsTo(StudentMonthlyPlan::class, 'student_monthly_plan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentMonthlyPlanItem::class, 'student_monthly_plan_day_id');
    }
}

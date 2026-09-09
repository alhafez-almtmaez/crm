<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMonthlyPlanTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_monthly_plan_id',
        'effective_date',
        'plan_id',
        'starts_after_plan_point_id',
    ];

    protected $casts = [
        'student_monthly_plan_id' => 'int',
        'effective_date' => 'immutable_date',
        'plan_id' => 'int',
        'starts_after_plan_point_id' => 'int',
    ];

    public function studentMonthlyPlan(): BelongsTo
    {
        return $this->belongsTo(StudentMonthlyPlan::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function startsAfterPlanPoint(): BelongsTo
    {
        return $this->belongsTo(PlanPoint::class, 'starts_after_plan_point_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMonthlyPlanItem extends Model
{
    use HasFactory;

    public const STATUS_GENERATED = 'generated';

    public const STATUS_ATTACHED = 'attached';

    public const STATUS_SPECIAL = 'special';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'student_monthly_plan_id',
        'student_monthly_plan_day_id',
        'student_id',
        'plan_point_id',
        'sort_order',
        'weight',
        'is_standalone',
        'status',
    ];

    protected $casts = [
        'student_monthly_plan_id' => 'int',
        'student_monthly_plan_day_id' => 'int',
        'student_id' => 'int',
        'plan_point_id' => 'int',
        'sort_order' => 'int',
        'weight' => 'decimal:2',
        'is_standalone' => 'bool',
    ];

    public function monthlyPlan(): BelongsTo
    {
        return $this->belongsTo(StudentMonthlyPlan::class, 'student_monthly_plan_id');
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(StudentMonthlyPlanDay::class, 'student_monthly_plan_day_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function planPoint(): BelongsTo
    {
        return $this->belongsTo(PlanPoint::class);
    }
}

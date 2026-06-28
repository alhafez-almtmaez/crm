<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentMonthlyPlan extends Model
{
    use HasFactory;

    public const STATUS_GENERATED = 'generated';

    public const STATUS_EXHAUSTED = 'exhausted';

    protected $fillable = [
        'student_id',
        'center_id',
        'group_id',
        'plan_id',
        'month',
        'year',
        'max_daily_weight',
        'starts_after_plan_point_id',
        'ends_at_plan_point_id',
        'generated_items_count',
        'skipped_items_count',
        'status',
        'generated_at',
    ];

    protected $casts = [
        'student_id' => 'int',
        'center_id' => 'int',
        'group_id' => 'int',
        'plan_id' => 'int',
        'month' => 'int',
        'year' => 'int',
        'max_daily_weight' => 'int',
        'starts_after_plan_point_id' => 'int',
        'ends_at_plan_point_id' => 'int',
        'generated_items_count' => 'int',
        'skipped_items_count' => 'int',
        'generated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function startsAfterPlanPoint(): BelongsTo
    {
        return $this->belongsTo(PlanPoint::class, 'starts_after_plan_point_id');
    }

    public function endsAtPlanPoint(): BelongsTo
    {
        return $this->belongsTo(PlanPoint::class, 'ends_at_plan_point_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(StudentMonthlyPlanDay::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StudentMonthlyPlanItem::class);
    }
}

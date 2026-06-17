<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeworkStudent extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_id',
        'student_id',
        'plan_id',
        'current_plan_point_id',
        'points_balance_before',
        'points_balance_after',
    ];

    protected $casts = [
        'homework_id' => 'int',
        'student_id' => 'int',
        'plan_id' => 'int',
        'current_plan_point_id' => 'int',
        'points_balance_before' => 'int',
        'points_balance_after' => 'int',
    ];

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function currentPlanPoint(): BelongsTo
    {
        return $this->belongsTo(PlanPoint::class, 'current_plan_point_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(HomeworkStudentPoint::class);
    }
}

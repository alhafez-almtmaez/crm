<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkStudentPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'homework_student_id',
        'homework_id',
        'student_id',
        'plan_point_id',
        'sort_order',
        'is_done',
        'is_next_homework',
        'awarded_points',
        'awarded_at',
    ];

    protected $casts = [
        'homework_student_id' => 'int',
        'homework_id' => 'int',
        'student_id' => 'int',
        'plan_point_id' => 'int',
        'sort_order' => 'int',
        'is_done' => 'bool',
        'is_next_homework' => 'bool',
        'awarded_points' => 'int',
        'awarded_at' => 'datetime',
    ];

    public function homeworkStudent(): BelongsTo
    {
        return $this->belongsTo(HomeworkStudent::class);
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
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

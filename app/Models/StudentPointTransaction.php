<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPointTransaction extends Model
{
    use HasFactory;

    public const TYPE_HOMEWORK_COMPLETED = 'homework_completed';

    public const TYPE_HOMEWORK_MANUAL_ADJUSTMENT = 'homework_manual_adjustment';

    protected $fillable = [
        'student_id',
        'homework_id',
        'homework_student_point_id',
        'plan_point_id',
        'type',
        'points',
        'balance_before',
        'balance_after',
        'created_by',
    ];

    protected $casts = [
        'student_id' => 'int',
        'homework_id' => 'int',
        'homework_student_point_id' => 'int',
        'plan_point_id' => 'int',
        'points' => 'int',
        'balance_before' => 'int',
        'balance_after' => 'int',
        'created_by' => 'int',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function homeworkStudentPoint(): BelongsTo
    {
        return $this->belongsTo(HomeworkStudentPoint::class);
    }

    public function planPoint(): BelongsTo
    {
        return $this->belongsTo(PlanPoint::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

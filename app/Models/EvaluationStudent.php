<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationStudent extends Model
{
    public const ATTENDANCE_PRESENT = 1;
    public const ATTENDANCE_EXCUSED_ABSENCE = 2;
    public const ATTENDANCE_ABSENCE = 3;

    protected $table = 'evaluations_users';

    protected $fillable = [
        'alhifz',
        'warud',
        'akhlaqi',
        'note',
        'attendances',
        'student_id',
        'user_id',
        'evaluation_id',
    ];

    protected $casts = [
        'attendances' => 'int',
        'student_id' => 'int',
        'user_id' => 'int',
        'evaluation_id' => 'int',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function resolvedStudentId(): ?int
    {
        return $this->student_id ?? $this->user_id;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceRuleExecutionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'evaluation_student_id',
        'student_id',
        'center_id',
        'absence_rule_id',
        'message_template_id',
        'attendance_type',
        'attendance_value',
        'occurrence_number',
        'action',
        'recipient_phones',
        'message_template_snapshot',
        'message_content',
        'sent_to_group',
        'was_message_sent',
        'student_was_frozen',
        'student_was_dismissed',
        'student_freeze_id',
        'deduction_points_count',
        'executed_by',
        'executed_at',
        'meta',
    ];

    protected $casts = [
        'recipient_phones' => 'array',
        'sent_to_group' => 'bool',
        'was_message_sent' => 'bool',
        'student_was_frozen' => 'bool',
        'student_was_dismissed' => 'bool',
        'deduction_points_count' => 'int',
        'executed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function evaluationStudent(): BelongsTo
    {
        return $this->belongsTo(EvaluationStudent::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AbsenceRule::class, 'absence_rule_id');
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function freezeRecord(): BelongsTo
    {
        return $this->belongsTo(StudentFreeze::class, 'student_freeze_id');
    }
}

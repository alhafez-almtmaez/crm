<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbsenceRule extends Model
{
    use HasFactory;

    public const ATTENDANCE_TYPE_ABSENCE = 'absence';
    public const ATTENDANCE_TYPE_EXCUSED_ABSENCE = 'excused_absence';

    /** @deprecated Backward compatibility for legacy rows. */
    public const ACTION_SEND_MESSAGE = 'send_message';
    /** @deprecated Backward compatibility for legacy rows. */
    public const ACTION_SEND_MESSAGE_AND_FREEZE = 'send_message_and_freeze';
    public const ACTION_FREEZE_STUDENT = 'freeze_student';
    public const ACTION_DISMISS_STUDENT = 'dismiss_student';

    protected $fillable = [
        'center_id',
        'attendance_type',
        'occurrence_number',
        'action',
        'message_template_id',
        'send_to_center_group',
        'freeze_reason',
        'freeze_working_days_count',
        'deduction_points_count',
        'meta',
        'is_active',
    ];

    protected $casts = [
        'occurrence_number' => 'int',
        'send_to_center_group' => 'bool',
        'freeze_working_days_count' => 'int',
        'deduction_points_count' => 'int',
        'meta' => 'array',
        'is_active' => 'bool',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'message_template_id');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(AbsenceRuleExecutionLog::class);
    }
}

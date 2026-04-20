<?php

namespace App\Models;

use App\Support\PhoneNumberHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Student extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_FROZEN = 2;

    protected $fillable = [
        'first_name',
        'second_name',
        'middle_name',
        'last_name',
        'full_name',
        'id_number',
        'parent_phone_number',
        'phone_number',
        'email',
        'date_of_birth',
        'center_id',
        'group_id',
        'plan_type_id',
        'admin_id',
        'is_active',
        'deducted_points_count',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_active' => 'int',
        'deducted_points_count' => 'int',
    ];

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
        return $this->belongsTo(Plan::class, 'plan_type_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function freezeRecords(): HasMany
    {
        return $this->hasMany(StudentFreeze::class);
    }

    public function congratulatoryRecords(): HasMany
    {
        return $this->hasMany(StudentCongratulatory::class);
    }

    public function evaluationItems(): HasMany
    {
        return $this->hasMany(EvaluationStudent::class);
    }

    public function absenceRuleExecutionLogs(): HasMany
    {
        return $this->hasMany(AbsenceRuleExecutionLog::class);
    }

    public function setParentPhoneNumberAttribute(mixed $value): void
    {
        $this->attributes['parent_phone_number'] = PhoneNumberHelper::normalizeForStorage($value);
    }

    public function setPhoneNumberAttribute(mixed $value): void
    {
        $this->attributes['phone_number'] = PhoneNumberHelper::normalizeForStorage($value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('students')
            ->logOnly([
                'first_name',
                'second_name',
                'middle_name',
                'last_name',
                'full_name',
                'id_number',
                'parent_phone_number',
                'phone_number',
                'email',
                'date_of_birth',
                'center_id',
                'group_id',
                'plan_type_id',
                'admin_id',
                'is_active',
                'deducted_points_count',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

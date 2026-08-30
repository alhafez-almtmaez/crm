<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Center extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STUDENT_GENDER_MALE = 'male';

    public const STUDENT_GENDER_FEMALE = 'female';

    protected $fillable = [
        'name',
        'certificate_name',
        'student_gender',
        'phone',
        'group_serialized',
        'working_days',
        'show_center_manager_signature',
    ];

    protected $casts = [
        'working_days' => 'array',
        'show_center_manager_signature' => 'bool',
        'archived_at' => 'datetime',
    ];

    /** @return Builder<Center> */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function absenceRules(): HasMany
    {
        return $this->hasMany(AbsenceRule::class);
    }

    public function absenceRuleExecutionLogs(): HasMany
    {
        return $this->hasMany(AbsenceRuleExecutionLog::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('centers')
            ->logOnly([
                'name',
                'certificate_name',
                'student_gender',
                'phone',
                'group_serialized',
                'working_days',
                'show_center_manager_signature',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

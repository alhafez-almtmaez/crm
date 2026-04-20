<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Center extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $fillable = [
        'name',
        'phone',
        'group_serialized',
        'working_days',
    ];

    protected $casts = [
        'working_days' => 'array',
    ];

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
            ->logOnly(['name', 'phone', 'group_serialized', 'working_days'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

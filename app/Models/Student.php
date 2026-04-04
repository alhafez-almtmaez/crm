<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Student extends Model
{
    use LogsActivity;

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
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

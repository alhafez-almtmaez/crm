<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Plan extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'plan_types';

    protected $fillable = [
        'name',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'plan_type_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('plans')
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

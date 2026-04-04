<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Group extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'center_id',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('groups')
            ->logOnly(['name', 'center_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

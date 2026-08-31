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

    public const CATEGORY_QURAN = 'quran';

    public const CATEGORY_SUNNAH = 'sunnah';

    public const CATEGORIES = [
        self::CATEGORY_QURAN,
        self::CATEGORY_SUNNAH,
    ];

    protected $table = 'plan_types';

    protected $fillable = [
        'name',
        'category',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'plan_type_id');
    }

    public function points(): HasMany
    {
        return $this->hasMany(PlanPoint::class, 'plan_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('plans')
            ->logOnly(['name', 'category'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

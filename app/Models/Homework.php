<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Homework extends Model
{
    use HasFactory;

    protected $table = 'homeworks';

    protected $fillable = [
        'date',
        'center_id',
        'admin_id',
    ];

    protected $casts = [
        'date' => 'date',
        'center_id' => 'int',
        'admin_id' => 'int',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(HomeworkStudent::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(HomeworkStudentPoint::class);
    }
}

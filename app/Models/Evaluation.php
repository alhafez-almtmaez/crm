<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    use HasFactory;

    public const TYPE_ALHIFZ = 1;
    public const TYPE_TAJWID = 2;

    protected $fillable = [
        'ulid',
        'date',
        'admin_id',
        'center_id',
        'is_send_absence_alerts',
        'evaluation_type',
    ];

    protected $casts = [
        'date' => 'date',
        'is_send_absence_alerts' => 'bool',
        'evaluation_type' => 'int',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function evaluationStudents(): HasMany
    {
        return $this->hasMany(EvaluationStudent::class);
    }
}

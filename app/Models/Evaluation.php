<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    protected $fillable = [
        'uuid',
        'date',
        'admin_id',
        'center_id',
        'is_send_absence_alerts',
    ];

    protected $casts = [
        'date' => 'date',
        'is_send_absence_alerts' => 'bool',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function evaluationStudents(): HasMany
    {
        return $this->hasMany(EvaluationStudent::class);
    }
}

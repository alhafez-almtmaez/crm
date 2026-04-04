<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentFreeze extends Model
{
    protected $fillable = [
        'student_id',
        'from',
        'to',
        'reason',
        'contact_phone',
        'frozen_by',
        'is_active',
        'unfrozen_at',
    ];

    protected $casts = [
        'from' => 'date',
        'to' => 'date',
        'is_active' => 'bool',
        'unfrozen_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCongratulatory extends Model
{
    protected $fillable = [
        'student_id',
        'reason',
        'sent_by',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

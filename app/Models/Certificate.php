<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    public const ACHIEVEMENT_SURAH = 'surah';

    public const ACHIEVEMENT_PART = 'part';

    public const ACHIEVEMENT_THREE_PARTS = 'three_parts';

    protected $fillable = [
        'ulid',
        'student_id',
        'plan_point_id',
        'issued_by',
        'certificate_number',
        'student_name',
        'center_name',
        'show_center_manager_signature',
        'design_snapshot',
        'wording_snapshot',
        'plan_name',
        'plan_point_name',
        'achievement_type',
        'achievement_name',
        'surah_name',
        'part_name',
        'three_parts',
        'title',
        'quote_first',
        'quote_second',
        'project_name',
        'closing_text',
        'center_manager_title',
        'project_manager_title',
        'date_title',
        'hijri_date',
        'gregorian_date',
        'achieved_at',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'student_id' => 'int',
            'plan_point_id' => 'int',
            'issued_by' => 'int',
            'show_center_manager_signature' => 'bool',
            'design_snapshot' => 'array',
            'wording_snapshot' => 'array',
            'achieved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function planPoint(): BelongsTo
    {
        return $this->belongsTo(PlanPoint::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}

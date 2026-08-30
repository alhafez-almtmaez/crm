<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CertificateContentTemplateAssignment extends Model
{
    use LogsActivity;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_GENDER = 'gender';

    public const SCOPE_CENTER = 'center';

    public const SCOPES = [
        self::SCOPE_GLOBAL,
        self::SCOPE_GENDER,
        self::SCOPE_CENTER,
    ];

    protected $fillable = [
        'template_id',
        'scope_type',
        'center_id',
        'student_gender',
        'achievement_type',
        'scope_key',
    ];

    protected function casts(): array
    {
        return [
            'template_id' => 'int',
            'center_id' => 'int',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateContentTemplate::class, 'template_id');
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('certificate_content_template_assignments')
            ->logOnly([
                'template_id',
                'scope_type',
                'center_id',
                'student_gender',
                'achievement_type',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}

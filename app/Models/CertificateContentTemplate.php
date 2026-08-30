<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CertificateContentTemplate extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'key',
        'name',
        'sections',
        'is_system',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'is_system' => 'bool',
            'is_active' => 'bool',
            'created_by' => 'int',
            'updated_by' => 'int',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CertificateContentTemplateAssignment::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('certificate_content_templates')
            ->logOnly(['key', 'name', 'sections', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected function key(): Attribute
    {
        return Attribute::set(static fn (mixed $value): string => Str::lower(trim((string) $value)));
    }
}

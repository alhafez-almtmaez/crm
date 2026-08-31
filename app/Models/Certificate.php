<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Certificate extends Model
{
    use LogsActivity;

    public const ACHIEVEMENT_SURAH = 'surah';

    public const ACHIEVEMENT_PART = 'part';

    public const ACHIEVEMENT_THREE_PARTS = 'three_parts';

    public const ACHIEVEMENT_SUNNAH_BOOK = 'sunnah_book';

    public const ACHIEVEMENT_SUNNAH_PART = 'sunnah_part';

    public const ACHIEVEMENT_TYPES = [
        self::ACHIEVEMENT_SURAH,
        self::ACHIEVEMENT_PART,
        self::ACHIEVEMENT_THREE_PARTS,
        self::ACHIEVEMENT_SUNNAH_BOOK,
        self::ACHIEVEMENT_SUNNAH_PART,
    ];

    public const STATUS_VALID = 'valid';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_REPLACED = 'replaced';

    public const STATUSES = [
        self::STATUS_VALID,
        self::STATUS_REVOKED,
        self::STATUS_REPLACED,
    ];

    protected $attributes = [
        'status' => self::STATUS_VALID,
    ];

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
        'book_name',
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
        'status',
        'revoked_at',
        'revoked_reason',
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
            'revoked_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('certificates')
            ->logOnly([
                'public_id',
                'certificate_number',
                'status',
                'revoked_at',
                'revoked_reason',
                'student_name',
                'achievement_type',
                'achievement_name',
                'surah_name',
                'part_name',
                'three_parts',
                'book_name',
                'issued_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
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

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate): void {
            if (blank($certificate->public_id)) {
                $certificate->public_id = (string) Str::uuid();
            }
        });
    }

    protected function publicId(): Attribute
    {
        return Attribute::set(function (mixed $value): string {
            $publicId = trim((string) $value);

            if (! Str::isUuid($publicId, version: 4)) {
                throw new LogicException('Certificate public_id must be a UUID v4.');
            }

            $original = (string) $this->getRawOriginal('public_id');
            if ($this->exists && $original !== '' && ! hash_equals($original, $publicId)) {
                throw new LogicException('Certificate public_id cannot be changed after issuance.');
            }

            return $publicId;
        });
    }
}

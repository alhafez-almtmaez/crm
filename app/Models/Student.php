<?php

namespace App\Models;

use App\Support\PhoneNumberHelper;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Student extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    public const STATUS_FROZEN = 2;

    public const CERTIFICATE_PORTAL_DELIVERY_PROCESSING = 'processing';

    public const CERTIFICATE_PORTAL_DELIVERY_SENT = 'sent';

    public const CERTIFICATE_PORTAL_DELIVERY_REVIEW_REQUIRED = 'review_required';

    public const CERTIFICATE_PORTAL_DELIVERY_UNREGISTERED = 'unregistered';

    protected $fillable = [
        'first_name',
        'second_name',
        'middle_name',
        'last_name',
        'full_name',
        'id_number',
        'parent_phone_number',
        'phone_number',
        'email',
        'date_of_birth',
        'center_id',
        'group_id',
        'plan_type_id',
        'current_plan_point_id',
        'max_daily_weight',
        'daily_weight_limits',
        'monthly_plan_cursor_point_id',
        'admin_id',
        'is_active',
        'deducted_points_count',
        'points_balance',
    ];

    protected $hidden = [
        'certificate_portal_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_active' => 'int',
        'current_plan_point_id' => 'int',
        'max_daily_weight' => 'int',
        'daily_weight_limits' => 'array',
        'monthly_plan_cursor_point_id' => 'int',
        'deducted_points_count' => 'int',
        'points_balance' => 'int',
        'certificate_portal_delivery_claimed_at' => 'datetime',
        'certificate_portal_sent_at' => 'datetime',
        'certificate_portal_sent_by' => 'int',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_type_id');
    }

    public function currentPlanPoint(): BelongsTo
    {
        return $this->belongsTo(PlanPoint::class, 'current_plan_point_id');
    }

    public function monthlyPlanCursorPoint(): BelongsTo
    {
        return $this->belongsTo(PlanPoint::class, 'monthly_plan_cursor_point_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function freezeRecords(): HasMany
    {
        return $this->hasMany(StudentFreeze::class);
    }

    public function congratulatoryRecords(): HasMany
    {
        return $this->hasMany(StudentCongratulatory::class);
    }

    public function evaluationItems(): HasMany
    {
        return $this->hasMany(EvaluationStudent::class);
    }

    public function absenceRuleExecutionLogs(): HasMany
    {
        return $this->hasMany(AbsenceRuleExecutionLog::class);
    }

    public function homeworkRows(): HasMany
    {
        return $this->hasMany(HomeworkStudent::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(StudentPointTransaction::class);
    }

    public function monthlyPlans(): HasMany
    {
        return $this->hasMany(StudentMonthlyPlan::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function setParentPhoneNumberAttribute(mixed $value): void
    {
        $this->attributes['parent_phone_number'] = PhoneNumberHelper::normalizeForStorage($value);
    }

    public function setPhoneNumberAttribute(mixed $value): void
    {
        $this->attributes['phone_number'] = PhoneNumberHelper::normalizeForStorage($value);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('students')
            ->logOnly([
                'first_name',
                'second_name',
                'middle_name',
                'last_name',
                'full_name',
                'id_number',
                'parent_phone_number',
                'phone_number',
                'email',
                'date_of_birth',
                'center_id',
                'group_id',
                'plan_type_id',
                'current_plan_point_id',
                'max_daily_weight',
                'daily_weight_limits',
                'monthly_plan_cursor_point_id',
                'admin_id',
                'is_active',
                'deducted_points_count',
                'points_balance',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        static::creating(static function (Student $student): void {
            if (blank($student->certificate_portal_id)) {
                $student->certificate_portal_id = (string) Str::uuid();
            }
        });

        static::saved(static function (Student $student): void {
            if ($student->group_id === null || ! Schema::hasTable('group_student')) {
                return;
            }

            // Keep records created through the legacy group_id field available to the new relation.
            $student->groups()->syncWithoutDetaching([(int) $student->group_id]);
        });
    }

    protected function certificatePortalId(): Attribute
    {
        return Attribute::set(function (mixed $value): string {
            $portalId = trim((string) $value);

            if (! Str::isUuid($portalId, version: 4)) {
                throw new LogicException('Student certificate_portal_id must be a UUID v4.');
            }

            $original = (string) $this->getRawOriginal('certificate_portal_id');
            if ($this->exists && $original !== '' && ! hash_equals($original, $portalId)) {
                throw new LogicException('Student certificate_portal_id cannot be changed.');
            }

            return $portalId;
        });
    }
}

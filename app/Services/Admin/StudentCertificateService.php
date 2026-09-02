<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Certificate;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Services\System\CertificateAchievementService;
use App\Services\System\CertificateContentTemplateService;
use App\Services\System\CertificateDesignSettingsService;
use App\Services\System\CertificateQrCodeService;
use App\Services\System\CertificateWordingService;
use App\Services\System\DateTimeFormatterService;
use App\Services\System\SystemSettingsService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use IntlDateFormatter;

class StudentCertificateService
{
    private const GREGORIAN_MONTH_NUMBERS = [
        'يناير' => 1,
        'فبراير' => 2,
        'مارس' => 3,
        'أبريل' => 4,
        'مايو' => 5,
        'يونيو' => 6,
        'يوليو' => 7,
        'أغسطس' => 8,
        'سبتمبر' => 9,
        'أكتوبر' => 10,
        'نوفمبر' => 11,
        'ديسمبر' => 12,
        'كانون الثاني' => 1,
        'شباط' => 2,
        'آذار' => 3,
        'نيسان' => 4,
        'أيار' => 5,
        'حزيران' => 6,
        'تموز' => 7,
        'آب' => 8,
        'أيلول' => 9,
        'تشرين الأول' => 10,
        'تشرين الثاني' => 11,
        'كانون الأول' => 12,
    ];

    /** @var array<string, string> */
    private array $dataUriCache = [];

    public function __construct(
        private readonly SystemSettingsService $settings,
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly CertificateAchievementService $certificateAchievements,
        private readonly CertificateDesignSettingsService $certificateDesigns,
        private readonly CertificateQrCodeService $certificateQrCodes,
        private readonly CertificateWordingService $certificateWordings,
        private readonly CertificateContentTemplateService $certificateContentTemplates,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Student $student): array
    {
        $student->loadMissing([
            'center:id,name',
            'plan:id,name',
            'currentPlanPoint:id,plan_id,sort_order,name',
        ]);

        $currentPoint = $this->progressPoint($student);
        $issuedCertificates = $student->certificates()
            ->with('issuer:id,name')
            ->latest('issued_at')
            ->latest('id')
            ->get();
        $issuedPlanPointIds = $issuedCertificates
            ->pluck('plan_point_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $availableCertificates = $this->reachedCertificatePoints($student, $currentPoint)
            ->reject(static fn (PlanPoint $point): bool => in_array((int) $point->id, $issuedPlanPointIds, true))
            ->map(fn (PlanPoint $point): array => $this->checkpointItem($point))
            ->values()
            ->all();

        return [
            'student' => [
                'id' => (int) $student->id,
                'full_name' => (string) $student->full_name,
                'center_name' => $student->center?->name,
                'plan_name' => $student->plan?->name,
                'current_plan_point_name' => $currentPoint?->name,
                'has_whatsapp_recipient' => $this->studentHasWhatsAppRecipient($student),
            ],
            'availableCertificates' => $availableCertificates,
            'certificates' => $issuedCertificates
                ->map(fn (Certificate $certificate): array => $this->listItem($student, $certificate))
                ->all(),
            'canIssue' => (bool) Auth::user()?->can('students.update'),
            'canRedesign' => (bool) Auth::user()?->can('students.update'),
            'canRevoke' => (bool) Auth::user()?->can('certificates.revoke'),
            'canSendWhatsApp' => (bool) Auth::user()?->can('certificates.send'),
        ];
    }

    public function issue(Student $student, int $planPointId): Certificate
    {
        return DB::transaction(function () use ($student, $planPointId): Certificate {
            /** @var Student $lockedStudent */
            $lockedStudent = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedStudent->loadMissing([
                'center:id,name,certificate_name,student_gender,show_center_manager_signature',
                'plan:id,name',
            ]);

            /** @var PlanPoint|null $planPoint */
            $planPoint = PlanPoint::query()
                ->whereKey($planPointId)
                ->lockForUpdate()
                ->first();

            if ($planPoint === null || ! $planPoint->requires_certificate) {
                throw ValidationException::withMessages([
                    'plan_point_id' => __('certificates.not_certificate_checkpoint'),
                ]);
            }

            $currentPoint = $this->progressPoint($lockedStudent);
            if (! $this->pointWasReached($lockedStudent, $currentPoint, $planPoint)) {
                throw ValidationException::withMessages([
                    'plan_point_id' => __('certificates.checkpoint_not_reached'),
                ]);
            }

            if (Certificate::query()
                ->where('student_id', $lockedStudent->id)
                ->where('plan_point_id', $planPoint->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'plan_point_id' => __('certificates.already_issued'),
                ]);
            }

            return $this->createCertificate(
                $lockedStudent,
                $planPoint,
                $this->achievementDate($lockedStudent, $planPoint),
                $lockedStudent->plan?->name,
            );
        });
    }

    /**
     * Issue an auditable historical certificate from a completion event that
     * proves the student reached the requested checkpoint. The evidence and
     * checkpoint must belong to the same historical plan, so a later transfer
     * cannot leak progress from one plan into another.
     */
    public function issueFromCompletion(
        StudentPointTransaction $transaction,
        ?int $certificatePlanPointId = null,
    ): Certificate {
        return DB::transaction(function () use ($transaction, $certificatePlanPointId): Certificate {
            /** @var StudentPointTransaction $lockedTransaction */
            $lockedTransaction = StudentPointTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTransaction->type !== StudentPointTransaction::TYPE_HOMEWORK_COMPLETED
                || $lockedTransaction->student_id === null
                || $lockedTransaction->plan_point_id === null
                || $lockedTransaction->created_at === null) {
                throw ValidationException::withMessages([
                    'transaction' => __('certificates.checkpoint_not_reached'),
                ]);
            }

            /** @var Student $lockedStudent */
            $lockedStudent = Student::query()
                ->whereKey($lockedTransaction->student_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((int) $lockedStudent->is_active !== Student::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'student' => __('certificates.bulk_active_students_only'),
                ]);
            }
            $lockedStudent->loadMissing([
                'center:id,name,certificate_name,student_gender,show_center_manager_signature',
                'plan:id,name',
            ]);

            /** @var PlanPoint|null $evidencePoint */
            $evidencePoint = PlanPoint::query()
                ->with('plan:id,name,category')
                ->whereKey($lockedTransaction->plan_point_id)
                ->lockForUpdate()
                ->first();

            if ($evidencePoint === null) {
                throw ValidationException::withMessages([
                    'transaction' => __('certificates.checkpoint_not_reached'),
                ]);
            }

            $targetPlanPointId = $certificatePlanPointId ?? (int) $evidencePoint->id;
            /** @var PlanPoint|null $planPoint */
            $planPoint = (int) $evidencePoint->id === $targetPlanPointId
                ? $evidencePoint
                : PlanPoint::query()
                    ->with('plan:id,name,category')
                    ->whereKey($targetPlanPointId)
                    ->lockForUpdate()
                    ->first();

            if ($planPoint === null || ! $planPoint->requires_certificate) {
                throw ValidationException::withMessages([
                    'transaction' => __('certificates.not_certificate_checkpoint'),
                ]);
            }

            if ((int) $planPoint->plan_id !== (int) $evidencePoint->plan_id
                || ! $this->pointIsAtOrBefore($planPoint, $evidencePoint)) {
                throw ValidationException::withMessages([
                    'transaction' => __('certificates.checkpoint_not_reached'),
                ]);
            }

            $existing = Certificate::query()
                ->where('student_id', $lockedStudent->id)
                ->where('plan_point_id', $planPoint->id)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            return $this->createCertificate(
                $lockedStudent,
                $planPoint,
                $lockedTransaction->created_at,
                $planPoint->plan?->name,
            );
        });
    }

    private function pointIsAtOrBefore(PlanPoint $checkpoint, PlanPoint $evidence): bool
    {
        return (int) $checkpoint->sort_order < (int) $evidence->sort_order
            || ((int) $checkpoint->sort_order === (int) $evidence->sort_order
                && (int) $checkpoint->id <= (int) $evidence->id);
    }

    private function createCertificate(
        Student $lockedStudent,
        PlanPoint $planPoint,
        CarbonInterface $achievedAt,
        ?string $planName,
    ): Certificate {
        $achievement = $this->certificateAchievements->resolve($planPoint);
        if ($achievement === null) {
            throw ValidationException::withMessages([
                'plan_point_id' => __('certificates.missing_achievement_data'),
            ]);
        }

        $designSnapshot = $this->certificateDesigns->resolveForCenter(
            $lockedStudent->center,
            $achievement['type'],
        );
        $legacyWording = $this->certificateWordings->resolve(
            (string) $designSnapshot['student_gender'],
            $achievement['type'],
        );

        $dates = $this->certificateDates($achievedAt);
        $issuedAt = now();
        $ulid = (string) Str::ulid();
        $certificateNumber = $this->certificateNumber($ulid, $issuedAt);
        $centerName = $this->nullableTrim($lockedStudent->center?->certificate_name)
            ?? $lockedStudent->center?->name;
        $displayAchievementName = $this->certificateAchievements->displayName(
            $achievement['type'],
            $achievement['name'],
        );
        $wordingSnapshot = $this->certificateContentTemplates->resolveSnapshot(
            $lockedStudent->center,
            $achievement['type'],
            [
                'student_name' => trim((string) $lockedStudent->full_name),
                'center_name' => $centerName ?? (string) __('certificates.default_center_name'),
                'achievement_label' => (string) $legacyWording['achievement_label'],
                'achievement_name' => $displayAchievementName,
                'certificate_number' => $certificateNumber,
                'plan_name' => (string) ($planName ?? ''),
                'plan_point_name' => (string) $planPoint->name,
                'hijri_date' => $dates['hijri'] ?? '—',
                'gregorian_date' => $dates['gregorian'],
            ],
            (string) $designSnapshot['student_gender'],
        ) ?? $legacyWording;
        $contentSnapshot = $this->certificateContentTemplates->snapshot($wordingSnapshot);
        $renderedContent = is_array($contentSnapshot['rendered_sections'] ?? null)
            ? $contentSnapshot['rendered_sections']
            : [];

        return Certificate::query()->create([
            'ulid' => $ulid,
            'student_id' => $lockedStudent->id,
            'plan_point_id' => $planPoint->id,
            'issued_by' => Auth::id(),
            'certificate_number' => $certificateNumber,
            'status' => Certificate::STATUS_VALID,
            'student_name' => trim((string) $lockedStudent->full_name),
            'center_name' => $centerName,
            'show_center_manager_signature' => (bool) ($lockedStudent->center?->show_center_manager_signature ?? true),
            'design_snapshot' => $designSnapshot,
            'wording_snapshot' => $wordingSnapshot,
            'plan_name' => $planName,
            'plan_point_name' => (string) $planPoint->name,
            'achievement_type' => $achievement['type'],
            'achievement_name' => $achievement['name'],
            'surah_name' => $this->nullableTrim($planPoint->surah_name),
            'part_name' => $this->nullableTrim($planPoint->part_name),
            'three_parts' => $this->nullableTrim($planPoint->three_parts),
            'book_name' => $this->nullableTrim($planPoint->book_name),
            'title' => (string) ($renderedContent['title'] ?? config('certificates.title')),
            'quote_first' => (string) ($renderedContent['quote_first'] ?? config('certificates.quote_first')),
            'quote_second' => (string) ($renderedContent['quote_second'] ?? config('certificates.quote_second')),
            'project_name' => (string) ($wordingSnapshot['project_name'] ?? ''),
            'closing_text' => (string) ($renderedContent['closing'] ?? $wordingSnapshot['closing_text'] ?? ''),
            'center_manager_title' => (string) config('certificates.center_manager_title'),
            'project_manager_title' => (string) config('certificates.project_manager_title'),
            'date_title' => (string) config('certificates.date_title'),
            'hijri_date' => $dates['hijri'],
            'gregorian_date' => $dates['gregorian'],
            'achieved_at' => $achievedAt,
            'issued_at' => $issuedAt,
        ]);
    }

    public function redesign(Student $student, Certificate $certificate): Certificate
    {
        return DB::transaction(function () use ($student, $certificate): Certificate {
            /** @var Student $lockedStudent */
            $lockedStudent = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentCenter = $lockedStudent->center_id !== null
                ? Center::query()
                    ->whereKey($lockedStudent->center_id)
                    ->lockForUpdate()
                    ->first(['id', 'student_gender', 'show_center_manager_signature'])
                : null;

            /** @var Certificate $lockedCertificate */
            $lockedCertificate = Certificate::query()
                ->whereKey($certificate->id)
                ->where('student_id', $lockedStudent->id)
                ->lockForUpdate()
                ->firstOrFail();

            $centerGender = $currentCenter?->student_gender;
            $savedGender = data_get($lockedCertificate->design_snapshot, 'student_gender')
                ?? data_get($lockedCertificate->wording_snapshot, 'student_gender');
            $gender = in_array($centerGender, [Center::STUDENT_GENDER_MALE, Center::STUDENT_GENDER_FEMALE], true)
                ? $centerGender
                : (in_array($savedGender, [Center::STUDENT_GENDER_MALE, Center::STUDENT_GENDER_FEMALE], true)
                    ? $savedGender
                    : Center::STUDENT_GENDER_MALE);

            $designSnapshot = $this->certificateDesigns->resolveForCenter(
                $currentCenter,
                (string) $lockedCertificate->achievement_type,
                $gender,
            );
            $legacyWording = $this->certificateWordings->resolve(
                $gender,
                (string) $lockedCertificate->achievement_type,
            );
            $wordingSnapshot = $this->certificateContentTemplates->resolveSnapshot(
                $currentCenter,
                (string) $lockedCertificate->achievement_type,
                [
                    'student_name' => (string) $lockedCertificate->student_name,
                    'center_name' => $this->nullableTrim($lockedCertificate->center_name)
                        ?? (string) __('certificates.default_center_name'),
                    'achievement_label' => (string) $legacyWording['achievement_label'],
                    'achievement_name' => $this->certificateAchievements->displayName(
                        (string) $lockedCertificate->achievement_type,
                        (string) $lockedCertificate->achievement_name,
                    ),
                    'certificate_number' => (string) $lockedCertificate->certificate_number,
                    'plan_name' => (string) ($lockedCertificate->plan_name ?? ''),
                    'plan_point_name' => (string) $lockedCertificate->plan_point_name,
                    'hijri_date' => $this->nullableTrim($lockedCertificate->hijri_date) ?? '—',
                    'gregorian_date' => $this->gregorianDateYearMonthDay(
                        (string) $lockedCertificate->gregorian_date,
                    ),
                ],
                $gender,
            ) ?? $legacyWording;
            $contentSnapshot = $this->certificateContentTemplates->snapshot($wordingSnapshot);
            $renderedContent = is_array($contentSnapshot['rendered_sections'] ?? null)
                ? $contentSnapshot['rendered_sections']
                : [];
            $oldTemplateKey = data_get($lockedCertificate->wording_snapshot, 'template_key');
            $oldTemplateId = data_get($lockedCertificate->wording_snapshot, 'template_id');
            $oldTemplateRevision = data_get($lockedCertificate->wording_snapshot, 'template_revision');
            $oldDesign = $lockedCertificate->design_snapshot;
            $oldWhatsAppDeliveryStatus = $lockedCertificate->whatsapp_delivery_status;
            $oldWhatsAppSentAt = $lockedCertificate->whatsapp_sent_at;
            $oldWhatsAppFilename = $lockedCertificate->whatsapp_image_filename;

            if ($oldWhatsAppDeliveryStatus === Certificate::WHATSAPP_DELIVERY_PROCESSING
                && ! $this->isStaleWhatsAppProcessingClaim($lockedCertificate)) {
                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_send_in_progress'),
                ]);
            }

            $lockedCertificate->forceFill([
                'design_snapshot' => $designSnapshot,
                'wording_snapshot' => $wordingSnapshot,
                'title' => (string) ($renderedContent['title'] ?? $lockedCertificate->title),
                'quote_first' => (string) ($renderedContent['quote_first'] ?? $lockedCertificate->quote_first),
                'quote_second' => (string) ($renderedContent['quote_second'] ?? $lockedCertificate->quote_second),
                'project_name' => (string) ($wordingSnapshot['project_name'] ?? ''),
                'closing_text' => (string) ($renderedContent['closing'] ?? $wordingSnapshot['closing_text'] ?? ''),
                'show_center_manager_signature' => $currentCenter !== null
                    ? (bool) $currentCenter->show_center_manager_signature
                    : (bool) $lockedCertificate->show_center_manager_signature,
                'whatsapp_delivery_status' => null,
                'whatsapp_sent_at' => null,
                'whatsapp_sent_by' => null,
                'whatsapp_image_filename' => null,
            ])->save();

            $activity = activity('certificates')
                ->performedOn($lockedCertificate)
                ->withProperties([
                    'action' => 'redesign',
                    'old_template_key' => is_string($oldTemplateKey) ? $oldTemplateKey : null,
                    'new_template_key' => data_get($wordingSnapshot, 'template_key'),
                    'old_template_id' => is_numeric($oldTemplateId) ? (int) $oldTemplateId : null,
                    'new_template_id' => data_get($wordingSnapshot, 'template_id'),
                    'old_template_revision' => is_string($oldTemplateRevision)
                        ? $oldTemplateRevision
                        : null,
                    'new_template_revision' => data_get($wordingSnapshot, 'template_revision'),
                    'center_id' => $currentCenter?->id,
                    'achievement_type' => (string) $lockedCertificate->achievement_type,
                    'old_design' => $oldDesign,
                    'new_design' => $designSnapshot,
                    'previous_whatsapp_delivery_status' => $oldWhatsAppDeliveryStatus,
                    'previous_whatsapp_sent_at' => $oldWhatsAppSentAt?->toISOString(),
                    'previous_whatsapp_image_filename' => $oldWhatsAppFilename,
                    'whatsapp_delivery_reset' => $oldWhatsAppDeliveryStatus !== null
                        || $oldWhatsAppSentAt !== null,
                ])
                ->event('redesigned');
            if (Auth::user() !== null) {
                $activity->causedBy(Auth::user());
            }
            $activity->log('certificate_redesigned');

            return $lockedCertificate->refresh();
        });
    }

    public function revoke(Student $student, Certificate $certificate, string $reason): Certificate
    {
        return DB::transaction(function () use ($student, $certificate, $reason): Certificate {
            /** @var Certificate $lockedCertificate */
            $lockedCertificate = Certificate::query()
                ->whereKey($certificate->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCertificate->status === Certificate::STATUS_REVOKED) {
                return $lockedCertificate;
            }

            if ($lockedCertificate->status === Certificate::STATUS_REPLACED) {
                throw ValidationException::withMessages([
                    'certificate' => __('certificates.replaced_cannot_be_revoked'),
                ]);
            }

            if ($lockedCertificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_PROCESSING
                && ! $this->isStaleWhatsAppProcessingClaim($lockedCertificate)) {
                throw ValidationException::withMessages([
                    'certificate' => __('certificates.whatsapp_send_in_progress'),
                ]);
            }

            $lockedCertificate->forceFill([
                'status' => Certificate::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_reason' => trim($reason),
            ])->save();

            return $lockedCertificate->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function listItem(Student $student, Certificate $certificate): array
    {
        $certificate->loadMissing('issuer:id,name');
        $canSendWhatsApp = (bool) Auth::user()?->can('certificates.send');
        $wasSentViaWhatsApp = $certificate->whatsapp_sent_at !== null;
        $whatsAppDeliveryStatus = is_string($certificate->whatsapp_delivery_status)
            ? $certificate->whatsapp_delivery_status
            : null;
        if ($whatsAppDeliveryStatus === Certificate::WHATSAPP_DELIVERY_PROCESSING
            && $this->isStaleWhatsAppProcessingClaim($certificate)) {
            $whatsAppDeliveryStatus = Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED;
        }
        $canSendThisCertificate = $canSendWhatsApp
            && $certificate->status === Certificate::STATUS_VALID
            && ! $wasSentViaWhatsApp
            && $whatsAppDeliveryStatus === null
            && $this->studentHasWhatsAppRecipient($student);

        return [
            'id' => (string) $certificate->ulid,
            'certificate_number' => (string) $certificate->certificate_number,
            'achievement_type' => (string) $certificate->achievement_type,
            'achievement_type_label' => $this->achievementTypeLabel((string) $certificate->achievement_type),
            'achievement_name' => (string) $certificate->achievement_name,
            'plan_point_name' => (string) $certificate->plan_point_name,
            'gregorian_date' => $this->gregorianDateYearMonthDay((string) $certificate->gregorian_date),
            'issued_at' => $this->dateTimeFormatter->formatForAdmin($certificate->issued_at),
            'issued_by_name' => $certificate->issuer?->name,
            'status' => (string) $certificate->status,
            'status_label' => $this->statusLabel((string) $certificate->status),
            'verification_url' => $this->verificationUrl($certificate),
            'preview_url' => route('admin.students.certificates.show', [$student, $certificate]),
            'pdf_url' => route('admin.students.certificates.pdf', [$student, $certificate]),
            'redesign_url' => route('admin.students.certificates.redesign', [$student, $certificate]),
            'revoke_url' => $certificate->status === Certificate::STATUS_VALID
                && (bool) Auth::user()?->can('certificates.revoke')
                    ? route('admin.students.certificates.revoke', [$student, $certificate])
                    : null,
            'was_sent_via_whatsapp' => $wasSentViaWhatsApp,
            'whatsapp_delivery_status' => $whatsAppDeliveryStatus,
            'whatsapp_delivery_requires_review' => $whatsAppDeliveryStatus === Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED,
            'whatsapp_sent_at' => $certificate->whatsapp_sent_at?->toISOString(),
            'whatsapp_sent_at_formatted' => $certificate->whatsapp_sent_at !== null
                ? $this->dateTimeFormatter->formatForAdmin($certificate->whatsapp_sent_at)
                : null,
            'whatsapp_image_filename' => $certificate->whatsapp_image_filename,
            'can_send_whatsapp' => $canSendThisCertificate,
            'whatsapp_send_url' => $canSendWhatsApp
                ? route('admin.students.certificates.whatsapp', [$student, $certificate])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewPayload(Student $student, Certificate $certificate, bool $pdf = false): array
    {
        $centerName = $this->nullableTrim($certificate->center_name)
            ?? __('certificates.default_center_name');
        $design = $this->certificateDesigns->snapshot(
            $certificate->design_snapshot,
            (string) $certificate->achievement_type,
        );
        $contentTemplate = $this->certificateContentTemplates->snapshot($certificate->wording_snapshot);
        $wording = $contentTemplate === null
            ? $this->certificateWordings->snapshot(
                $certificate->wording_snapshot,
                (string) $certificate->achievement_type,
                [
                    'closing_text' => (string) $certificate->closing_text,
                ],
                (string) $design['student_gender'],
            )
            : $this->certificateWordings->resolve(
                (string) $contentTemplate['student_gender'],
                (string) $certificate->achievement_type,
            );
        $renderedContent = is_array($contentTemplate['rendered_sections'] ?? null)
            ? $contentTemplate['rendered_sections']
            : [];
        $showCenterIdentity = (bool) $certificate->show_center_manager_signature;
        $renderAssets = $this->renderAssetPayload($design, $pdf, $showCenterIdentity);
        $qrCode = $this->certificateQrCodes->payload(
            $this->verificationUrl($certificate),
            (string) ($design['accent_color'] ?? ''),
        );

        return [
            'page_title' => (string) ($renderedContent['title'] ?? $certificate->title),
            'title' => (string) ($renderedContent['title'] ?? $certificate->title),
            'quote_first' => (string) ($renderedContent['quote_first'] ?? $certificate->quote_first),
            'quote_second' => (string) ($renderedContent['quote_second'] ?? $certificate->quote_second),
            'project_name' => (string) $wording['project_name'],
            'center_name' => $centerName,
            'show_center_manager_signature' => $showCenterIdentity,
            'student_name' => (string) $certificate->student_name,
            'achievement_intro' => (string) $wording['achievement_intro'],
            'achievement_label' => (string) $wording['achievement_label'],
            'achievement_name' => $this->certificateAchievements->displayName(
                (string) $certificate->achievement_type,
                (string) $certificate->achievement_name,
            ),
            'achievement_suffix' => (string) $wording['achievement_suffix'],
            'closing_text' => (string) ($renderedContent['closing'] ?? $wording['closing_text']),
            'center_manager_title' => $showCenterIdentity
                ? (string) $certificate->center_manager_title
                : '',
            'project_manager_title' => (string) $certificate->project_manager_title,
            'date_title' => (string) $certificate->date_title,
            'hijri_date' => $this->nullableTrim($certificate->hijri_date) ?? '—',
            'gregorian_date' => $this->gregorianDateYearMonthDay((string) $certificate->gregorian_date),
            'certificate_number' => (string) $certificate->certificate_number,
            'intro_before_project' => (string) $wording['intro_before_project'],
            'intro_after_center' => (string) $wording['intro_after_center'],
            'content_template' => $contentTemplate,
            'labels' => config('certificates.labels', []),
            'design' => $design,
            'stylesheet_url' => $renderAssets['stylesheet_url'],
            'font_preload_urls' => $renderAssets['font_preload_urls'],
            'images' => $renderAssets['images'],
            'pdf_mode' => $pdf,
            'back_url' => route('admin.students.certificates.index', $student),
            'pdf_url' => route('admin.students.certificates.pdf', [$student, $certificate]),
            ...$qrCode,
        ];
    }

    /**
     * Build browser-safe or self-contained PDF assets for the real certificate Blade.
     *
     * @param  array<string, mixed>  $design
     * @return array{stylesheet_url: string, font_preload_urls: array<int, string>, images: array<string, string>}
     */
    public function renderAssetPayload(
        array $design,
        bool $pdf = false,
        bool $showCenterIdentity = true,
    ): array {
        $achievementType = is_string($design['achievement_type'] ?? null)
            ? $design['achievement_type']
            : Certificate::ACHIEVEMENT_SURAH;
        $design = $this->certificateDesigns->snapshot($design, $achievementType);

        $images = $this->imageSources($pdf, (string) $design['frame_path']);
        if (! $showCenterIdentity) {
            foreach (['left_logo', 'center_stamp', 'center_signature'] as $key) {
                $images[$key] = '';
            }
        }

        return [
            'stylesheet_url' => $pdf
                ? $this->pdfStylesheetDataUri($design)
                : $this->versionedPublicAssetUrl((string) config('certificates.assets.stylesheet')),
            'font_preload_urls' => $pdf ? [] : $this->fontPreloadUrls($design),
            'images' => $images,
        ];
    }

    /**
     * @return EloquentCollection<int, PlanPoint>
     */
    private function reachedCertificatePoints(Student $student, ?PlanPoint $currentPoint): EloquentCollection
    {
        if ($student->plan_type_id === null || $currentPoint === null) {
            return new EloquentCollection;
        }

        return PlanPoint::query()
            ->where('plan_id', $student->plan_type_id)
            ->where('requires_certificate', true)
            ->where(function ($query) use ($currentPoint): void {
                $query->where('sort_order', '<', $currentPoint->sort_order)
                    ->orWhere(function ($nested) use ($currentPoint): void {
                        $nested->where('sort_order', $currentPoint->sort_order)
                            ->where('id', '<=', $currentPoint->id);
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function progressPoint(Student $student): ?PlanPoint
    {
        if ($student->plan_type_id === null) {
            return null;
        }

        if ($student->current_plan_point_id !== null) {
            $currentPoint = PlanPoint::query()
                ->whereKey($student->current_plan_point_id)
                ->where('plan_id', $student->plan_type_id)
                ->first();

            if ($currentPoint !== null) {
                return $currentPoint;
            }
        }

        return PlanPoint::query()
            ->join('student_point_transactions', 'plan_points.id', '=', 'student_point_transactions.plan_point_id')
            ->where('student_point_transactions.student_id', $student->id)
            ->where('student_point_transactions.type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
            ->where('plan_points.plan_id', $student->plan_type_id)
            ->orderByDesc('plan_points.sort_order')
            ->orderByDesc('plan_points.id')
            ->select('plan_points.*')
            ->first();
    }

    private function pointWasReached(Student $student, ?PlanPoint $currentPoint, PlanPoint $point): bool
    {
        if ($currentPoint === null || $student->plan_type_id === null) {
            return false;
        }

        if ((int) $point->plan_id !== (int) $student->plan_type_id) {
            return false;
        }

        return $point->sort_order < $currentPoint->sort_order
            || ($point->sort_order === $currentPoint->sort_order && $point->id <= $currentPoint->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkpointItem(PlanPoint $point): array
    {
        $achievement = $this->certificateAchievements->resolve($point);

        return [
            'id' => (int) $point->id,
            'plan_point_name' => (string) $point->name,
            'achievement_type' => $achievement['type'] ?? null,
            'achievement_type_label' => isset($achievement['type'])
                ? $this->achievementTypeLabel($achievement['type'])
                : null,
            'achievement_name' => $achievement['name'] ?? null,
            'can_issue' => $achievement !== null,
            'issue_problem' => $achievement === null ? __('certificates.missing_achievement_data') : null,
        ];
    }

    private function achievementDate(Student $student, PlanPoint $point): CarbonInterface
    {
        $completedAt = StudentPointTransaction::query()
            ->where('student_id', $student->id)
            ->where('plan_point_id', $point->id)
            ->where('type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
            ->oldest('created_at')
            ->value('created_at');

        return $completedAt !== null ? Carbon::parse($completedAt) : now();
    }

    /**
     * @return array{gregorian: string, hijri: string|null}
     */
    private function certificateDates(CarbonInterface $date): array
    {
        $timezone = (string) ($this->settings->get()['timezone'] ?? 'Asia/Amman');
        $localDate = $date->copy()->setTimezone($timezone);
        $gregorian = $this->gregorianDateYearMonthDay($localDate->format('Y/m/d'));
        $hijri = null;

        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter(
                'ar_JO@calendar=islamic',
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                $timezone,
                IntlDateFormatter::TRADITIONAL,
                'd/MMMM/y',
            );
            $formatted = $formatter->format($localDate);
            if (is_string($formatted) && trim($formatted) !== '') {
                $hijri = $this->arabicDigits(trim($formatted));
            }
        }

        return ['gregorian' => $gregorian, 'hijri' => $hijri];
    }

    private function certificateNumber(string $ulid, CarbonInterface $issuedAt): string
    {
        $configuredPrefix = (string) config('certificates.number_prefix', 'CERT');
        $prefix = preg_replace('/[^A-Za-z0-9-]/', '', $configuredPrefix) ?: 'CERT';
        $timezone = (string) ($this->settings->get()['timezone'] ?? 'Asia/Amman');
        $year = $issuedAt->copy()->setTimezone($timezone)->format('Y');

        return Str::upper($prefix).'-'.$year.'-'.substr($ulid, -8);
    }

    private function achievementTypeLabel(string $type): string
    {
        return match ($type) {
            Certificate::ACHIEVEMENT_SURAH => __('certificates.types.surah'),
            Certificate::ACHIEVEMENT_PART => __('certificates.types.part'),
            Certificate::ACHIEVEMENT_THREE_PARTS => __('certificates.types.three_parts'),
            Certificate::ACHIEVEMENT_SUNNAH_BOOK => __('certificates.types.sunnah_book'),
            Certificate::ACHIEVEMENT_SUNNAH_PART => __('certificates.types.sunnah_part'),
            default => __('certificates.types.achievement'),
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Certificate::STATUS_VALID => __('certificates.statuses.valid'),
            Certificate::STATUS_REVOKED => __('certificates.statuses.revoked'),
            Certificate::STATUS_REPLACED => __('certificates.statuses.replaced'),
            default => __('certificates.statuses.unknown'),
        };
    }

    private function studentHasWhatsAppRecipient(Student $student): bool
    {
        foreach ([$student->parent_phone_number, $student->phone_number] as $phone) {
            if (is_string($phone) && trim($phone) !== '') {
                return true;
            }
        }

        return false;
    }

    private function isStaleWhatsAppProcessingClaim(Certificate $certificate): bool
    {
        return $certificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_PROCESSING
            && $certificate->updated_at !== null
            && $certificate->updated_at->lte(
                now()->subMinutes(Certificate::WHATSAPP_PROCESSING_STALE_AFTER_MINUTES),
            );
    }

    private function verificationUrl(Certificate|string $certificate): string
    {
        $publicId = $certificate instanceof Certificate
            ? (string) $certificate->public_id
            : $certificate;
        $path = route('certificates.verify', ['public_id' => $publicId], absolute: false);

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function imageSources(bool $pdf, string $framePath): array
    {
        $images = [];

        foreach (['frame', 'left_logo', 'right_logo', 'center_stamp', 'center_signature', 'project_stamp', 'project_signature'] as $key) {
            $relativePath = $key === 'frame'
                ? $framePath
                : (string) config("certificates.assets.{$key}", '');
            $images[$key] = $pdf
                ? $this->localDataUri($relativePath)
                : $this->versionedPublicAssetUrl($relativePath);
        }

        return $images;
    }

    /**
     * @return array<int, string>
     */
    private function fontPreloadUrls(array $design): array
    {
        return collect($this->selectedFontAssetMap($design))
            ->values()
            ->map(fn (string $publicPath): string => $this->validatedPublicPath($publicPath) !== null
                ? asset($publicPath)
                : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function versionedPublicAssetUrl(string $relativePath): string
    {
        $path = $this->validatedPublicPath($relativePath);
        if ($path === null) {
            return '';
        }

        return asset($relativePath).'?v='.filemtime($path);
    }

    private function pdfStylesheetDataUri(array $design): string
    {
        $relativePath = (string) config('certificates.assets.stylesheet', '');
        $path = $this->validatedPublicPath($relativePath);
        if ($path === null) {
            return 'data:text/css;base64,';
        }

        $css = file_get_contents($path);
        if (! is_string($css)) {
            return 'data:text/css;base64,';
        }

        foreach ($this->selectedFontAssetMap($design) as $cssPath => $publicPath) {
            $css = str_replace($cssPath, $this->localDataUri($publicPath), $css);
        }

        return 'data:text/css;base64,'.base64_encode($css);
    }

    /**
     * Resolve only the files used by this certificate so PDF payloads do not grow
     * with every font available in the design catalog.
     *
     * @param  array<string, mixed>  $design
     * @return array<string, string>
     */
    private function selectedFontAssetMap(array $design): array
    {
        $familyCatalog = config('certificates.font_families', []);
        $presetCatalog = config('certificates.fonts', []);
        if (! is_array($familyCatalog) || ! is_array($presetCatalog)) {
            return $this->legacyFontAssetMap();
        }

        $selectedFamilyKeys = [];
        $savedFamilies = array_filter([
            $design['body_font_family'] ?? null,
            $design['display_font_family'] ?? null,
        ], static fn (mixed $family): bool => is_string($family) && trim($family) !== '');

        foreach ($familyCatalog as $key => $family) {
            if (! is_string($key) || ! is_array($family)) {
                continue;
            }

            if (in_array($family['family'] ?? null, $savedFamilies, true)) {
                $selectedFamilyKeys[] = $key;
            }
        }

        if ($selectedFamilyKeys === []) {
            $fontKey = is_string($design['font'] ?? null)
                ? $design['font']
                : (string) config('certificates.default_font', 'classic');
            $defaultFontKey = (string) config('certificates.default_font', 'classic');
            $preset = $presetCatalog[$fontKey] ?? $presetCatalog[$defaultFontKey] ?? null;

            if (is_array($preset) && is_array($preset['families'] ?? null)) {
                $selectedFamilyKeys = array_values(array_filter(
                    $preset['families'],
                    static fn (mixed $key): bool => is_string($key),
                ));
            }
        }

        $fontMap = [];
        foreach (array_unique($selectedFamilyKeys) as $key) {
            $family = $familyCatalog[$key] ?? null;
            if (! is_array($family)) {
                continue;
            }

            foreach ([
                ['regular_css_path', 'regular_path'],
                ['bold_css_path', 'bold_path'],
            ] as [$cssKey, $pathKey]) {
                $cssPath = $family[$cssKey] ?? null;
                $publicPath = $family[$pathKey] ?? null;
                if (is_string($cssPath) && is_string($publicPath)) {
                    $fontMap[$cssPath] = $publicPath;
                }
            }
        }

        return $fontMap !== [] ? $fontMap : $this->legacyFontAssetMap();
    }

    /**
     * @return array<string, string>
     */
    private function legacyFontAssetMap(): array
    {
        $fontMap = config('certificates.assets.fonts', []);

        return is_array($fontMap)
            ? array_filter(
                $fontMap,
                static fn (mixed $publicPath, mixed $cssPath): bool => is_string($cssPath) && is_string($publicPath),
                ARRAY_FILTER_USE_BOTH,
            )
            : [];
    }

    private function localDataUri(string $relativePath): string
    {
        if (array_key_exists($relativePath, $this->dataUriCache)) {
            return $this->dataUriCache[$relativePath];
        }

        $path = $this->validatedPublicPath($relativePath);
        if ($path === null) {
            return $this->dataUriCache[$relativePath] = '';
        }

        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            return $this->dataUriCache[$relativePath] = '';
        }

        $mimeType = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'ttf' => 'font/ttf',
            default => 'application/octet-stream',
        };

        return $this->dataUriCache[$relativePath] = "data:{$mimeType};base64,".base64_encode($contents);
    }

    private function validatedPublicPath(string $relativePath): ?string
    {
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            return null;
        }

        $publicRoot = realpath(public_path());
        $path = realpath(public_path(ltrim($relativePath, '/\\')));

        if ($publicRoot === false || $path === false) {
            return null;
        }

        if ($path !== $publicRoot && ! str_starts_with($path, $publicRoot.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return is_file($path) ? $path : null;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function arabicDigits(string $value): string
    {
        return strtr($value, [
            '0' => '٠',
            '1' => '١',
            '2' => '٢',
            '3' => '٣',
            '4' => '٤',
            '5' => '٥',
            '6' => '٦',
            '7' => '٧',
            '8' => '٨',
            '9' => '٩',
        ]);
    }

    private function gregorianDateYearMonthDay(string $value): string
    {
        $value = trim($value);
        $westernDate = strtr($value, [
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);

        if (preg_match('/^(\d{4})\/([^\/]+)\/(\d{1,2})$/u', $westernDate, $matches)) {
            $month = $this->gregorianMonthNumber($matches[2]);

            if ($month !== null) {
                return $this->yearMonthDayDate($matches[1], $month, $matches[3]);
            }
        }

        if (preg_match('/^(\d{1,2})\/([^\/]+)\/(\d{4})$/u', $westernDate, $matches)) {
            $month = $this->gregorianMonthNumber($matches[2]);

            if ($month !== null) {
                return $this->yearMonthDayDate($matches[3], $month, $matches[1]);
            }
        }

        return $value;
    }

    private function gregorianMonthNumber(string $value): ?int
    {
        $month = ctype_digit($value)
            ? (int) $value
            : (self::GREGORIAN_MONTH_NUMBERS[$value] ?? null);

        return is_int($month) && $month >= 1 && $month <= 12 ? $month : null;
    }

    private function yearMonthDayDate(string $year, int $month, string $day): string
    {
        return $this->arabicDigits($year)
            .'/'.$this->arabicDigits(str_pad((string) $month, 2, '0', STR_PAD_LEFT))
            .'/'.$this->arabicDigits(str_pad($day, 2, '0', STR_PAD_LEFT));
    }
}

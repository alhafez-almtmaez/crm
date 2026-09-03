<?php

namespace App\Services\Admin;

use App\Exceptions\WhatsAppMessageSendException;
use App\Exceptions\WhatsAppRecipientNotRegisteredException;
use App\Models\Center;
use App\Models\Certificate;
use App\Models\Student;
use App\Services\System\StudentCertificatePortalService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentCertificatePortalWhatsAppService
{
    private const LOCK_SECONDS = 300;

    private const PROCESSING_STALE_AFTER_MINUTES = 10;

    public function __construct(
        private readonly WhatsAppMessagingService $messaging,
        private readonly StudentCertificatePortalService $portals,
    ) {}

    /**
     * @param  array<int, int>  $additionalStudentIds
     * @return array<string, int>
     */
    public function preview(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
        ?int $studentId = null,
        ?int $limit = null,
        array $additionalStudentIds = [],
    ): array {
        $students = $this->candidates(
            $cutoffUtc,
            $activeOnly,
            $studentId,
            $limit,
            $additionalStudentIds,
        );
        $summary = [
            'students' => $students->count(),
            'configured_messages' => 0,
            'pending_students' => 0,
            'pending_messages' => 0,
            'already_sent' => 0,
            'unregistered' => 0,
            'review_required' => 0,
            'processing' => 0,
            'no_recipient' => 0,
        ];

        foreach ($students as $student) {
            $phones = $this->recipientPhones($student);
            $messageCount = count($phones);
            $summary['configured_messages'] += $messageCount;

            if ($messageCount === 0) {
                $summary['no_recipient']++;

                continue;
            }

            $state = $this->deliveryState($student);
            if ($state === 'pending') {
                $summary['pending_students']++;
                $summary['pending_messages'] += $messageCount;
            } else {
                $summary[$state]++;
            }
        }

        return $summary;
    }

    /**
     * @param  (callable(array<string, int|string|null>): void)|null  $onProgress
     * @return array<string, mixed>
     */
    public function sendPending(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
        ?int $studentId = null,
        ?int $limit = null,
        ?callable $onProgress = null,
    ): array {
        $students = $this->candidates($cutoffUtc, $activeOnly, $studentId, $limit);
        $summary = [
            'candidates' => $students->count(),
            'checked' => 0,
            'attempted_students' => 0,
            'sent_students' => 0,
            'sent_messages' => 0,
            'already_sent' => 0,
            'unregistered' => 0,
            'review_required' => 0,
            'processing' => 0,
            'inactive' => 0,
            'no_recipient' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($students as $index => $candidate) {
            $summary['checked']++;
            $event = [
                'index' => $index + 1,
                'total' => $students->count(),
                'student_id' => (int) $candidate->id,
                'student_name' => (string) $candidate->full_name,
                'status' => null,
                'reason' => null,
            ];
            $freshStudent = $this->freshCandidate($candidate, $cutoffUtc);

            if (! $freshStudent instanceof Student) {
                $summary['inactive']++;
                $event['status'] = 'inactive';
                $event['reason'] = 'inactive_or_no_certificate';
                $this->progress($onProgress, $event);

                continue;
            }

            if ($this->recipientPhones($freshStudent) === []) {
                $summary['no_recipient']++;
                $event['status'] = 'no_recipient';
                $this->progress($onProgress, $event);

                continue;
            }

            $result = null;
            try {
                $result = $this->send($freshStudent, $cutoffUtc);
                $event['status'] = $result['status'];
                $event['recipient_count'] = $result['recipient_count'];

                if ($result['attempted']) {
                    $summary['attempted_students']++;
                }

                match ($result['status']) {
                    'sent' => $summary['sent_students']++,
                    'already_sent' => $summary['already_sent']++,
                    'unregistered' => $summary['unregistered']++,
                    'review_required' => $summary['review_required']++,
                    'processing' => $summary['processing']++,
                    default => $summary['failed']++,
                };
                if ($result['status'] === 'sent') {
                    $summary['sent_messages'] += $result['recipient_count'];
                }
            } catch (Throwable $exception) {
                $summary['failed']++;
                $event['status'] = 'failed';
                $event['reason'] = $exception instanceof ValidationException
                    ? 'validation_failed'
                    : 'unexpected_error';
                $summary['failures'][] = $event;

                Log::warning('Student certificate portal WhatsApp delivery failed.', [
                    'student_id' => (int) $freshStudent->id,
                    'exception_class' => $exception::class,
                ]);
            }

            $this->progress($onProgress, $event);

            if (($result['attempted'] ?? false) && $index < $students->count() - 1) {
                $this->messaging->waitBetweenMessages();
            }
        }

        return $summary;
    }

    /**
     * @return array{status: string, attempted: bool, recipient_count: int, message: string}
     */
    public function send(Student $student, CarbonInterface $cutoffUtc): array
    {
        $lock = Cache::lock("student:{$student->id}:certificate-portal-whatsapp", self::LOCK_SECONDS);
        if (! $lock->get()) {
            return [
                'status' => 'processing',
                'attempted' => false,
                'recipient_count' => 0,
                'message' => '',
            ];
        }

        try {
            $freshStudent = $this->freshCandidate($student, $cutoffUtc);
            if (! $freshStudent instanceof Student) {
                throw ValidationException::withMessages([
                    'student' => __('certificates.portal_whatsapp_not_eligible'),
                ]);
            }

            $phones = $this->recipientPhones($freshStudent);
            if ($phones === []) {
                throw ValidationException::withMessages([
                    'student' => __('certificates.whatsapp_no_phone'),
                ]);
            }

            $fingerprint = $this->fingerprint($freshStudent, $phones);
            $existingState = $this->matchingDeliveryState($freshStudent, $fingerprint);
            if ($existingState === Student::CERTIFICATE_PORTAL_DELIVERY_SENT) {
                return [
                    'status' => 'already_sent',
                    'attempted' => false,
                    'recipient_count' => 0,
                    'message' => $this->message($freshStudent),
                ];
            }
            if ($existingState === Student::CERTIFICATE_PORTAL_DELIVERY_UNREGISTERED) {
                return [
                    'status' => 'unregistered',
                    'attempted' => false,
                    'recipient_count' => 0,
                    'message' => $this->message($freshStudent),
                ];
            }
            if ($existingState === Student::CERTIFICATE_PORTAL_DELIVERY_REVIEW_REQUIRED) {
                return [
                    'status' => 'review_required',
                    'attempted' => false,
                    'recipient_count' => 0,
                    'message' => $this->message($freshStudent),
                ];
            }
            if ($existingState === Student::CERTIFICATE_PORTAL_DELIVERY_PROCESSING) {
                if (! $this->processingIsStale($freshStudent)) {
                    return [
                        'status' => 'processing',
                        'attempted' => false,
                        'recipient_count' => 0,
                        'message' => $this->message($freshStudent),
                    ];
                }

                $this->markReviewRequired($freshStudent, $fingerprint);

                return [
                    'status' => 'review_required',
                    'attempted' => false,
                    'recipient_count' => 0,
                    'message' => $this->message($freshStudent),
                ];
            }

            $this->claim($freshStudent, $fingerprint);
            $message = $this->message($freshStudent);

            try {
                $eligibleRecipients = $this->messaging->assertHasEligibleRecipients($phones);
            } catch (WhatsAppRecipientNotRegisteredException) {
                $this->markUnregistered($freshStudent, $fingerprint);

                return [
                    'status' => 'unregistered',
                    'attempted' => false,
                    'recipient_count' => 0,
                    'message' => $message,
                ];
            } catch (Throwable $exception) {
                $this->releaseClaim($freshStudent, $fingerprint);

                throw $exception;
            }

            try {
                $this->messaging->sendTextMessageToPhones($phones, $message);
            } catch (WhatsAppRecipientNotRegisteredException) {
                $this->markUnregistered($freshStudent, $fingerprint);

                return [
                    'status' => 'unregistered',
                    'attempted' => false,
                    'recipient_count' => 0,
                    'message' => $message,
                ];
            } catch (WhatsAppMessageSendException $exception) {
                if ($exception->deliveryAttempted()) {
                    $this->markReviewRequired($freshStudent, $fingerprint);

                    return [
                        'status' => 'review_required',
                        'attempted' => true,
                        'recipient_count' => 0,
                        'message' => $message,
                    ];
                }

                $this->releaseClaim($freshStudent, $fingerprint);

                throw $exception;
            } catch (Throwable) {
                $this->markReviewRequired($freshStudent, $fingerprint);

                return [
                    'status' => 'review_required',
                    'attempted' => true,
                    'recipient_count' => 0,
                    'message' => $message,
                ];
            }

            $this->markSent($freshStudent, $fingerprint);

            return [
                'status' => 'sent',
                'attempted' => true,
                'recipient_count' => count($eligibleRecipients),
                'message' => $message,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<int, int>  $additionalStudentIds
     * @return EloquentCollection<int, Student>
     */
    public function candidates(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
        ?int $studentId = null,
        ?int $limit = null,
        array $additionalStudentIds = [],
    ): EloquentCollection {
        $additionalStudentIds = collect($additionalStudentIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return Student::query()
            ->with([
                'center:id,name,student_gender',
                'certificates' => static fn ($query) => $query
                    ->where('status', Certificate::STATUS_VALID)
                    ->orderBy('id')
                    ->select([
                        'id',
                        'student_id',
                        'public_id',
                        'achieved_at',
                        'updated_at',
                    ]),
            ])
            ->when($activeOnly, static fn (Builder $query): Builder => $query->where(
                'is_active',
                Student::STATUS_ACTIVE,
            ))
            ->when($studentId !== null, static fn (Builder $query): Builder => $query->whereKey($studentId))
            ->where(static function (Builder $query) use ($cutoffUtc, $additionalStudentIds): void {
                $query->whereHas('certificates', static fn (Builder $certificates): Builder => $certificates
                    ->where('status', Certificate::STATUS_VALID)
                    ->where('achieved_at', '<', $cutoffUtc));

                if ($additionalStudentIds !== []) {
                    $query->orWhereIn('students.id', $additionalStudentIds);
                }
            })
            ->orderBy('students.id')
            ->when($limit !== null, static fn (Builder $query): Builder => $query->limit($limit))
            ->get([
                'students.id',
                'students.full_name',
                'students.center_id',
                'students.parent_phone_number',
                'students.phone_number',
                'students.is_active',
                'students.certificate_portal_id',
                'students.certificate_portal_delivery_status',
                'students.certificate_portal_delivery_fingerprint',
                'students.certificate_portal_delivery_claimed_at',
                'students.certificate_portal_sent_at',
                'students.certificate_portal_sent_by',
            ]);
    }

    /** @return array<int, string> */
    public function recipientPhones(Student $student): array
    {
        return collect([$student->parent_phone_number, $student->phone_number])
            ->filter(static fn (mixed $phone): bool => is_string($phone) && trim($phone) !== '')
            ->map(static fn (string $phone): string => trim($phone))
            ->unique()
            ->values()
            ->all();
    }

    public function message(Student $student): string
    {
        $student->loadMissing('center:id,name,student_gender');
        $messageKey = $student->center?->student_gender === Center::STUDENT_GENDER_FEMALE
            ? 'certificates.portal_whatsapp_messages.female'
            : 'certificates.portal_whatsapp_messages.male';
        $centerName = trim((string) $student->center?->name);

        return Lang::get($messageKey, [
            'center' => $centerName !== '' ? $centerName : (string) config('app.name'),
            'student' => trim((string) $student->full_name),
            'portal_url' => $this->portals->url($student),
        ], 'ar');
    }

    private function freshCandidate(Student $student, CarbonInterface $cutoffUtc): ?Student
    {
        return $this->candidates(
            $cutoffUtc,
            activeOnly: true,
            studentId: (int) $student->id,
            limit: 1,
        )->first();
    }

    /** @param array<int, string> $phones */
    private function fingerprint(Student $student, array $phones): string
    {
        $certificateIds = $student->certificates
            ->map(static fn (Certificate $certificate): array => [
                (string) $certificate->public_id,
                $certificate->achieved_at?->toISOString(),
            ])
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'portal_id' => (string) $student->certificate_portal_id,
            'student_name' => (string) $student->full_name,
            'phones' => array_values($phones),
            'certificates' => $certificateIds,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function deliveryState(Student $student): string
    {
        $phones = $this->recipientPhones($student);
        if ($student->certificates->isEmpty()) {
            return 'pending';
        }

        $state = $this->matchingDeliveryState($student, $this->fingerprint($student, $phones));

        return match ($state) {
            Student::CERTIFICATE_PORTAL_DELIVERY_SENT => 'already_sent',
            Student::CERTIFICATE_PORTAL_DELIVERY_UNREGISTERED => 'unregistered',
            Student::CERTIFICATE_PORTAL_DELIVERY_REVIEW_REQUIRED => 'review_required',
            Student::CERTIFICATE_PORTAL_DELIVERY_PROCESSING => $this->processingIsStale($student)
                ? 'review_required'
                : 'processing',
            default => 'pending',
        };
    }

    private function matchingDeliveryState(Student $student, string $fingerprint): ?string
    {
        if (! is_string($student->certificate_portal_delivery_fingerprint)
            || ! hash_equals($student->certificate_portal_delivery_fingerprint, $fingerprint)) {
            return null;
        }

        return is_string($student->certificate_portal_delivery_status)
            ? $student->certificate_portal_delivery_status
            : null;
    }

    private function processingIsStale(Student $student): bool
    {
        return $student->certificate_portal_delivery_claimed_at === null
            || $student->certificate_portal_delivery_claimed_at->lte(
                now()->subMinutes(self::PROCESSING_STALE_AFTER_MINUTES),
            );
    }

    private function claim(Student $student, string $fingerprint): void
    {
        DB::transaction(function () use ($student, $fingerprint): void {
            Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->forceFill([
                    'certificate_portal_delivery_status' => Student::CERTIFICATE_PORTAL_DELIVERY_PROCESSING,
                    'certificate_portal_delivery_fingerprint' => $fingerprint,
                    'certificate_portal_delivery_claimed_at' => now(),
                    'certificate_portal_sent_at' => null,
                    'certificate_portal_sent_by' => null,
                ])->save();
        });
    }

    private function markSent(Student $student, string $fingerprint): void
    {
        DB::transaction(function () use ($student, $fingerprint): void {
            $lockedStudent = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (! hash_equals((string) $lockedStudent->certificate_portal_delivery_fingerprint, $fingerprint)) {
                return;
            }

            $lockedStudent->forceFill([
                'certificate_portal_delivery_status' => Student::CERTIFICATE_PORTAL_DELIVERY_SENT,
                'certificate_portal_delivery_claimed_at' => null,
                'certificate_portal_sent_at' => now(),
                'certificate_portal_sent_by' => Auth::id(),
            ])->save();

            activity('certificates')
                ->performedOn($lockedStudent)
                ->withProperties([
                    'action' => 'send_certificate_portal_whatsapp',
                    'certificate_portal_delivery_fingerprint' => $fingerprint,
                ])
                ->log('student certificate portal sent through WhatsApp');
        });
    }

    private function markUnregistered(Student $student, string $fingerprint): void
    {
        $this->updateClaimState(
            $student,
            $fingerprint,
            Student::CERTIFICATE_PORTAL_DELIVERY_UNREGISTERED,
        );
    }

    private function markReviewRequired(Student $student, string $fingerprint): void
    {
        $this->updateClaimState(
            $student,
            $fingerprint,
            Student::CERTIFICATE_PORTAL_DELIVERY_REVIEW_REQUIRED,
        );
    }

    private function releaseClaim(Student $student, string $fingerprint): void
    {
        $this->updateClaimState($student, $fingerprint, null, clearFingerprint: true);
    }

    private function updateClaimState(
        Student $student,
        string $fingerprint,
        ?string $status,
        bool $clearFingerprint = false,
    ): void {
        Student::query()
            ->whereKey($student->id)
            ->where('certificate_portal_delivery_fingerprint', $fingerprint)
            ->where('certificate_portal_delivery_status', Student::CERTIFICATE_PORTAL_DELIVERY_PROCESSING)
            ->update([
                'certificate_portal_delivery_status' => $status,
                'certificate_portal_delivery_fingerprint' => $clearFingerprint ? null : $fingerprint,
                'certificate_portal_delivery_claimed_at' => null,
                'certificate_portal_sent_at' => null,
                'certificate_portal_sent_by' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  (callable(array<string, int|string|null>): void)|null  $onProgress
     * @param  array<string, int|string|null>  $event
     */
    private function progress(?callable $onProgress, array $event): void
    {
        if ($onProgress !== null) {
            $onProgress($event);
        }
    }
}

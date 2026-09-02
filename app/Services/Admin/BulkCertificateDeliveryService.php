<?php

namespace App\Services\Admin;

use App\Models\Certificate;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class BulkCertificateDeliveryService
{
    public function __construct(
        private readonly StudentCertificateService $certificates,
        private readonly CertificateWhatsAppService $certificateWhatsApp,
        private readonly WhatsAppMessagingService $whatsAppMessaging,
    ) {}

    /**
     * Preview cumulative historical checkpoints inferred independently inside
     * every plan in which the student has completion evidence.
     *
     * The cutoff is exclusive and must be supplied in UTC by the caller.
     *
     * @return array{
     *     cutoff_utc: string,
     *     active_only: bool,
     *     limit: int|null,
     *     missing_certificates: array<string, mixed>,
     *     ready_to_send: array<string, mixed>,
     *     projected_delivery: array{certificates: int, students: int, configured_recipients: int},
     *     issued_delivery_states: array<string, int>
     * }
     */
    public function preview(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
        ?int $limit = null,
    ): array {
        $missing = $this->missingIssueCandidates($cutoffUtc, $activeOnly, $limit);
        $ready = $this->pendingSendQuery($cutoffUtc, $activeOnly, $limit)->get();
        $issued = $this->issuedCertificateQuery($cutoffUtc, $activeOnly, $limit)->get();
        $missingSummary = $this->transactionCohortSummary($missing);
        $readySummary = $this->certificateCohortSummary($ready);

        return [
            'cutoff_utc' => $cutoffUtc->toIso8601String(),
            'active_only' => $activeOnly,
            'limit' => $limit,
            'missing_certificates' => $missingSummary,
            'ready_to_send' => $readySummary,
            'projected_delivery' => [
                'certificates' => $missing->count() + $ready->count(),
                'students' => $missing->pluck('student_id')
                    ->merge($ready->pluck('student_id'))
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->count(),
                'configured_recipients' => (int) $missingSummary['configured_recipients']
                    + (int) $readySummary['configured_recipients'],
            ],
            'issued_delivery_states' => $this->deliveryStateSummary($issued),
        ];
    }

    /**
     * Issue every missing cumulative checkpoint backed by the earliest
     * completion event in the same plan that proves it was reached. A failure
     * for one record never stops the remaining records.
     *
     * @param  (callable(array<string, int|string|null>): void)|null  $onProgress
     * @return array{
     *     candidates: int,
     *     checked: int,
     *     issued: int,
     *     already_issued: int,
     *     failed: int,
     *     failures: array<int, array<string, int|string|null>>
     * }
     */
    public function issueMissing(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
        ?int $limit = null,
        ?callable $onProgress = null,
    ): array {
        $transactions = $this->missingIssueCandidates($cutoffUtc, $activeOnly, $limit);
        $summary = [
            'candidates' => $transactions->count(),
            'checked' => 0,
            'issued' => 0,
            'already_issued' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($transactions as $index => $transaction) {
            $summary['checked']++;
            $event = $this->transactionEvent($transaction, $index + 1, $transactions->count());

            try {
                $certificatePlanPointId = $this->candidatePlanPointId($transaction);
                $certificate = $this->certificates->issueFromCompletion(
                    $transaction,
                    $certificatePlanPointId,
                );
                $event['certificate_id'] = (int) $certificate->id;

                if ($certificate->wasRecentlyCreated) {
                    $summary['issued']++;
                    $event['status'] = 'issued';
                } else {
                    $summary['already_issued']++;
                    $event['status'] = 'already_issued';
                }
            } catch (Throwable $exception) {
                if ($this->certificateExistsFor($transaction, $this->candidatePlanPointId($transaction))) {
                    $summary['already_issued']++;
                    $event['status'] = 'already_issued';
                } else {
                    $summary['failed']++;
                    $event['status'] = 'failed';
                    $event['reason'] = $exception instanceof ValidationException
                        ? 'validation_failed'
                        : 'unexpected_error';
                    $summary['failures'][] = $this->failureItem($event);

                    Log::error('Bulk historical certificate issuance failed.', [
                        'transaction_id' => (int) $transaction->id,
                        'student_id' => (int) $transaction->student_id,
                        'evidence_plan_point_id' => (int) $transaction->plan_point_id,
                        'certificate_plan_point_id' => $this->candidatePlanPointId($transaction),
                        'exception_class' => $exception::class,
                    ]);
                }
            }

            if ($onProgress !== null) {
                $onProgress($event);
            }
        }

        return $summary;
    }

    /**
     * Send valid certificates whose saved achievement date precedes the cutoff.
     * Completed, partial, review, and fresh processing states are excluded.
     * Stale processing claims are selected only so the delivery service can
     * reconcile them to review without attempting another message.
     *
     * The WhatsApp pacing service runs only after a send result (including a
     * partial or uncertain result) and only when another candidate remains.
     *
     * @param  (callable(array<string, int|string|null>): void)|null  $onProgress
     * @return array{
     *     candidates: int,
     *     checked: int,
     *     attempted: int,
     *     sent: int,
     *     partial: int,
     *     review_required: int,
     *     already_sent: int,
     *     inactive: int,
     *     no_recipient: int,
     *     unregistered: int,
     *     validation_failed: int,
     *     failed: int,
     *     failures: array<int, array<string, int|string|null>>
     * }
     */
    public function sendPending(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
        ?int $limit = null,
        ?callable $onProgress = null,
    ): array {
        $certificates = $this->pendingSendQuery($cutoffUtc, $activeOnly, $limit)->get();
        $summary = [
            'candidates' => $certificates->count(),
            'checked' => 0,
            'attempted' => 0,
            'sent' => 0,
            'partial' => 0,
            'review_required' => 0,
            'already_sent' => 0,
            'inactive' => 0,
            'no_recipient' => 0,
            'unregistered' => 0,
            'validation_failed' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($certificates as $index => $certificate) {
            $summary['checked']++;
            $event = $this->certificateEvent($certificate, $index + 1, $certificates->count());
            $certificate = Certificate::query()->find($certificate->id);
            if (! $certificate instanceof Certificate) {
                $summary['failed']++;
                $event['status'] = 'failed';
                $event['reason'] = 'certificate_missing';
                $summary['failures'][] = $this->failureItem($event);
                if ($onProgress !== null) {
                    $onProgress($event);
                }

                continue;
            }

            $student = Student::query()
                ->whereKey($certificate->student_id)
                ->first([
                    'id',
                    'full_name',
                    'parent_phone_number',
                    'phone_number',
                    'is_active',
                ]);
            $sendProducedResult = false;
            $hasDeliveryState = $certificate->whatsapp_delivery_status !== null
                || $certificate->whatsapp_sent_at !== null;

            if (! $student instanceof Student) {
                $summary['failed']++;
                $event['status'] = 'failed';
                $event['reason'] = 'student_missing';
                $summary['failures'][] = $this->failureItem($event);
                if ($onProgress !== null) {
                    $onProgress($event);
                }

                continue;
            }

            if (! $hasDeliveryState && (int) $student->is_active !== Student::STATUS_ACTIVE) {
                $summary['inactive']++;
                $event['status'] = 'inactive';
                $event['reason'] = 'inactive';
                $summary['failures'][] = $this->failureItem($event);
                if ($onProgress !== null) {
                    $onProgress($event);
                }

                continue;
            }

            if (! $hasDeliveryState && $this->certificateWhatsApp->recipientPhones($student) === []) {
                $summary['no_recipient']++;
                $event['status'] = 'no_recipient';
                $summary['failures'][] = $this->failureItem($event);
                if ($onProgress !== null) {
                    $onProgress($event);
                }

                continue;
            }

            try {
                $summary['attempted']++;
                $result = $this->certificateWhatsApp->send($student, $certificate);
                $sendProducedResult = ! $result['already_sent'];

                if ($result['already_sent']) {
                    $summary['already_sent']++;
                    $event['status'] = 'already_sent';
                } elseif ($result['uncertain']) {
                    $summary['review_required']++;
                    $event['status'] = 'review_required';
                } elseif ($result['partial']) {
                    $summary['partial']++;
                    $event['status'] = 'partial';
                } else {
                    $summary['sent']++;
                    $event['status'] = 'sent';
                }

                $event['certificate_id'] = (int) $result['certificate']->id;
            } catch (Throwable $exception) {
                $reason = $this->sendFailureReason($exception);
                $summary[$reason]++;
                $event['status'] = $reason;
                $event['reason'] = $reason;
                $summary['failures'][] = $this->failureItem($event);

                Log::warning('Bulk certificate WhatsApp delivery failed.', [
                    'certificate_id' => (int) $certificate->id,
                    'student_id' => (int) $certificate->student_id,
                    'plan_point_id' => (int) $certificate->plan_point_id,
                    'failure_type' => $reason,
                    'exception_class' => $exception::class,
                ]);
            }

            if ($onProgress !== null) {
                $onProgress($event);
            }

            if ($sendProducedResult && $index < $certificates->count() - 1) {
                $this->whatsAppMessaging->waitBetweenMessages();
            }
        }

        return $summary;
    }

    /**
     * Build one candidate per cumulative certificate checkpoint. Completion
     * events are grouped by student and historical plan, so progress from a
     * transferred student's new plan can never advance an old plan (or the
     * reverse). Each checkpoint uses the earliest event in its own plan whose
     * point ordering reaches or passes it.
     *
     * @return EloquentCollection<int, StudentPointTransaction>
     */
    public function missingIssueCandidates(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
        ?int $limit = null,
    ): EloquentCollection {
        $this->assertValidLimit($limit);

        $evidenceTransactions = $this->completionEvidenceQuery($cutoffUtc, $activeOnly)->get()
            ->filter(static fn (StudentPointTransaction $transaction): bool => $transaction->student instanceof Student
                && $transaction->planPoint instanceof PlanPoint
                && (int) $transaction->planPoint->plan_id > 0
                && $transaction->created_at !== null
            )
            ->values();

        if ($evidenceTransactions->isEmpty()) {
            return new EloquentCollection;
        }

        $planIds = $evidenceTransactions
            ->map(static fn (StudentPointTransaction $transaction): int => (int) $transaction->planPoint?->plan_id
            )
            ->filter()
            ->unique()
            ->values();
        $checkpoints = PlanPoint::query()
            ->whereIn('plan_id', $planIds)
            ->where('requires_certificate', true)
            ->with('plan:id,name')
            ->orderBy('plan_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($checkpoints->isEmpty()) {
            return new EloquentCollection;
        }

        $studentIds = $evidenceTransactions
            ->pluck('student_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $existingLookup = Certificate::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('plan_point_id', $checkpoints->modelKeys())
            ->get(['student_id', 'plan_point_id'])
            ->mapWithKeys(static fn (Certificate $certificate): array => [
                self::studentPointKey(
                    (int) $certificate->student_id,
                    (int) $certificate->plan_point_id,
                ) => true,
            ]);
        $checkpointsByPlan = $checkpoints->groupBy(
            static fn (PlanPoint $checkpoint): int => (int) $checkpoint->plan_id,
        );
        $evidenceByStudentPlan = $evidenceTransactions->groupBy(
            static fn (StudentPointTransaction $transaction): string => self::studentPointKey(
                (int) $transaction->student_id,
                (int) $transaction->planPoint?->plan_id,
            ),
        );
        $candidates = [];

        foreach ($evidenceByStudentPlan as $transactions) {
            /** @var EloquentCollection<int, StudentPointTransaction> $transactions */
            $orderedEvidence = $transactions
                ->sort(static fn (
                    StudentPointTransaction $first,
                    StudentPointTransaction $second,
                ): int => [
                    $first->created_at?->getTimestamp() ?? PHP_INT_MAX,
                    (int) $first->id,
                ] <=> [
                    $second->created_at?->getTimestamp() ?? PHP_INT_MAX,
                    (int) $second->id,
                ])
                ->values();
            $firstEvidence = $orderedEvidence->first();
            if (! $firstEvidence instanceof StudentPointTransaction) {
                continue;
            }

            $planId = (int) $firstEvidence->planPoint?->plan_id;
            /** @var EloquentCollection<int, PlanPoint>|null $planCheckpoints */
            $planCheckpoints = $checkpointsByPlan->get($planId);
            if (! $planCheckpoints instanceof EloquentCollection) {
                continue;
            }

            foreach ($planCheckpoints as $checkpoint) {
                $studentId = (int) $firstEvidence->student_id;
                if ($existingLookup->has(self::studentPointKey($studentId, (int) $checkpoint->id))) {
                    continue;
                }

                $evidence = $orderedEvidence->first(
                    fn (StudentPointTransaction $transaction): bool => $this->transactionReachesCheckpoint($transaction, $checkpoint),
                );
                if (! $evidence instanceof StudentPointTransaction) {
                    continue;
                }

                $candidate = clone $evidence;
                $candidate->setAttribute('bulk_certificate_plan_point_id', (int) $checkpoint->id);
                $candidate->setAttribute('bulk_certificate_plan_id', (int) $checkpoint->plan_id);
                $candidate->setAttribute('bulk_certificate_sort_order', (int) $checkpoint->sort_order);
                $candidate->setRelation('bulkCertificatePlanPoint', $checkpoint);
                $candidate->setRelation('bulkCertificatePlan', $checkpoint->plan);
                $candidates[] = $candidate;
            }
        }

        usort($candidates, static fn (
            StudentPointTransaction $first,
            StudentPointTransaction $second,
        ): int => [
            (int) $first->getAttribute('bulk_certificate_plan_id'),
            (int) $first->getAttribute('bulk_certificate_sort_order'),
            (int) $first->getAttribute('bulk_certificate_plan_point_id'),
            (int) $first->student_id,
        ] <=> [
            (int) $second->getAttribute('bulk_certificate_plan_id'),
            (int) $second->getAttribute('bulk_certificate_sort_order'),
            (int) $second->getAttribute('bulk_certificate_plan_point_id'),
            (int) $second->student_id,
        ]);

        if ($limit !== null) {
            $candidates = array_slice($candidates, 0, $limit);
        }

        return new EloquentCollection($candidates);
    }

    /** @return Builder<StudentPointTransaction> */
    public function completionEvidenceQuery(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
    ): Builder {
        return StudentPointTransaction::query()
            ->select('student_point_transactions.*')
            ->join('students as bulk_students', 'bulk_students.id', '=', 'student_point_transactions.student_id')
            ->join('plan_points as evidence_plan_points', 'evidence_plan_points.id', '=', 'student_point_transactions.plan_point_id')
            ->where('student_point_transactions.type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
            ->where('student_point_transactions.created_at', '<', $cutoffUtc)
            ->when(
                $activeOnly,
                static fn (Builder $builder): Builder => $builder->where(
                    'bulk_students.is_active',
                    Student::STATUS_ACTIVE,
                ),
            )
            ->with([
                'student:id,full_name,parent_phone_number,phone_number,is_active',
                'planPoint:id,plan_id,sort_order,name',
                'planPoint.plan:id,name',
            ])
            ->orderBy('bulk_students.id')
            ->orderBy('evidence_plan_points.plan_id')
            ->orderBy('student_point_transactions.created_at')
            ->orderBy('student_point_transactions.id');
    }

    /** @return Builder<Certificate> */
    public function pendingSendQuery(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
        ?int $limit = null,
    ): Builder {
        return $this->issuedCertificateQuery($cutoffUtc, $activeOnly, $limit)
            ->where('certificates.status', Certificate::STATUS_VALID)
            ->whereNull('certificates.whatsapp_sent_at')
            ->where(function (Builder $query): void {
                $query->whereNull('certificates.whatsapp_delivery_status')
                    ->orWhere(function (Builder $stale): void {
                        $stale->where(
                            'certificates.whatsapp_delivery_status',
                            Certificate::WHATSAPP_DELIVERY_PROCESSING,
                        )->where(
                            'certificates.updated_at',
                            '<=',
                            now()->subMinutes(Certificate::WHATSAPP_PROCESSING_STALE_AFTER_MINUTES),
                        );
                    });
            });
    }

    /** @return Builder<Certificate> */
    public function issuedCertificateQuery(
        CarbonInterface $cutoffUtc,
        bool $activeOnly = true,
        ?int $limit = null,
    ): Builder {
        $this->assertValidLimit($limit);

        $query = Certificate::query()
            ->select('certificates.*')
            ->join('students as bulk_students', 'bulk_students.id', '=', 'certificates.student_id')
            ->leftJoin('plan_points as bulk_plan_points', 'bulk_plan_points.id', '=', 'certificates.plan_point_id')
            ->leftJoin('plan_types as bulk_plans', 'bulk_plans.id', '=', 'bulk_plan_points.plan_id')
            ->where('certificates.achieved_at', '<', $cutoffUtc)
            ->when(
                $activeOnly,
                static fn (Builder $builder): Builder => $builder->where(
                    'bulk_students.is_active',
                    Student::STATUS_ACTIVE,
                ),
            )
            ->with([
                'student:id,full_name,parent_phone_number,phone_number,is_active',
                'planPoint:id,plan_id,sort_order,name',
                'planPoint.plan:id,name',
            ])
            ->orderByRaw('CASE WHEN bulk_plans.id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('bulk_plans.id')
            ->orderBy('bulk_plan_points.sort_order')
            ->orderBy('bulk_plan_points.id')
            ->orderBy('bulk_students.id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query;
    }

    /**
     * @param  EloquentCollection<int, StudentPointTransaction>  $transactions
     * @return array<string, mixed>
     */
    private function transactionCohortSummary(EloquentCollection $transactions): array
    {
        return $this->cohortSummary(
            $transactions,
            static fn (StudentPointTransaction $transaction): ?Student => $transaction->student,
            static fn (StudentPointTransaction $transaction): mixed => $transaction->relationLoaded('bulkCertificatePlan')
                    ? $transaction->getRelation('bulkCertificatePlan')
                    : $transaction->planPoint?->plan,
        );
    }

    /**
     * @param  EloquentCollection<int, Certificate>  $certificates
     * @return array<string, mixed>
     */
    private function certificateCohortSummary(EloquentCollection $certificates): array
    {
        return $this->cohortSummary(
            $certificates,
            static fn (Certificate $certificate): ?Student => $certificate->student,
            static fn (Certificate $certificate): mixed => $certificate->planPoint?->plan,
        );
    }

    /**
     * @param  EloquentCollection<int, StudentPointTransaction>|EloquentCollection<int, Certificate>  $records
     * @param  callable(mixed): (Student|null)  $studentFor
     * @param  callable(mixed): mixed  $planFor
     * @return array<string, mixed>
     */
    private function cohortSummary(
        EloquentCollection $records,
        callable $studentFor,
        callable $planFor,
    ): array {
        $studentIds = [];
        $configuredRecipients = 0;
        $withoutConfiguredRecipient = 0;
        $plans = [];

        foreach ($records as $record) {
            $student = $studentFor($record);
            $plan = $planFor($record);
            $phones = $student instanceof Student
                ? $this->certificateWhatsApp->recipientPhones($student)
                : [];
            $recipientCount = count($phones);
            $studentId = (int) ($student?->id ?? 0);
            $planId = (int) ($plan?->id ?? 0);

            if ($studentId > 0) {
                $studentIds[$studentId] = true;
            }

            $configuredRecipients += $recipientCount;
            if ($recipientCount === 0) {
                $withoutConfiguredRecipient++;
            }

            $plans[$planId] ??= [
                'plan_id' => $planId,
                'plan_name' => (string) ($plan?->name ?? ''),
                'certificates' => 0,
                'students_lookup' => [],
                'configured_recipients' => 0,
                'without_configured_recipient' => 0,
            ];
            $plans[$planId]['certificates']++;
            $plans[$planId]['configured_recipients'] += $recipientCount;
            $plans[$planId]['without_configured_recipient'] += $recipientCount === 0 ? 1 : 0;
            if ($studentId > 0) {
                $plans[$planId]['students_lookup'][$studentId] = true;
            }
        }

        $planRows = collect($plans)
            ->map(static function (array $row): array {
                $row['student_ids'] = array_map('intval', array_keys($row['students_lookup']));
                $row['students'] = count($row['student_ids']);
                unset($row['students_lookup']);

                return $row;
            })
            ->values()
            ->all();

        return [
            'certificates' => $records->count(),
            'students' => count($studentIds),
            'configured_recipients' => $configuredRecipients,
            'without_configured_recipient' => $withoutConfiguredRecipient,
            'plans' => $planRows,
        ];
    }

    /**
     * @param  EloquentCollection<int, Certificate>  $certificates
     * @return array<string, int>
     */
    private function deliveryStateSummary(EloquentCollection $certificates): array
    {
        $summary = [
            'total' => $certificates->count(),
            'ready' => 0,
            'sent' => 0,
            'partial' => 0,
            'review_required' => 0,
            'processing' => 0,
            'invalid' => 0,
            'unknown' => 0,
        ];

        foreach ($certificates as $certificate) {
            if ($certificate->status !== Certificate::STATUS_VALID) {
                $summary['invalid']++;
            } elseif ($certificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_PARTIAL) {
                $summary['partial']++;
            } elseif ($certificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED) {
                $summary['review_required']++;
            } elseif ($certificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_PROCESSING) {
                $summary['processing']++;
            } elseif ($certificate->whatsapp_sent_at !== null
                || $certificate->whatsapp_delivery_status === Certificate::WHATSAPP_DELIVERY_SENT) {
                $summary['sent']++;
            } elseif ($certificate->whatsapp_delivery_status === null) {
                $summary['ready']++;
            } else {
                $summary['unknown']++;
            }
        }

        return $summary;
    }

    /** @return array<string, int|string|null> */
    private function transactionEvent(
        StudentPointTransaction $transaction,
        int $index,
        int $total,
    ): array {
        return [
            'phase' => 'issue',
            'index' => $index,
            'total' => $total,
            'transaction_id' => (int) $transaction->id,
            'certificate_id' => null,
            'student_id' => (int) $transaction->student_id,
            'plan_id' => (int) $transaction->getAttribute('bulk_certificate_plan_id'),
            'plan_point_id' => $this->candidatePlanPointId($transaction),
            'status' => null,
            'reason' => null,
        ];
    }

    /** @return array<string, int|string|null> */
    private function certificateEvent(Certificate $certificate, int $index, int $total): array
    {
        return [
            'phase' => 'send',
            'index' => $index,
            'total' => $total,
            'transaction_id' => null,
            'certificate_id' => (int) $certificate->id,
            'student_id' => (int) $certificate->student_id,
            'plan_id' => (int) $certificate->planPoint?->plan_id,
            'plan_point_id' => (int) $certificate->plan_point_id,
            'status' => null,
            'reason' => null,
        ];
    }

    /** @param array<string, int|string|null> $event
     * @return array<string, int|string|null>
     */
    private function failureItem(array $event): array
    {
        return [
            'transaction_id' => $event['transaction_id'],
            'certificate_id' => $event['certificate_id'],
            'student_id' => $event['student_id'],
            'plan_id' => $event['plan_id'],
            'plan_point_id' => $event['plan_point_id'],
            'reason' => $event['reason'] ?? $event['status'],
        ];
    }

    private function certificateExistsFor(
        StudentPointTransaction $transaction,
        int $certificatePlanPointId,
    ): bool {
        return Certificate::query()
            ->where('student_id', $transaction->student_id)
            ->where('plan_point_id', $certificatePlanPointId)
            ->exists();
    }

    private function candidatePlanPointId(StudentPointTransaction $transaction): int
    {
        return (int) ($transaction->getAttribute('bulk_certificate_plan_point_id')
            ?: $transaction->plan_point_id);
    }

    private function transactionReachesCheckpoint(
        StudentPointTransaction $transaction,
        PlanPoint $checkpoint,
    ): bool {
        $evidence = $transaction->planPoint;
        if (! $evidence instanceof PlanPoint
            || (int) $evidence->plan_id !== (int) $checkpoint->plan_id) {
            return false;
        }

        return (int) $checkpoint->sort_order < (int) $evidence->sort_order
            || ((int) $checkpoint->sort_order === (int) $evidence->sort_order
                && (int) $checkpoint->id <= (int) $evidence->id);
    }

    private static function studentPointKey(int $studentId, int $planPointId): string
    {
        return "{$studentId}:{$planPointId}";
    }

    private function sendFailureReason(Throwable $exception): string
    {
        if (! $exception instanceof ValidationException) {
            return 'failed';
        }

        $messages = collect($exception->errors())
            ->flatten()
            ->filter(static fn (mixed $message): bool => is_string($message));

        if ($messages->contains(fn (string $message): bool => $this->isUnregisteredMessage($message))) {
            return 'unregistered';
        }

        if ($messages->contains(static fn (string $message): bool => hash_equals(
            (string) __('certificates.whatsapp_no_phone'),
            $message,
        ))) {
            return 'no_recipient';
        }

        return 'validation_failed';
    }

    private function isUnregisteredMessage(string $message): bool
    {
        $marker = '__BULK_CERTIFICATE_PHONE_NUMBERS__';
        $template = (string) __('whatsapp.numbers_not_registered', ['numbers' => $marker]);
        [$prefix, $suffix] = array_pad(explode($marker, $template, 2), 2, '');

        return str_starts_with($message, $prefix) && str_ends_with($message, $suffix);
    }

    private function assertValidLimit(?int $limit): void
    {
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('The bulk certificate limit must be a positive integer.');
        }
    }
}

<?php

namespace App\Services\Admin\AbsenceRules;

use App\Exceptions\WhatsAppMessageSendException;
use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Student;
use App\Services\Admin\AbsenceRules\Actions\DismissStudentAction;
use App\Services\Admin\AbsenceRules\Actions\FreezeStudentAction;
use App\Services\Admin\AbsenceRules\Actions\SendMessageAction;
use App\Services\Admin\AbsenceRules\Contracts\RuleActionHandler;
use App\Services\Admin\AdminDataScopeService;
use App\Services\Admin\WhatsAppMessagingService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class AbsenceRuleEngine
{
    /** @var array<int, string> */
    private const ATTENDANCE_TYPE_MAP = [
        EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE => AbsenceRule::ATTENDANCE_TYPE_EXCUSED_ABSENCE,
        EvaluationStudent::ATTENDANCE_ABSENCE => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
    ];

    /** @var array<string, string> */
    private const ATTENDANCE_AR_LABELS = [
        AbsenceRule::ATTENDANCE_TYPE_EXCUSED_ABSENCE => 'غياب بعذر',
        AbsenceRule::ATTENDANCE_TYPE_ABSENCE => 'غياب بدون عذر',
    ];

    /** @var array<string, int> */
    private const WEEKDAY_NUMBERS = [
        'sunday' => 0,
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
    ];

    /** @var array<string, RuleActionHandler> */
    private array $handlers = [];

    public function __construct(
        private readonly MessageTemplateRenderer $templateRenderer,
        private readonly AdminDataScopeService $dataScope,
        private readonly WhatsAppMessagingService $whatsAppMessagingService,
        private readonly AbsenceAlertExecutionLock $executionLock,
        SendMessageAction $sendMessageAction,
        FreezeStudentAction $freezeStudentAction,
        DismissStudentAction $dismissStudentAction,
    ) {
        $availableHandlers = [
            $sendMessageAction,
            $freezeStudentAction,
            $dismissStudentAction,
        ];

        foreach ($availableHandlers as $handler) {
            $this->handlers[$handler->key()] = $handler;
        }
    }

    /**
     * @return array{
     *     processed: int,
     *     skipped: int,
     *     errors: array<int, string>,
     *     alerts_marked_as_sent: bool,
     *     local_preview: bool,
     *     preview_messages: array<int, array<string, mixed>>
     * }
     */
    public function processEvaluation(int $evaluationId, ?int $executedBy = null): array
    {
        $evaluation = Evaluation::query()
            ->with(['center', 'group.center'])
            ->find($evaluationId);

        if (! $evaluation) {
            return [
                'processed' => 0,
                'skipped' => 0,
                'errors' => ["Evaluation {$evaluationId} not found."],
                'alerts_marked_as_sent' => false,
                'local_preview' => false,
                'preview_messages' => [],
            ];
        }

        return $this->process($evaluation, $executedBy);
    }

    /**
     * @return array{
     *     processed: int,
     *     skipped: int,
     *     errors: array<int, string>,
     *     alerts_marked_as_sent: bool,
     *     local_preview: bool,
     *     preview_messages: array<int, array<string, mixed>>
     * }
     */
    public function process(Evaluation $evaluation, ?int $executedBy = null): array
    {
        $lock = $this->executionLock->acquire((int) $evaluation->id);
        if ($lock === null) {
            return [
                'processed' => 0,
                'skipped' => 0,
                'errors' => [__('evaluations.absence_alerts_processing')],
                'alerts_marked_as_sent' => false,
                'local_preview' => false,
                'preview_messages' => [],
            ];
        }

        try {
            $freshEvaluation = Evaluation::query()
                ->with(['center', 'group.center'])
                ->find($evaluation->id);

            if (! $freshEvaluation) {
                return [
                    'processed' => 0,
                    'skipped' => 0,
                    'errors' => ["Evaluation {$evaluation->id} not found."],
                    'alerts_marked_as_sent' => false,
                    'local_preview' => false,
                    'preview_messages' => [],
                ];
            }

            return $this->processWhileLocked($freshEvaluation, $lock, $executedBy);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{
     *     processed: int,
     *     skipped: int,
     *     errors: array<int, string>,
     *     alerts_marked_as_sent: bool,
     *     local_preview: bool,
     *     preview_messages: array<int, array<string, mixed>>
     * }
     */
    private function processWhileLocked(Evaluation $evaluation, Lock $lock, ?int $executedBy = null): array
    {
        $evaluation->loadMissing(['center', 'group.center']);

        if ($evaluation->is_send_absence_alerts) {
            return [
                'processed' => 0,
                'skipped' => 0,
                'errors' => [],
                'alerts_marked_as_sent' => true,
                'local_preview' => false,
                'preview_messages' => [],
            ];
        }

        $processed = 0;
        $skipped = 0;
        $errors = [];
        $localPreviewMessages = [];
        $isLocalPreviewRun = app()->environment('local');
        $evaluationDateRaw = (string) ($evaluation->date ?? '');

        try {
            $evaluationDate = CarbonImmutable::parse($evaluationDateRaw);
        } catch (Throwable) {
            return [
                'processed' => 0,
                'skipped' => 0,
                'errors' => ["Evaluation {$evaluation->id} has invalid date value."],
                'alerts_marked_as_sent' => false,
                'local_preview' => false,
                'preview_messages' => [],
            ];
        }

        $items = EvaluationStudent::query()
            ->join('students as scope_students', 'evaluations_users.student_id', '=', 'scope_students.id')
            ->where('evaluation_id', $evaluation->id)
            ->whereIn('attendances', array_keys(self::ATTENDANCE_TYPE_MAP))
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'scope_students'))
            ->select('evaluations_users.*')
            ->get();

        foreach ($items as $item) {
            if (! $this->executionLock->refresh($lock)) {
                $errors[] = __('evaluations.absence_alert_lock_lost');

                break;
            }

            $attendanceValue = (int) $item->attendances;
            $attendanceType = self::ATTENDANCE_TYPE_MAP[$attendanceValue] ?? null;

            if ($attendanceType === null) {
                $skipped++;

                continue;
            }

            $studentId = $item->resolvedStudentId();
            if ($studentId === null) {
                $skipped++;
                $errors[] = "Evaluation item {$item->id}: missing student reference.";

                continue;
            }

            $student = Student::query()
                ->with('center')
                ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
                ->find($studentId);
            if (! $student) {
                $skipped++;
                $errors[] = "Evaluation item {$item->id}: student {$studentId} not found.";

                continue;
            }

            $previousExecution = $this->previousExecution($item);
            $previousExecutionState = $previousExecution['state'];
            $retryLog = $previousExecution['log'];

            if ($previousExecutionState === 'completed') {
                $skipped++;

                continue;
            }

            if ($previousExecutionState === 'review') {
                $skipped++;
                $errors[] = __('evaluations.absence_alert_requires_review', ['item' => $item->id]);

                continue;
            }

            $groupId = $evaluation->group_id !== null ? (int) $evaluation->group_id : null;
            if ($groupId !== null && $groupId <= 0) {
                $groupId = null;
            }

            $centerId = $evaluation->group?->center_id ?? $student->center_id ?? $evaluation->center_id;
            $centerId = $centerId !== null ? (int) $centerId : null;
            if ($centerId !== null && $centerId <= 0) {
                $centerId = null;
            }

            $occurrence = $this->countMonthlyOccurrence(
                studentId: $student->id,
                attendanceValue: $attendanceValue,
                evaluationDate: $evaluationDate,
                groupId: $groupId,
            );

            if ($occurrence <= 0) {
                $skipped++;

                if ($previousExecutionState === 'retry') {
                    $errors[] = __('evaluations.absence_alert_retry_unresolved', ['item' => $item->id]);
                }

                continue;
            }

            $ruleQuery = AbsenceRule::query()
                ->active()
                ->with('messageTemplate')
                ->where('attendance_type', $attendanceType)
                ->where('occurrence_number', $occurrence);

            if ($centerId === null) {
                $ruleQuery->whereNull('center_id');
            } else {
                $ruleQuery
                    ->where(function ($query) use ($centerId): void {
                        $query->where('center_id', $centerId)
                            ->orWhereNull('center_id');
                    })
                    ->orderByRaw('CASE WHEN center_id = ? THEN 0 ELSE 1 END', [$centerId]);
            }

            $this->dataScope->applyAbsenceRuleAccess($ruleQuery, 'absence_rules');

            $rule = $ruleQuery->first();

            if (! $rule) {
                $skipped++;

                if ($previousExecutionState === 'retry') {
                    $errors[] = __('evaluations.absence_alert_retry_unresolved', ['item' => $item->id]);
                }

                continue;
            }

            $templateSnapshot = $rule->messageTemplate?->content;
            $freezeWindow = $this->buildFreezeWindow(
                evaluation: $evaluation,
                evaluationDate: $evaluationDate,
                student: $student,
                rule: $rule,
                attendanceType: $attendanceType,
                occurrence: $occurrence,
            );
            $templateContext = $this->buildTemplateContext(
                evaluation: $evaluation,
                evaluationDateValue: $evaluationDate,
                student: $student,
                rule: $rule,
                attendanceType: $attendanceType,
                attendanceValue: $attendanceValue,
                occurrence: $occurrence,
                freezeWindow: $freezeWindow,
            );
            $messageContent = $templateSnapshot !== null
                ? $this->templateRenderer->render($templateSnapshot, $templateContext)
                : null;
            $recipientPhones = $this->extractUniquePhones($student);

            $context = new RuleExecutionContext(
                rule: $rule,
                evaluation: $evaluation,
                evaluationStudent: $item,
                student: $student,
                attendanceType: $attendanceType,
                attendanceValue: $attendanceValue,
                occurrenceNumber: $occurrence,
                messageTemplateSnapshot: $templateSnapshot,
                messageContent: $messageContent,
                recipientPhones: $recipientPhones,
                groupSerialized: $this->evaluationGroupSerialized($evaluation, $student),
                freezeFrom: Arr::get($freezeWindow, 'from'),
                freezeTo: Arr::get($freezeWindow, 'to'),
                freezeReason: Arr::get($freezeWindow, 'reason'),
                centerContactPhone: Arr::get($freezeWindow, 'contact_phone'),
                executedBy: $executedBy,
            );

            $claimMeta = [
                'processing' => true,
                'processing_started_at' => now()->toIso8601String(),
                'group_serialized' => $context->shouldSendToGroup()
                    ? $context->groupSerialized
                    : null,
            ];
            $claimValues = [
                'evaluation_id' => $evaluation->id,
                'student_id' => $student->id,
                'center_id' => $centerId,
                'absence_rule_id' => $rule->id,
                'message_template_id' => $rule->message_template_id,
                'attendance_type' => $attendanceType,
                'attendance_value' => $attendanceValue,
                'occurrence_number' => $occurrence,
                'action' => $rule->action,
                'recipient_phones' => $recipientPhones,
                'message_template_snapshot' => $templateSnapshot,
                'message_content' => $messageContent,
                'sent_to_group' => $rule->send_to_center_group,
                'was_message_sent' => false,
                'student_was_frozen' => false,
                'student_was_dismissed' => false,
                'student_freeze_id' => null,
                'deduction_points_count' => 0,
                'executed_by' => $executedBy,
                'executed_at' => now(),
                'meta' => $claimMeta,
            ];

            if ($previousExecutionState === 'retry' && $retryLog !== null) {
                $retryLog->update($claimValues);
                $log = $retryLog->refresh();
            } else {
                $log = AbsenceRuleExecutionLog::query()->firstOrCreate(
                    ['evaluation_student_id' => $item->id],
                    $claimValues,
                );

                if (! $log->wasRecentlyCreated) {
                    $skipped++;
                    $errors[] = __('evaluations.absence_alert_requires_review', ['item' => $item->id]);

                    continue;
                }
            }

            try {
                $result = $this->resolveHandler($rule->action)->execute($context);
                $isLocalPreview = (bool) ($result->meta['local_preview'] ?? false);

                $log->update([
                    'was_message_sent' => $result->wasMessageSent,
                    'student_was_frozen' => $result->studentWasFrozen,
                    'student_was_dismissed' => $result->studentWasDismissed,
                    'student_freeze_id' => $result->studentFreezeId,
                    'deduction_points_count' => $result->deductedPointsCount,
                    'executed_by' => $executedBy,
                    'executed_at' => now(),
                    'meta' => array_merge($result->meta, ['processing' => false]),
                ]);
                $log->refresh();

                if ($isLocalPreview) {
                    $localPreviewMessages[] = $this->previewMessagePayload($log, $student, $evaluation);
                }

                if ($previousExecutionState === 'retry' && ! $result->wasMessageSent && ! $isLocalPreview) {
                    $skipped++;
                    $errors[] = __('evaluations.absence_alert_retry_unresolved', ['item' => $item->id]);
                } else {
                    $processed++;
                }
            } catch (Throwable $exception) {
                $errors[] = "Evaluation item {$item->id}: {$exception->getMessage()}";
                $skipped++;

                $failureMeta = [
                    'error' => $exception->getMessage(),
                    'processing' => false,
                    'group_serialized' => $context->shouldSendToGroup()
                        ? $context->groupSerialized
                        : null,
                ];

                if ($exception instanceof WhatsAppMessageSendException) {
                    $failureMeta = array_merge(
                        $failureMeta,
                        $this->whatsAppMessagingService->deliveryFailureMeta(
                            $context->recipientPhones,
                            $context->shouldSendToGroup() ? $context->groupSerialized : null,
                            $exception,
                        ),
                    );
                } else {
                    $failureMeta['delivery_unknown'] = true;
                }

                $log->update([
                    'was_message_sent' => false,
                    'student_was_frozen' => false,
                    'student_was_dismissed' => false,
                    'student_freeze_id' => null,
                    'deduction_points_count' => 0,
                    'executed_by' => $executedBy,
                    'executed_at' => now(),
                    'meta' => $failureMeta,
                ]);
            }
        }

        $alertsMarkedAsSent = $errors === [] && ! $this->dataScope->shouldScope() && ! $isLocalPreviewRun;
        if ($alertsMarkedAsSent) {
            $evaluation->update(['is_send_absence_alerts' => true]);
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'alerts_marked_as_sent' => $alertsMarkedAsSent,
            'local_preview' => $isLocalPreviewRun,
            'preview_messages' => $localPreviewMessages,
        ];
    }

    /**
     * @return array{state: 'none'|'retry'|'completed'|'review', log: AbsenceRuleExecutionLog|null}
     */
    private function previousExecution(EvaluationStudent $item): array
    {
        $logs = AbsenceRuleExecutionLog::query()
            ->where('evaluation_student_id', $item->id)
            ->oldest('id')
            ->get();

        $previewLog = $logs
            ->first(static fn (AbsenceRuleExecutionLog $log): bool => (bool) (($log->meta ?? [])['local_preview'] ?? false));
        $logs = $logs
            ->reject(static fn (AbsenceRuleExecutionLog $log): bool => (bool) (($log->meta ?? [])['local_preview'] ?? false))
            ->values();

        if ($logs->isEmpty()) {
            return $previewLog instanceof AbsenceRuleExecutionLog
                ? ['state' => 'retry', 'log' => $previewLog]
                : ['state' => 'none', 'log' => null];
        }

        if ($logs->contains(static fn (AbsenceRuleExecutionLog $log): bool => $log->was_message_sent
            || $log->student_was_frozen
            || $log->student_was_dismissed
            || $log->deduction_points_count > 0)) {
            return ['state' => 'completed', 'log' => null];
        }

        if ($logs->contains(static function (AbsenceRuleExecutionLog $log): bool {
            $meta = $log->meta ?? [];
            if ((bool) ($meta['processing'] ?? false)) {
                return false;
            }

            $error = $meta['error'] ?? null;

            return ! is_string($error) || trim($error) === '';
        })) {
            return ['state' => 'completed', 'log' => null];
        }

        if ($logs->every(fn (AbsenceRuleExecutionLog $log): bool => $this->isKnownZeroDeliveryFailure($log))) {
            return ['state' => 'retry', 'log' => $logs->last()];
        }

        return ['state' => 'review', 'log' => null];
    }

    private function isKnownZeroDeliveryFailure(AbsenceRuleExecutionLog $log): bool
    {
        $meta = $log->meta ?? [];
        $deliveredChatIds = $meta['delivered_chat_ids'] ?? [];
        $remainingChatIds = $meta['remaining_chat_ids'] ?? [];

        if (! is_array($deliveredChatIds) || $deliveredChatIds !== [] || ! is_array($remainingChatIds)) {
            return false;
        }

        $groupSerialized = array_key_exists('group_serialized', $meta)
            ? $meta['group_serialized']
            : null;
        $intendedChatIds = $this->whatsAppMessagingService->recipientChatIds(
            is_array($log->recipient_phones) ? $log->recipient_phones : [],
            is_string($groupSerialized) ? $groupSerialized : null,
        );

        $remainingChatIds = array_values(array_unique(array_map(
            static fn (mixed $chatId): string => trim((string) $chatId),
            $remainingChatIds,
        )));
        sort($intendedChatIds);
        sort($remainingChatIds);

        return $intendedChatIds !== [] && $intendedChatIds === $remainingChatIds;
    }

    private function resolveHandler(string $action): RuleActionHandler
    {
        $handler = $this->handlers[$action] ?? null;
        if ($handler) {
            return $handler;
        }

        if ($action === AbsenceRule::ACTION_SEND_MESSAGE_AND_FREEZE) {
            return $this->handlers[AbsenceRule::ACTION_FREEZE_STUDENT];
        }

        throw new RuntimeException("Absence rule action [{$action}] is not supported.");
    }

    private function countMonthlyOccurrence(
        int $studentId,
        int $attendanceValue,
        CarbonImmutable $evaluationDate,
        ?int $groupId,
    ): int {
        return EvaluationStudent::query()
            ->join('evaluations', 'evaluations_users.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations_users.attendances', $attendanceValue)
            ->when($groupId !== null, static fn ($query) => $query->where('evaluations.group_id', $groupId))
            ->where(function ($query) use ($studentId): void {
                $query->where('evaluations_users.student_id', $studentId)
                    ->orWhere(function ($inner) use ($studentId): void {
                        $inner->whereNull('evaluations_users.student_id')
                            ->where('evaluations_users.user_id', $studentId);
                    });
            })
            ->whereMonth('evaluations.date', $evaluationDate->month)
            ->whereYear('evaluations.date', $evaluationDate->year)
            ->whereDate('evaluations.date', '<=', $evaluationDate->toDateString())
            ->count();
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, reason: string, contact_phone: string}|array<string, never>
     */
    private function buildFreezeWindow(
        Evaluation $evaluation,
        CarbonImmutable $evaluationDate,
        Student $student,
        AbsenceRule $rule,
        string $attendanceType,
        int $occurrence,
    ): array {
        if ($rule->action !== AbsenceRule::ACTION_FREEZE_STUDENT) {
            return [];
        }

        $from = $evaluationDate->addDay();
        $workingDays = $this->normalizeWorkingDays($this->evaluationWorkingDays($evaluation, $student));
        $to = $this->calculateFreezeToDate(
            from: $from,
            workingDays: $workingDays,
            workingDaysCount: max(1, (int) $rule->freeze_working_days_count),
        );

        $reason = trim((string) $rule->freeze_reason);
        if ($reason === '') {
            $reason = self::ATTENDANCE_AR_LABELS[$attendanceType]." - تكرار رقم {$occurrence}";
        }

        return [
            'from' => $from,
            'to' => $to,
            'reason' => $reason,
            'contact_phone' => (string) ($this->evaluationCenter($evaluation, $student)?->phone ?? ''),
        ];
    }

    /**
     * @param  array<int, mixed>|null  $workingDays
     * @return array<int, int>
     */
    private function normalizeWorkingDays(?array $workingDays): array
    {
        $values = Collection::make($workingDays ?? [])
            ->map(static function ($value): ?int {
                if (is_string($value)) {
                    $normalized = strtolower(trim($value));
                    if (array_key_exists($normalized, self::WEEKDAY_NUMBERS)) {
                        return self::WEEKDAY_NUMBERS[$normalized];
                    }

                    if (! ctype_digit($normalized)) {
                        return null;
                    }

                    return (int) $normalized;
                }

                return is_numeric($value) ? (int) $value : null;
            })
            ->filter(static fn (?int $value): bool => $value !== null && $value >= 0 && $value <= 6)
            ->unique()
            ->values()
            ->all();

        if ($values === []) {
            return [0, 1, 2, 3, 4, 5, 6];
        }

        return $values;
    }

    private function evaluationCenter(Evaluation $evaluation, Student $student): ?Center
    {
        return $evaluation->group?->center
            ?? $student->center
            ?? $evaluation->center;
    }

    /**
     * @return array<int, mixed>|null
     */
    private function evaluationWorkingDays(Evaluation $evaluation, Student $student): ?array
    {
        foreach ([
            $evaluation->group?->working_days,
            $evaluation->group?->center?->working_days,
            $student->center?->working_days,
            $evaluation->center?->working_days,
        ] as $workingDays) {
            if (is_array($workingDays) && $workingDays !== []) {
                return $workingDays;
            }
        }

        return null;
    }

    private function evaluationGroupSerialized(Evaluation $evaluation, Student $student): ?string
    {
        if ($evaluation->group !== null) {
            $groupSerialized = $evaluation->group->group_serialized;

            return is_string($groupSerialized) && trim($groupSerialized) !== ''
                ? $groupSerialized
                : null;
        }

        foreach ([$student->center?->group_serialized, $evaluation->center?->group_serialized] as $groupSerialized) {
            if (is_string($groupSerialized) && trim($groupSerialized) !== '') {
                return $groupSerialized;
            }
        }

        return null;
    }

    private function calculateFreezeToDate(
        CarbonImmutable $from,
        array $workingDays,
        int $workingDaysCount,
    ): CarbonImmutable {
        $cursor = $from;
        $matched = [];

        while (count($matched) < $workingDaysCount) {
            if (in_array($cursor->dayOfWeek, $workingDays, true)) {
                $matched[] = $cursor;
            }

            $cursor = $cursor->addDay();
        }

        /** @var CarbonImmutable $lastMatched */
        return Arr::last($matched);
    }

    /**
     * @return array<int, string>
     */
    private function extractUniquePhones(Student $student): array
    {
        $unique = [];

        foreach ([$student->parent_phone_number, $student->phone_number] as $phone) {
            if (! is_string($phone)) {
                continue;
            }

            $normalized = preg_replace('/\D+/', '', $phone) ?? '';
            if ($normalized === '') {
                continue;
            }

            $unique[$normalized] = $normalized;
        }

        return array_values($unique);
    }

    /**
     * @return array<string, mixed>
     */
    private function previewMessagePayload(
        AbsenceRuleExecutionLog $log,
        Student $student,
        Evaluation $evaluation,
    ): array {
        $meta = $log->meta ?? [];
        $center = $this->evaluationCenter($evaluation, $student);

        return [
            'id' => (int) $log->id,
            'student_name' => $student->full_name,
            'center_name' => $center?->name ?? '',
            'evaluation_id' => (int) $log->evaluation_id,
            'attendance_type' => $log->attendance_type,
            'attendance_label' => self::ATTENDANCE_AR_LABELS[$log->attendance_type] ?? $log->attendance_type,
            'occurrence_number' => $log->occurrence_number,
            'action' => $log->action,
            'recipient_phones' => $log->recipient_phones ?? [],
            'sent_to_group' => (bool) $log->sent_to_group,
            'group_serialized' => $meta['group_serialized'] ?? null,
            'message_content' => $log->message_content,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $freezeWindow
     * @return array<string, mixed>
     */
    private function buildTemplateContext(
        Evaluation $evaluation,
        CarbonImmutable $evaluationDateValue,
        Student $student,
        AbsenceRule $rule,
        string $attendanceType,
        int $attendanceValue,
        int $occurrence,
        array $freezeWindow,
    ): array {
        $evaluationDate = $evaluationDateValue->locale('ar');
        /** @var CarbonImmutable|null $freezeFrom */
        $freezeFrom = Arr::get($freezeWindow, 'from');
        /** @var CarbonImmutable|null $freezeTo */
        $freezeTo = Arr::get($freezeWindow, 'to');
        $studentDeductedPoints = (int) $student->deducted_points_count;
        $ruleDeductionPoints = (int) $rule->deduction_points_count;
        $center = $this->evaluationCenter($evaluation, $student);
        $group = $evaluation->group;

        return [
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'parent_phone_number' => $student->parent_phone_number,
                'phone_number' => $student->phone_number,
                'deducted_points_count' => $studentDeductedPoints,
                'deducted_points_after' => $studentDeductedPoints + $ruleDeductionPoints,
            ],
            'center' => [
                'id' => $center?->id ?? $evaluation->center_id,
                'name' => $center?->name ?? '',
                'phone' => $center?->phone ?? '',
            ],
            'group' => [
                'id' => $group?->id,
                'name' => $group?->name ?? '',
                'group_serialized' => $group?->group_serialized,
            ],
            'evaluation' => [
                'id' => $evaluation->id,
                'group_id' => $evaluation->group_id,
                'date_iso' => $evaluationDate->toDateString(),
                'date_ar' => $evaluationDate->translatedFormat('j F ، Y'),
                'day_ar' => $evaluationDate->translatedFormat('l'),
            ],
            'attendance' => [
                'type' => $attendanceType,
                'value' => $attendanceValue,
                'occurrence_number' => $occurrence,
                'label_ar' => self::ATTENDANCE_AR_LABELS[$attendanceType] ?? $attendanceType,
            ],
            'rule' => [
                'id' => $rule->id,
                'action' => $rule->action,
                'deduction_points_count' => $ruleDeductionPoints,
            ],
            'freeze' => [
                'from_iso' => $freezeFrom?->toDateString(),
                'to_iso' => $freezeTo?->toDateString(),
                'from_ar' => $freezeFrom?->locale('ar')->translatedFormat('l ، j F ، Y'),
                'to_ar' => $freezeTo?->locale('ar')->translatedFormat('l ، j F ، Y'),
                'reason' => Arr::get($freezeWindow, 'reason'),
                'contact_phone' => Arr::get($freezeWindow, 'contact_phone'),
            ],

            // Flat aliases for backward-compatible templates.
            'full_name' => $student->full_name,
            'day' => $evaluationDate->translatedFormat('l'),
            'date' => $evaluationDate->translatedFormat('j F ، Y'),
            'center_name' => $center?->name ?? '',
            'group_name' => $group?->name ?? '',
            'occurrence_number' => $occurrence,
            'attendance_type' => $attendanceType,
            'attendance_label_ar' => self::ATTENDANCE_AR_LABELS[$attendanceType] ?? $attendanceType,
            'deduction_points_count' => $ruleDeductionPoints,
            'student_deducted_points_count' => $studentDeductedPoints,
            'student_deducted_points_after' => $studentDeductedPoints + $ruleDeductionPoints,
            'freeze_from' => $freezeFrom?->locale('ar')->translatedFormat('l ، j F ، Y'),
            'freeze_to' => $freezeTo?->locale('ar')->translatedFormat('l ، j F ، Y'),
            'freeze_reason' => Arr::get($freezeWindow, 'reason'),
            'center_phone' => Arr::get($freezeWindow, 'contact_phone'),
        ];
    }
}

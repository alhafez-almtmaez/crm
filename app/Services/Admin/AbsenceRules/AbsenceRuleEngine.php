<?php

namespace App\Services\Admin\AbsenceRules;

use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Student;
use App\Services\Admin\AbsenceRules\Actions\DismissStudentAction;
use App\Services\Admin\AbsenceRules\Actions\FreezeStudentAction;
use App\Services\Admin\AbsenceRules\Contracts\RuleActionHandler;
use Carbon\CarbonImmutable;
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

    /** @var array<string, RuleActionHandler> */
    private array $handlers = [];

    public function __construct(
        private readonly MessageTemplateRenderer $templateRenderer,
        FreezeStudentAction $freezeStudentAction,
        DismissStudentAction $dismissStudentAction,
    )
    {
        $availableHandlers = [
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
     *     alerts_marked_as_sent: bool
     * }
     */
    public function processEvaluation(int $evaluationId, ?int $executedBy = null): array
    {
        $evaluation = Evaluation::query()
            ->with('center')
            ->find($evaluationId);

        if (!$evaluation) {
            return [
                'processed' => 0,
                'skipped' => 0,
                'errors' => ["Evaluation {$evaluationId} not found."],
                'alerts_marked_as_sent' => false,
            ];
        }

        return $this->process($evaluation, $executedBy);
    }

    /**
     * @return array{
     *     processed: int,
     *     skipped: int,
     *     errors: array<int, string>,
     *     alerts_marked_as_sent: bool
     * }
     */
    public function process(Evaluation $evaluation, ?int $executedBy = null): array
    {
        if ($evaluation->is_send_absence_alerts) {
            return [
                'processed' => 0,
                'skipped' => 0,
                'errors' => [],
                'alerts_marked_as_sent' => true,
            ];
        }

        $processed = 0;
        $skipped = 0;
        $errors = [];
        $evaluationDateRaw = (string) ($evaluation->date ?? '');

        try {
            $evaluationDate = CarbonImmutable::parse($evaluationDateRaw);
        } catch (Throwable) {
            return [
                'processed' => 0,
                'skipped' => 0,
                'errors' => ["Evaluation {$evaluation->id} has invalid date value."],
                'alerts_marked_as_sent' => false,
            ];
        }

        $items = EvaluationStudent::query()
            ->where('evaluation_id', $evaluation->id)
            ->whereIn('attendances', array_keys(self::ATTENDANCE_TYPE_MAP))
            ->get();

        foreach ($items as $item) {
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

            $student = Student::query()->with('center')->find($studentId);
            if (!$student) {
                $skipped++;
                $errors[] = "Evaluation item {$item->id}: student {$studentId} not found.";
                continue;
            }

            $centerId = $student->center_id ?? $evaluation->center_id;
            $centerId = $centerId !== null ? (int) $centerId : null;
            if ($centerId !== null && $centerId <= 0) {
                $centerId = null;
            }

            $occurrence = $this->countMonthlyOccurrence(
                studentId: $student->id,
                attendanceValue: $attendanceValue,
                evaluationDate: $evaluationDate,
            );

            if ($occurrence <= 0) {
                $skipped++;
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

            $rule = $ruleQuery->first();

            if (!$rule) {
                $skipped++;
                continue;
            }

            $templateSnapshot = $rule->messageTemplate?->content;
            $freezeWindow = $this->buildFreezeWindow($evaluationDate, $student, $rule, $attendanceType, $occurrence);
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
                groupSerialized: $student->center?->group_serialized,
                freezeFrom: Arr::get($freezeWindow, 'from'),
                freezeTo: Arr::get($freezeWindow, 'to'),
                freezeReason: Arr::get($freezeWindow, 'reason'),
                centerContactPhone: Arr::get($freezeWindow, 'contact_phone'),
                executedBy: $executedBy,
            );

            try {
                $result = $this->resolveHandler($rule->action)->execute($context);

                AbsenceRuleExecutionLog::query()->create([
                    'evaluation_id' => $evaluation->id,
                    'evaluation_student_id' => $item->id,
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
                    'was_message_sent' => $result->wasMessageSent,
                    'student_was_frozen' => $result->studentWasFrozen,
                    'student_was_dismissed' => $result->studentWasDismissed,
                    'student_freeze_id' => $result->studentFreezeId,
                    'deduction_points_count' => $result->deductedPointsCount,
                    'executed_by' => $executedBy,
                    'executed_at' => now(),
                    'meta' => $result->meta,
                ]);

                $processed++;
            } catch (Throwable $exception) {
                $errors[] = "Evaluation item {$item->id}: {$exception->getMessage()}";
                $skipped++;

                AbsenceRuleExecutionLog::query()->create([
                    'evaluation_id' => $evaluation->id,
                    'evaluation_student_id' => $item->id,
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
                    'deduction_points_count' => 0,
                    'executed_by' => $executedBy,
                    'executed_at' => now(),
                    'meta' => [
                        'error' => $exception->getMessage(),
                    ],
                ]);
            }
        }

        $alertsMarkedAsSent = $errors === [];
        if ($alertsMarkedAsSent) {
            $evaluation->update(['is_send_absence_alerts' => true]);
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'alerts_marked_as_sent' => $alertsMarkedAsSent,
        ];
    }

    private function resolveHandler(string $action): RuleActionHandler
    {
        $handler = $this->handlers[$action] ?? null;
        if ($handler) {
            return $handler;
        }

        if (in_array($action, [AbsenceRule::ACTION_SEND_MESSAGE, AbsenceRule::ACTION_SEND_MESSAGE_AND_FREEZE], true)) {
            return $this->handlers[AbsenceRule::ACTION_FREEZE_STUDENT];
        }

        throw new RuntimeException("Absence rule action [{$action}] is not supported.");
    }

    private function countMonthlyOccurrence(int $studentId, int $attendanceValue, CarbonImmutable $evaluationDate): int
    {
        return EvaluationStudent::query()
            ->join('evaluations', 'evaluations_users.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations_users.attendances', $attendanceValue)
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
        $workingDays = $this->normalizeWorkingDays($student->center?->working_days);
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
            'contact_phone' => (string) ($student->center?->phone ?? ''),
        ];
    }

    /**
     * @param array<int, mixed>|null $workingDays
     * @return array<int, int>
     */
    private function normalizeWorkingDays(?array $workingDays): array
    {
        $values = Collection::make($workingDays ?? [])
            ->map(static fn ($value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value >= 0 && $value <= 6)
            ->unique()
            ->values()
            ->all();

        if ($values === []) {
            return [0, 1, 2, 3, 4, 5, 6];
        }

        return $values;
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
            if (!is_string($phone)) {
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
     * @param array<string, mixed> $freezeWindow
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
                'id' => $student->center?->id ?? $evaluation->center_id,
                'name' => $student->center?->name ?? '',
                'phone' => $student->center?->phone ?? '',
            ],
            'evaluation' => [
                'id' => $evaluation->id,
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
            'center_name' => $student->center?->name ?? '',
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

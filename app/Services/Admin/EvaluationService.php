<?php

namespace App\Services\Admin;

use App\Exceptions\WhatsAppMessageSendException;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\Student;
use App\Models\StudentFreeze;
use App\Services\Admin\AbsenceRules\AbsenceAlertExecutionLock;
use App\Services\Admin\AbsenceRules\AbsenceRuleEngine;
use App\Services\System\DateTimeFormatterService;
use App\Support\GroupMonthlyPlanCoverage;
use App\Support\GroupWorkingDays;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EvaluationService
{
    public function __construct(
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly AbsenceRuleEngine $absenceRuleEngine,
        private readonly AbsenceAlertExecutionLock $absenceAlertExecutionLock,
        private readonly AdminDataScopeService $dataScope,
        private readonly WhatsAppMessagingService $whatsAppMessagingService,
        private readonly WhatsAppPendingMessageService $whatsAppPendingMessageService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 50);
        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $centerId = isset($filters['center_id']) ? (int) $filters['center_id'] : null;
        $groupId = isset($filters['group_id']) ? (int) $filters['group_id'] : null;
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $alertStatus = (string) ($filters['alert_status'] ?? '');
        $sortMap = [
            'id' => 'evaluations.id',
            'date' => 'evaluations.date',
            'center_name' => 'centers.name',
            'group_name' => 'groups.name',
            'admin_name' => 'admins.name',
            'is_send_absence_alerts' => 'evaluations.is_send_absence_alerts',
            'created_at' => 'evaluations.created_at',
        ];

        $sortColumn = $sortMap[$sortBy] ?? 'evaluations.id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';
        $rows = Evaluation::query()
            ->leftJoin('groups', 'evaluations.group_id', '=', 'groups.id')
            ->leftJoin('centers', 'groups.center_id', '=', 'centers.id')
            ->leftJoin('users as admins', 'evaluations.admin_id', '=', 'admins.id')
            ->select([
                'evaluations.id',
                'evaluations.ulid',
                'evaluations.date',
                'evaluations.center_id',
                'evaluations.group_id',
                'evaluations.admin_id',
                'evaluations.is_send_absence_alerts',
                'evaluations.created_at',
                'centers.name as center_name',
                'groups.name as group_name',
                'admins.name as admin_name',
            ])
            ->tap(fn ($query) => $this->dataScope->applyEvaluationAccess($query, 'evaluations'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('evaluations.id', 'like', "%{$search}%")
                        ->orWhere('evaluations.date', 'like', "%{$search}%")
                        ->orWhere('centers.name', 'like', "%{$search}%")
                        ->orWhere('groups.name', 'like', "%{$search}%")
                        ->orWhere('admins.name', 'like', "%{$search}%");
                });
            })
            ->when($centerId !== null && $centerId > 0, function ($query) use ($centerId): void {
                $query->where('groups.center_id', $centerId);
            })
            ->when($groupId !== null && $groupId > 0, function ($query) use ($groupId): void {
                $query->where('evaluations.group_id', $groupId);
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom): void {
                $query->whereDate('evaluations.date', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo): void {
                $query->whereDate('evaluations.date', '<=', $dateTo);
            })
            ->when($alertStatus === 'sent', function ($query): void {
                $query->where('evaluations.is_send_absence_alerts', true);
            })
            ->when($alertStatus === 'pending', function ($query): void {
                $query->where('evaluations.is_send_absence_alerts', false);
            })
            ->orderBy($sortColumn, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $evaluationIds = $rows->getCollection()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $previewCounts = $this->localPreviewCountsForEvaluations($evaluationIds);
        $messageLogStatuses = $this->messageLogStatusesForEvaluations($evaluationIds);

        $rows->setCollection(
            $rows->getCollection()->map(function ($row) use ($messageLogStatuses, $previewCounts) {
                $row->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($row->created_at));
                $row->setAttribute(
                    'date_formatted',
                    Carbon::parse((string) $row->date)->locale(app()->getLocale())->translatedFormat('l ، d/m/Y'),
                );
                $row->setAttribute(
                    'report_url',
                    filled($row->ulid) ? route('evaluations.report', ['publicId' => $row->ulid]) : null,
                );
                $row->setAttribute('local_absence_preview_count', $previewCounts[(int) $row->id] ?? 0);
                $row->setAttribute('message_log_status', $messageLogStatuses[(int) $row->id] ?? null);

                return $row;
            }),
        );

        return $rows;
    }

    /**
     * @return array<int, array{id: int, name: string, groups: array<int, array{id: int, name: string, center_id: int}>}>
     */
    public function centerOptions(): array
    {
        return Center::query()
            ->active()
            ->tap(fn ($query) => $this->dataScope->applyCenterAccess($query, 'centers'))
            ->with(['groups' => function ($query): void {
                $query
                    ->tap(fn ($groupQuery) => $this->dataScope->applyGroupAccess($groupQuery, 'groups'))
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Center $center): array => [
                'id' => (int) $center->id,
                'name' => (string) $center->name,
                'groups' => $center->groups
                    ->map(static fn (Group $group): array => [
                        'id' => (int) $group->id,
                        'name' => (string) $group->name,
                        'center_id' => (int) $group->center_id,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array{selected_center_id: ?int, selected_group_id: ?int, selected_date: string, students: array<int, array<string, mixed>>, existing_evaluation_id: ?int}
     */
    public function createFormPayload(?int $centerId, ?int $groupId, ?string $date): array
    {
        $resolvedDate = $this->resolveDate($date);
        $resolvedCenterId = $centerId !== null && $centerId > 0 ? $centerId : null;
        $resolvedGroupId = $groupId !== null && $groupId > 0 ? $groupId : null;

        if ($resolvedGroupId !== null) {
            $group = Group::query()
                ->tap(fn ($query) => $this->dataScope->applyGroupAccess($query, 'groups'))
                ->find($resolvedGroupId, ['id', 'center_id']);

            abort_unless($group instanceof Group, 404);
            $resolvedCenterId = (int) $group->center_id;
        }

        if ($resolvedGroupId === null && $resolvedCenterId !== null) {
            $centerExists = Center::query()
                ->tap(fn ($query) => $this->dataScope->applyCenterAccess($query, 'centers'))
                ->whereKey($resolvedCenterId)
                ->exists();

            abort_unless($centerExists, 404);
        }

        if ($resolvedGroupId === null) {
            return [
                'selected_center_id' => $resolvedCenterId,
                'selected_group_id' => null,
                'selected_date' => $resolvedDate,
                'students' => [],
                'existing_evaluation_id' => null,
            ];
        }

        $existingEvaluationId = Evaluation::query()
            ->where('group_id', $resolvedGroupId)
            ->whereDate('date', $resolvedDate)
            ->value('id');

        $students = $existingEvaluationId === null
            ? $this->studentRowsForGroupDate($resolvedGroupId, $resolvedDate)
            : [];

        return [
            'selected_center_id' => $resolvedCenterId,
            'selected_group_id' => $resolvedGroupId,
            'selected_date' => $resolvedDate,
            'students' => $students,
            'existing_evaluation_id' => $existingEvaluationId !== null ? (int) $existingEvaluationId : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function editStudentRows(Evaluation $evaluation): array
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        $date = Carbon::parse((string) $evaluation->date)->toDateString();
        $groupId = (int) $evaluation->group_id;
        abort_unless($groupId > 0, 404);

        return $this->studentRowsForGroupDate(
            groupId: $groupId,
            date: $date,
            evaluation: $evaluation,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Evaluation
    {
        $groupId = (int) $data['group_id'];
        $date = $this->resolveDate((string) $data['date']);
        $adminId = Auth::id();
        $evaluationType = $this->resolveEvaluationType($data['evaluation_type'] ?? Evaluation::TYPE_ALHIFZ);

        $group = Group::query()
            ->tap(fn ($query) => $this->dataScope->applyGroupAccess($query, 'groups'))
            ->find($groupId, ['id', 'center_id', 'working_days']);

        abort_unless($group instanceof Group, 404);
        $centerId = (int) $group->center_id;

        if (! GroupWorkingDays::isConfigured($group->working_days)) {
            throw ValidationException::withMessages([
                'date' => __('groups.working_days_not_configured'),
            ]);
        }

        if (! GroupWorkingDays::includes($group->working_days, $date)) {
            throw ValidationException::withMessages([
                'date' => __('evaluations.date_not_in_group_working_days'),
            ]);
        }

        if (! GroupMonthlyPlanCoverage::exists((int) $group->id, $date)) {
            throw ValidationException::withMessages([
                'date' => __('monthly_plans.required_for_follow_up_date'),
            ]);
        }

        $exists = Evaluation::query()
            ->where('group_id', $groupId)
            ->whereDate('date', $date)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'date' => __('evaluations.already_exists_for_group_date'),
            ]);
        }

        return DB::transaction(function () use ($centerId, $date, $adminId, $data, $evaluationType, $groupId): Evaluation {
            $evaluation = Evaluation::query()->create([
                'ulid' => (string) Str::ulid(),
                'date' => $date,
                'center_id' => $centerId,
                'group_id' => $groupId,
                'admin_id' => $adminId,
                'is_send_absence_alerts' => false,
                'evaluation_type' => $evaluationType,
            ]);

            $this->replaceEvaluationRows($evaluation, $data['items'] ?? [], $evaluationType);
            $this->ensureFrozenRows($evaluation);

            return $evaluation->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Evaluation $evaluation, array $data): Evaluation
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);
        $lock = $this->acquireEvaluationExecutionLock($evaluation);

        try {
            return DB::transaction(function () use ($evaluation, $data): Evaluation {
                $evaluationType = $this->resolveEvaluationType($data['evaluation_type'] ?? $evaluation->evaluation_type);
                if ((int) $evaluation->evaluation_type !== $evaluationType) {
                    $evaluation->update(['evaluation_type' => $evaluationType]);
                }

                $this->syncEvaluationRows($evaluation, $data['items'] ?? [], $evaluationType);
                $this->ensureFrozenRows($evaluation);

                if ($evaluation->is_send_absence_alerts) {
                    $evaluation->update(['is_send_absence_alerts' => false]);
                }

                return $evaluation->refresh();
            });
        } finally {
            $lock->release();
        }
    }

    public function delete(Evaluation $evaluation): void
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);
        $lock = $this->acquireEvaluationExecutionLock($evaluation);

        try {
            $evaluation->delete();
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
    public function sendAbsenceAlerts(Evaluation $evaluation): array
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        return $this->absenceRuleEngine->processEvaluation(
            evaluationId: $evaluation->id,
            executedBy: Auth::id(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function absenceAlertPreviews(Evaluation $evaluation): array
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        return $this->absencePreviewLogsQuery($evaluation)
            ->limit(100)
            ->get()
            ->filter(fn (AbsenceRuleExecutionLog $log): bool => (bool) (($log->meta ?? [])['local_preview'] ?? false))
            ->map(fn (AbsenceRuleExecutionLog $log): array => $this->absencePreviewPayload($log))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function absenceMessageLogs(Evaluation $evaluation): array
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);

        return $this->absenceMessageLogsQuery($evaluation)
            ->limit(100)
            ->get()
            ->map(fn (AbsenceRuleExecutionLog $log): array => $this->absenceMessageLogPayload($log))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function resendAbsenceMessage(Evaluation $evaluation, AbsenceRuleExecutionLog $log): array
    {
        $this->dataScope->abortUnlessCanAccessEvaluation($evaluation);
        abort_unless((int) $log->evaluation_id === (int) $evaluation->id, 404);
        abort_unless(
            $this->dataScope
                ->applyAbsenceExecutionLogAccess(AbsenceRuleExecutionLog::query())
                ->whereKey($log->id)
                ->exists(),
            404,
        );

        $lock = $this->acquireEvaluationExecutionLock($evaluation);

        try {
            if (! $this->absenceAlertExecutionLock->refresh($lock)) {
                throw ValidationException::withMessages([
                    'message' => __('evaluations.absence_alert_lock_lost'),
                ]);
            }

            return $this->resendAbsenceMessageWhileLocked($evaluation, $log);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resendAbsenceMessageWhileLocked(Evaluation $evaluation, AbsenceRuleExecutionLog $log): array
    {

        /** @var AbsenceRuleExecutionLog $log */
        $log = AbsenceRuleExecutionLog::query()
            ->with(['student.center:id,name,group_serialized', 'center:id,name,group_serialized'])
            ->findOrFail($log->id);

        if ($log->was_message_sent || $this->hasBlockingSiblingExecution($log)) {
            throw ValidationException::withMessages([
                'message' => __('evaluations.absence_message_already_sent'),
            ]);
        }

        if ($this->hasPartialDelivery($log->meta ?? [])) {
            throw ValidationException::withMessages([
                'message' => __('evaluations.absence_message_partially_sent'),
            ]);
        }

        if ((bool) (($log->meta ?? [])['delivery_unknown'] ?? false)) {
            throw ValidationException::withMessages([
                'message' => __('evaluations.absence_message_delivery_uncertain'),
            ]);
        }

        $error = Arr::get($log->meta ?? [], 'error');
        if (! is_string($error) || trim($error) === '') {
            throw ValidationException::withMessages([
                'message' => __('evaluations.absence_message_not_failed'),
            ]);
        }

        $content = trim((string) $log->message_content);
        if ($content === '') {
            throw ValidationException::withMessages([
                'message' => __('evaluations.absence_message_missing_content'),
            ]);
        }

        $student = $log->student;
        if (! $student instanceof Student) {
            throw ValidationException::withMessages([
                'message' => __('evaluations.absence_message_missing_student'),
            ]);
        }

        $student->loadMissing('center:id,name,group_serialized');
        $evaluation->loadMissing('group:id,center_id,group_serialized');
        $recipientPhones = $this->extractUniquePhones($student);
        $groupSerialized = null;
        if ($log->sent_to_group) {
            $groupSerialized = $evaluation->group !== null
                ? $evaluation->group->group_serialized
                : $student->center?->group_serialized;
        }
        $evaluationCenterId = $evaluation->group?->center_id
            ?? $evaluation->center_id
            ?? $student->center_id;

        if ($recipientPhones === [] && ($groupSerialized === null || trim($groupSerialized) === '')) {
            throw ValidationException::withMessages([
                'message' => __('students.no_recipients_provided'),
            ]);
        }

        if (! $this->whatsAppPendingMessageService->canDispatchAbsenceLog($log)) {
            throw ValidationException::withMessages([
                'message' => __('evaluations.absence_message_business_action_not_applied'),
            ]);
        }

        if (! $this->whatsAppPendingMessageService->retirePendingForSource(
            AbsenceRuleExecutionLog::class,
            (int) $log->id,
        )) {
            throw ValidationException::withMessages([
                'message' => __('evaluations.absence_message_pending_processing'),
            ]);
        }

        try {
            $this->whatsAppMessagingService->sendMediaCaption($recipientPhones, $content, $groupSerialized);
        } catch (WhatsAppMessageSendException $exception) {
            $this->updateResentMessageLog(
                log: $log,
                student: $student,
                recipientPhones: $recipientPhones,
                groupSerialized: $groupSerialized,
                centerId: $evaluationCenterId !== null ? (int) $evaluationCenterId : null,
                wasMessageSent: false,
                errorMessage: $exception->getMessage(),
                dispatchMeta: $this->whatsAppMessagingService->deliveryFailureMeta(
                    $recipientPhones,
                    $groupSerialized,
                    $exception,
                ),
            );

            throw ValidationException::withMessages([
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            $this->updateResentMessageLog(
                log: $log,
                student: $student,
                recipientPhones: $recipientPhones,
                groupSerialized: $groupSerialized,
                centerId: $evaluationCenterId !== null ? (int) $evaluationCenterId : null,
                wasMessageSent: false,
                errorMessage: $exception->getMessage(),
                dispatchMeta: ['delivery_unknown' => true],
            );

            throw ValidationException::withMessages([
                'message' => $exception->getMessage(),
            ]);
        }

        $log = $this->updateResentMessageLog(
            log: $log,
            student: $student,
            recipientPhones: $recipientPhones,
            groupSerialized: $groupSerialized,
            centerId: $evaluationCenterId !== null ? (int) $evaluationCenterId : null,
            wasMessageSent: true,
        );

        return $this->absenceMessageLogPayload($log);
    }

    /**
     * @return array<string, mixed>
     */
    public function reportPayload(string $publicId): array
    {
        $publicId = trim($publicId);
        abort_if($publicId === '', 404);

        $evaluationQuery = Evaluation::query()
            ->with(['group:id,name,center_id', 'group.center:id,name', 'center:id,name'])
            ->where('ulid', $publicId);

        if (Schema::hasColumn('evaluations', 'uuid')) {
            $evaluationQuery->orWhere('uuid', $publicId);
        }

        /** @var Evaluation $evaluation */
        $evaluation = $evaluationQuery->firstOrFail();
        $date = Carbon::parse((string) $evaluation->date);
        $dateForQuery = $date->toDateString();
        $evaluationType = $this->resolveEvaluationType($evaluation->evaluation_type);

        $rows = EvaluationStudent::query()
            ->leftJoin('students', function ($join): void {
                $join->on('evaluations_users.student_id', '=', 'students.id')
                    ->orOn('evaluations_users.user_id', '=', 'students.id');
            })
            ->leftJoin('plan_types', 'students.plan_type_id', '=', 'plan_types.id')
            ->leftJoin('student_freezes', function ($join) use ($dateForQuery): void {
                $join->on('students.id', '=', 'student_freezes.student_id')
                    ->whereDate('student_freezes.from', '<=', $dateForQuery)
                    ->whereDate('student_freezes.to', '>=', $dateForQuery);
            })
            ->where('evaluations_users.evaluation_id', $evaluation->id)
            ->orderBy('students.plan_type_id')
            ->orderBy('students.full_name')
            ->orderBy('evaluations_users.id')
            ->select([
                'evaluations_users.id as item_id',
                'evaluations_users.alhifz',
                'evaluations_users.warud',
                'evaluations_users.akhlaqi',
                'evaluations_users.tajwid',
                'evaluations_users.note',
                'evaluations_users.attendances',
                'students.id as student_id',
                DB::raw("COALESCE(students.full_name, '') as full_name"),
                'students.plan_type_id',
                'plan_types.name as plan_name',
                'student_freezes.from as freeze_from',
                'student_freezes.to as freeze_to',
                'student_freezes.reason as freeze_reason',
            ])
            ->get()
            ->values()
            ->map(function ($row, int $index) use ($evaluationType): array {
                $attendance = (int) ($row->attendances ?? EvaluationStudent::ATTENDANCE_PRESENT);
                $primaryScore = $this->nullableInt(
                    $evaluationType === Evaluation::TYPE_TAJWID ? $row->tajwid : $row->alhifz,
                );
                $warud = $this->nullableInt($row->warud);
                $akhlaqi = $this->nullableInt($row->akhlaqi);
                $scoreTotal = $primaryScore + ($warud ?? 0) + $akhlaqi;
                $maxTotal = $warud !== null ? 30 : 20;

                return [
                    'number' => $index + 1,
                    'item_id' => (int) $row->item_id,
                    'student_id' => $row->student_id !== null ? (int) $row->student_id : null,
                    'full_name' => trim((string) $row->full_name) !== '' ? (string) $row->full_name : '-',
                    'plan_type_id' => $row->plan_type_id !== null ? (int) $row->plan_type_id : null,
                    'plan_name' => $row->plan_name !== null ? (string) $row->plan_name : '-',
                    'attendance' => $attendance,
                    'primary_score' => $primaryScore,
                    'warud' => $warud,
                    'akhlaqi' => $akhlaqi,
                    'note' => $row->note !== null ? (string) $row->note : null,
                    'freeze_from' => $this->formatArabicShortDate($row->freeze_from),
                    'freeze_to' => $this->formatArabicShortDate($row->freeze_to),
                    'freeze_reason' => $row->freeze_reason !== null ? (string) $row->freeze_reason : null,
                    'is_perfect' => $attendance === EvaluationStudent::ATTENDANCE_PRESENT
                        && $primaryScore !== null
                        && $akhlaqi !== null
                        && $scoreTotal === $maxTotal,
                ];
            })
            ->all();

        return [
            'title' => 'تقييمات الطلاب',
            'center_name' => $evaluation->group?->center?->name ?? $evaluation->center?->name ?? '-',
            'group_name' => $evaluation->group?->name ?? '-',
            'date' => $date->copy()->locale('ar')->translatedFormat('l ، j F ، Y'),
            'hijri_date' => $this->formatHijriDate($date),
            'evaluation_type' => $evaluationType,
            'primary_score_label' => $evaluationType === Evaluation::TYPE_TAJWID ? 'التجويد' : 'الحفظ',
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function replaceEvaluationRows(Evaluation $evaluation, array $items, int $evaluationType): void
    {
        $rows = [];
        $rowsByStudent = [];
        $now = now();

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $studentId = (int) ($item['student_id'] ?? 0);
            $attendance = (int) ($item['attendances'] ?? EvaluationStudent::ATTENDANCE_PRESENT);

            if ($studentId <= 0) {
                continue;
            }

            $scorePayload = $this->buildScorePayload(
                $item,
                EvaluationStudent::hasScores($attendance),
                $evaluationType,
            );

            $rowsByStudent[$studentId] = [
                ...$scorePayload,
                'note' => $this->normalizeNote($item),
                'attendances' => $attendance,
                'student_id' => $studentId,
                'user_id' => $studentId,
                'evaluation_id' => $evaluation->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach ($rowsByStudent as $row) {
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        if ($rows !== []) {
            EvaluationStudent::query()->insert($rows);
        }
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function syncEvaluationRows(Evaluation $evaluation, array $items, int $evaluationType): void
    {
        $existingRows = EvaluationStudent::query()
            ->where('evaluation_id', $evaluation->id)
            ->where('attendances', '!=', EvaluationStudent::ATTENDANCE_FROZEN)
            ->get()
            ->mapWithKeys(static function (EvaluationStudent $row): array {
                $studentId = $row->resolvedStudentId();
                if ($studentId === null) {
                    return [];
                }

                return [(int) $studentId => $row];
            });

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $studentId = (int) ($item['student_id'] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $attendance = (int) ($item['attendances'] ?? EvaluationStudent::ATTENDANCE_PRESENT);
            $scorePayload = $this->buildScorePayload(
                $item,
                EvaluationStudent::hasScores($attendance),
                $evaluationType,
            );
            $payload = [
                ...$scorePayload,
                'note' => $this->normalizeNote($item),
                'attendances' => $attendance,
                'student_id' => $studentId,
                'user_id' => $studentId,
                'evaluation_id' => $evaluation->id,
            ];

            /** @var EvaluationStudent|null $existing */
            $existing = $existingRows->get($studentId);
            if ($existing !== null) {
                $existing->fill($payload);
                if ($existing->isDirty()) {
                    $existing->save();
                }

                continue;
            }

            EvaluationStudent::query()->create($payload);
        }
    }

    private function ensureFrozenRows(Evaluation $evaluation): void
    {
        if ($evaluation->group_id === null || $evaluation->date === null) {
            return;
        }

        $date = Carbon::parse((string) $evaluation->date)->toDateString();
        $frozenStudentIds = $this->frozenStudentIdsForDate((int) $evaluation->group_id, $date);
        if ($frozenStudentIds === []) {
            return;
        }

        $existingStudentIds = EvaluationStudent::query()
            ->where('evaluation_id', $evaluation->id)
            ->whereIn('student_id', $frozenStudentIds)
            ->pluck('student_id')
            ->filter(static fn ($value): bool => $value !== null)
            ->map(static fn ($value): int => (int) $value)
            ->all();

        $existingLookup = array_fill_keys($existingStudentIds, true);
        $now = now();
        $rows = [];

        foreach ($frozenStudentIds as $studentId) {
            if (isset($existingLookup[$studentId])) {
                continue;
            }

            $rows[] = [
                'alhifz' => null,
                'warud' => null,
                'akhlaqi' => null,
                'tajwid' => null,
                'note' => null,
                'attendances' => EvaluationStudent::ATTENDANCE_FROZEN,
                'student_id' => $studentId,
                'user_id' => $studentId,
                'evaluation_id' => $evaluation->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            EvaluationStudent::query()->insert($rows);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function studentRowsForGroupDate(int $groupId, string $date, ?Evaluation $evaluation = null): array
    {
        $existingItemsByStudent = collect();
        if ($evaluation !== null) {
            $existingItemsByStudent = $evaluation->evaluationStudents()
                ->where('attendances', '!=', EvaluationStudent::ATTENDANCE_FROZEN)
                ->whereHas('student', fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
                ->get()
                ->mapWithKeys(static function (EvaluationStudent $item): array {
                    $studentId = $item->resolvedStudentId();
                    if ($studentId === null) {
                        return [];
                    }

                    return [(int) $studentId => $item];
                });
        }

        $frozenLookup = array_fill_keys($this->frozenStudentIdsForDate($groupId, $date), true);

        $baseStudents = Student::query()
            ->with(['groups' => static fn ($query) => $query->orderBy('groups.name')])
            ->leftJoin('plan_types', 'students.plan_type_id', '=', 'plan_types.id')
            ->whereHas('groups', static fn ($query) => $query->where('groups.id', $groupId))
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->where('students.is_active', Student::STATUS_ACTIVE)
            ->orderBy('students.plan_type_id')
            ->orderBy('students.full_name')
            ->select([
                'students.id',
                'students.full_name',
                'plan_types.name as plan_name',
            ])
            ->get();

        $rows = [];
        $included = [];

        foreach ($baseStudents as $student) {
            $studentId = (int) $student->id;
            if (isset($frozenLookup[$studentId]) && ! $existingItemsByStudent->has($studentId)) {
                continue;
            }

            $rows[] = $this->studentRowData(
                studentId: $studentId,
                fullName: (string) $student->full_name,
                planName: $student->plan_name !== null ? (string) $student->plan_name : null,
                groupName: $student->groups->pluck('name')->implode(', ') ?: null,
                item: $existingItemsByStudent->get($studentId),
            );
            $included[$studentId] = true;
        }

        foreach ($existingItemsByStudent as $studentId => $item) {
            $studentId = (int) $studentId;
            if (isset($included[$studentId])) {
                continue;
            }

            $student = Student::query()
                ->with(['groups' => static fn ($query) => $query->orderBy('groups.name')])
                ->leftJoin('plan_types', 'students.plan_type_id', '=', 'plan_types.id')
                ->where('students.id', $studentId)
                ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
                ->select([
                    'students.id',
                    'students.full_name',
                    'plan_types.name as plan_name',
                ])
                ->first();

            if ($student === null) {
                // Legacy/orphaned evaluation row (student deleted): skip to keep edit form valid.
                continue;
            }

            $rows[] = $this->studentRowData(
                studentId: $studentId,
                fullName: (string) ($student->full_name ?? ''),
                planName: $student?->plan_name !== null ? (string) $student->plan_name : null,
                groupName: $student->groups->pluck('name')->implode(', ') ?: null,
                item: $item,
            );
        }

        return $rows;
    }

    private function studentRowData(
        int $studentId,
        string $fullName,
        ?string $planName,
        ?string $groupName,
        ?EvaluationStudent $item = null,
    ): array {
        return [
            'student_id' => $studentId,
            'full_name' => $fullName,
            'plan_name' => $planName,
            'group_name' => $groupName,
            'alhifz' => $item?->alhifz ?? 10,
            'warud' => $item?->warud ?? 10,
            'akhlaqi' => $item?->akhlaqi ?? 10,
            'tajwid' => $item?->tajwid ?? 10,
            'note' => $item?->note,
            'attendances' => $item?->attendances ?? EvaluationStudent::ATTENDANCE_PRESENT,
            'was_edited' => $item !== null
                && $item->created_at !== null
                && $item->updated_at !== null
                && $item->updated_at->gt($item->created_at),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function frozenStudentIdsForDate(int $groupId, string $date): array
    {
        return StudentFreeze::query()
            ->join('students', 'student_freezes.student_id', '=', 'students.id')
            ->join('group_student', 'students.id', '=', 'group_student.student_id')
            ->where('group_student.group_id', $groupId)
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->whereDate('student_freezes.from', '<=', $date)
            ->whereDate('student_freezes.to', '>=', $date)
            ->where(function ($query) use ($date): void {
                $query->where('student_freezes.is_active', true)
                    ->orWhere(function ($inner) use ($date): void {
                        $inner->whereNotNull('student_freezes.unfrozen_at')
                            ->whereDate('student_freezes.unfrozen_at', '>', $date);
                    });
            })
            ->pluck('student_freezes.student_id')
            ->unique()
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function normalizeScore(array $item, string $key): int
    {
        $value = Arr::get($item, $key);
        if ($value === null || $value === '') {
            return 10;
        }

        $score = (int) $value;

        return max(0, min(10, $score));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function normalizeNote(array $item): ?string
    {
        $value = Arr::get($item, 'note');
        if (! is_string($value)) {
            return null;
        }

        $note = trim($value);

        return $note !== '' ? $note : null;
    }

    private function resolveDate(?string $date): string
    {
        if ($date === null || trim($date) === '') {
            return now()->toDateString();
        }

        return Carbon::parse($date)->toDateString();
    }

    /**
     * @param  array<int, string>  $recipientPhones
     */
    private function updateResentMessageLog(
        AbsenceRuleExecutionLog $log,
        Student $student,
        array $recipientPhones,
        ?string $groupSerialized,
        ?int $centerId,
        bool $wasMessageSent,
        ?string $errorMessage = null,
        array $dispatchMeta = [],
    ): AbsenceRuleExecutionLog {
        $attemptedAt = now();
        $meta = $log->meta ?? [];
        foreach (['partial_delivery', 'delivered_chat_ids', 'remaining_chat_ids', 'delivery_unknown'] as $key) {
            unset($meta[$key]);
        }
        $meta['resend_attempted'] = true;
        $meta['resend_attempted_at'] = $attemptedAt->toIso8601String();
        $meta['resend_attempted_by'] = Auth::id();
        $meta['resend_source'] = 'evaluation_group';
        $meta['group_serialized'] = $groupSerialized;
        $meta['error'] = $errorMessage;
        $meta = array_merge($meta, $dispatchMeta);

        if ($wasMessageSent) {
            $meta['resent'] = true;
            $meta['resent_at'] = $attemptedAt->toIso8601String();
            $meta['resent_by'] = Auth::id();
        }

        $log->update([
            'center_id' => $centerId,
            'recipient_phones' => $recipientPhones,
            'was_message_sent' => $wasMessageSent,
            'executed_by' => Auth::id(),
            'executed_at' => $attemptedAt,
            'meta' => $meta,
        ]);

        return $log->refresh()->load(['student:id,full_name', 'center:id,name,group_serialized']);
    }

    /** @param array<string, mixed> $meta */
    private function hasPartialDelivery(array $meta): bool
    {
        if ((bool) ($meta['partial_delivery'] ?? false)
            || (bool) ($meta['pending_partial_delivery'] ?? false)) {
            return true;
        }

        $deliveredChatIds = $meta['delivered_chat_ids'] ?? $meta['pending_delivered_chat_ids'] ?? [];

        return is_array($deliveredChatIds) && $deliveredChatIds !== [];
    }

    private function acquireEvaluationExecutionLock(Evaluation $evaluation): Lock
    {
        $lock = $this->absenceAlertExecutionLock->acquire((int) $evaluation->id);
        if ($lock === null) {
            throw ValidationException::withMessages([
                'message' => __('evaluations.absence_alerts_processing'),
            ]);
        }

        return $lock;
    }

    private function hasBlockingSiblingExecution(AbsenceRuleExecutionLog $log): bool
    {
        if ($log->evaluation_student_id === null) {
            return false;
        }

        return AbsenceRuleExecutionLog::query()
            ->where('evaluation_student_id', $log->evaluation_student_id)
            ->whereKeyNot($log->id)
            ->get()
            ->reject(static fn (AbsenceRuleExecutionLog $sibling): bool => (bool) (($sibling->meta ?? [])['local_preview'] ?? false))
            ->contains(fn (AbsenceRuleExecutionLog $sibling): bool => ! $this->isKnownZeroDeliveryFailure($sibling));
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
     * @param  array<int, int>  $evaluationIds
     * @return array<int, int>
     */
    private function localPreviewCountsForEvaluations(array $evaluationIds): array
    {
        if (! app()->environment('local') || $evaluationIds === []) {
            return [];
        }

        return AbsenceRuleExecutionLog::query()
            ->whereIn('evaluation_id', $evaluationIds)
            ->where('was_message_sent', false)
            ->whereNotNull('message_content')
            ->get(['id', 'evaluation_id', 'meta'])
            ->filter(fn (AbsenceRuleExecutionLog $log): bool => (bool) (($log->meta ?? [])['local_preview'] ?? false))
            ->groupBy('evaluation_id')
            ->map(static fn ($logs): int => $logs->count())
            ->mapWithKeys(static fn (int $count, $evaluationId): array => [(int) $evaluationId => $count])
            ->all();
    }

    /**
     * @param  array<int, int>  $evaluationIds
     * @return array<int, string>
     */
    private function messageLogStatusesForEvaluations(array $evaluationIds): array
    {
        if ($evaluationIds === []) {
            return [];
        }

        return $this->dataScope
            ->applyAbsenceExecutionLogAccess(AbsenceRuleExecutionLog::query())
            ->whereIn('evaluation_id', $evaluationIds)
            ->whereNotNull('message_content')
            ->get(['evaluation_id', 'was_message_sent', 'meta'])
            ->groupBy('evaluation_id')
            ->map(function ($logs): ?string {
                $hasFailure = $logs->contains(function (AbsenceRuleExecutionLog $log): bool {
                    $error = Arr::get($log->meta ?? [], 'error');

                    return ! $log->was_message_sent && is_string($error) && trim($error) !== '';
                });

                if ($hasFailure) {
                    return 'failed';
                }

                $allSent = $logs->isNotEmpty()
                    && $logs->every(fn (AbsenceRuleExecutionLog $log): bool => (bool) $log->was_message_sent);

                return $allSent ? 'sent' : null;
            })
            ->filter()
            ->mapWithKeys(static fn (string $status, $evaluationId): array => [(int) $evaluationId => $status])
            ->all();
    }

    private function absencePreviewLogsQuery(Evaluation $evaluation)
    {
        return $this->dataScope
            ->applyAbsenceExecutionLogAccess(AbsenceRuleExecutionLog::query())
            ->with(['student:id,full_name', 'center:id,name'])
            ->where('evaluation_id', $evaluation->id)
            ->where('was_message_sent', false)
            ->whereNotNull('message_content')
            ->latest('id');
    }

    private function absenceMessageLogsQuery(Evaluation $evaluation)
    {
        return $this->dataScope
            ->applyAbsenceExecutionLogAccess(AbsenceRuleExecutionLog::query())
            ->with(['student:id,full_name', 'center:id,name,group_serialized'])
            ->where('evaluation_id', $evaluation->id)
            ->whereNotNull('message_content')
            ->latest('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function absencePreviewPayload(AbsenceRuleExecutionLog $log): array
    {
        $meta = $log->meta ?? [];

        return [
            'id' => (int) $log->id,
            'student_name' => $log->student?->full_name ?? '-',
            'center_name' => $log->center?->name ?? '',
            'evaluation_id' => (int) $log->evaluation_id,
            'attendance_type' => $log->attendance_type,
            'attendance_label' => $this->attendanceLabel((string) $log->attendance_type),
            'occurrence_number' => $log->occurrence_number,
            'action' => $log->action,
            'recipient_phones' => $log->recipient_phones ?? [],
            'sent_to_group' => (bool) $log->sent_to_group,
            'group_serialized' => $meta['group_serialized'] ?? null,
            'message_content' => $log->message_content,
            'created_at_formatted' => $this->dateTimeFormatter->formatForAdmin($log->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function absenceMessageLogPayload(AbsenceRuleExecutionLog $log): array
    {
        $meta = $log->meta ?? [];
        $groupSerialized = array_key_exists('group_serialized', $meta)
            ? $meta['group_serialized']
            : $log->center?->group_serialized;
        $requiresDeliveryReview = $this->hasPartialDelivery($meta)
            || (bool) ($meta['delivery_unknown'] ?? false)
            || (bool) ($meta['processing'] ?? false);
        $error = $meta['error'] ?? null;

        return [
            'id' => (int) $log->id,
            'student_name' => $log->student?->full_name ?? '-',
            'center_name' => $log->center?->name ?? '',
            'evaluation_id' => (int) $log->evaluation_id,
            'attendance_type' => $log->attendance_type,
            'attendance_label' => $this->attendanceLabel((string) $log->attendance_type),
            'occurrence_number' => $log->occurrence_number,
            'action' => $log->action,
            'recipient_phones' => $log->recipient_phones ?? [],
            'sent_to_group' => (bool) $log->sent_to_group,
            'group_serialized' => $groupSerialized,
            'message_content' => $log->message_content,
            'was_message_sent' => (bool) $log->was_message_sent,
            'local_preview' => (bool) ($meta['local_preview'] ?? false),
            'error' => $error,
            'requires_delivery_review' => $requiresDeliveryReview,
            'can_resend' => ! $log->was_message_sent
                && ! $requiresDeliveryReview
                && is_string($error)
                && trim($error) !== '',
            'created_at_formatted' => $this->dateTimeFormatter->formatForAdmin($log->created_at),
            'executed_at_formatted' => $this->dateTimeFormatter->formatForAdmin($log->executed_at),
        ];
    }

    private function attendanceLabel(string $attendanceType): string
    {
        return match ($attendanceType) {
            'present' => 'حاضر',
            'late' => 'متأخر',
            'excused_absence' => 'غياب بعذر',
            'absence' => 'غياب بدون عذر',
            default => $attendanceType,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{alhifz: ?int, warud: ?int, akhlaqi: ?int, tajwid: ?int}
     */
    private function buildScorePayload(array $item, bool $isPresent, int $evaluationType): array
    {
        if (! $isPresent) {
            return [
                'alhifz' => null,
                'warud' => null,
                'akhlaqi' => null,
                'tajwid' => null,
            ];
        }

        return [
            'alhifz' => $evaluationType === Evaluation::TYPE_ALHIFZ ? $this->normalizeScore($item, 'alhifz') : null,
            'warud' => $this->normalizeScore($item, 'warud'),
            'akhlaqi' => $this->normalizeScore($item, 'akhlaqi'),
            'tajwid' => $evaluationType === Evaluation::TYPE_TAJWID ? $this->normalizeScore($item, 'tajwid') : null,
        ];
    }

    private function resolveEvaluationType(mixed $value): int
    {
        return (int) $value === Evaluation::TYPE_TAJWID ? Evaluation::TYPE_TAJWID : Evaluation::TYPE_ALHIFZ;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function formatArabicShortDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->locale('ar')->translatedFormat('l ، Y/m/j');
    }

    private function formatHijriDate(Carbon $date): string
    {
        if (! class_exists(\IntlDateFormatter::class)) {
            return '';
        }

        $formatter = new \IntlDateFormatter(
            'ar@calendar=islamic-umalqura',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            config('app.timezone'),
            \IntlDateFormatter::TRADITIONAL,
            'EEEE ، d MMMM ، y',
        );

        $formatted = $formatter->format($date->toDateTime());

        return is_string($formatted) ? $formatted : '';
    }
}

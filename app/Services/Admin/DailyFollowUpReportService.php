<?php

namespace App\Services\Admin;

use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\HomeworkStudentPoint;
use App\Models\MonthlyPlan;
use App\Models\Student;
use App\Models\StudentMonthlyPlan;
use App\Services\System\SystemSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class DailyFollowUpReportService
{
    public function __construct(
        private readonly AdminDataScopeService $dataScope,
        private readonly SystemSettingsService $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function workspacePlan(
        ?int $groupId,
        ?string $date,
        bool $includeProgress = true,
        bool $canViewMonthlyPlan = false,
    ): array {
        if ($groupId === null || $groupId <= 0 || blank($date)) {
            return $this->missingPlanPayload();
        }

        $selectedDate = CarbonImmutable::parse($date)->startOfDay();
        $asOfDate = $this->effectiveAsOfDate($selectedDate);
        $monthlyPlan = $this->monthlyPlanForDate($groupId, $selectedDate);
        if (! $monthlyPlan instanceof MonthlyPlan) {
            return $this->missingPlanPayload($groupId, $selectedDate);
        }

        if (! $includeProgress) {
            return [
                'available' => true,
                'required' => true,
                'progress_available' => false,
                'monthly_plan' => $this->monthlyPlanPayload($monthlyPlan, $canViewMonthlyPlan),
                'summary' => null,
                'students' => [],
                'create_url' => null,
            ];
        }

        $studentPlans = $this->studentPlans($monthlyPlan, $groupId);
        $completionDates = $this->completionDates(
            groupId: $groupId,
            studentPlans: $studentPlans,
            throughDate: $asOfDate,
        );

        $tracking = $studentPlans
            ->mapWithKeys(function (StudentMonthlyPlan $studentPlan) use ($asOfDate, $completionDates, $monthlyPlan, $selectedDate): array {
                $studentId = (int) $studentPlan->student_id;

                return [
                    $studentId => $this->studentTrackingPayload(
                        studentPlan: $studentPlan,
                        asOfDate: $asOfDate,
                        completionDates: $completionDates[$studentId] ?? [],
                        monthlyPlan: $monthlyPlan,
                        selectedDate: $selectedDate,
                    ),
                ];
            });

        $groupStudents = Student::query()
            ->whereHas('groups', static fn ($query) => $query->where('groups.id', $groupId))
            ->where('students.is_active', Student::STATUS_ACTIVE)
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->orderBy('students.full_name')
            ->get(['students.id', 'students.full_name']);

        foreach ($groupStudents as $student) {
            $studentId = (int) $student->id;
            if ($tracking->has($studentId)) {
                continue;
            }

            $tracking->put($studentId, [
                'student_id' => $studentId,
                'student_name' => (string) $student->full_name,
                'monthly_plan_id' => (int) $monthlyPlan->id,
                'plan_name' => null,
                'status' => 'missing_student_plan',
                'is_on_track' => false,
                'adherence_percentage' => null,
                'progress_percentage' => null,
                'expected_percentage' => null,
                'variance_percentage' => null,
                'completed_items_count' => 0,
                'total_items_count' => 0,
                'due_items_count' => 0,
                'due_completed_items_count' => 0,
                'future_completed_items_count' => 0,
                'today' => [
                    'planned_items_count' => 0,
                    'completed_items_count' => 0,
                    'percentage' => null,
                    'items' => [],
                ],
                'next' => [
                    'date' => null,
                    'items' => [],
                ],
                'report_url' => null,
            ]);
        }

        $statusCounts = $tracking
            ->countBy(static fn (array $row): string => (string) ($row['status'] ?? 'missing_student_plan'));

        return [
            'available' => true,
            'required' => true,
            'progress_available' => true,
            'monthly_plan' => $this->monthlyPlanPayload($monthlyPlan, $canViewMonthlyPlan),
            'summary' => [
                'students_count' => $tracking->count(),
                'on_track_count' => (int) $statusCounts->get('on_track', 0) + (int) $statusCounts->get('completed', 0),
                'ahead_count' => (int) $statusCounts->get('ahead', 0),
                'behind_count' => (int) $statusCounts->get('behind', 0),
                'not_due_count' => (int) $statusCounts->get('not_due', 0),
                'missing_count' => (int) $statusCounts->get('missing_student_plan', 0) + (int) $statusCounts->get('plan_mismatch', 0),
            ],
            'students' => $tracking->values()->all(),
            'create_url' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function studentReport(
        Group $group,
        Student $student,
        string $date,
        bool $canViewMonthlyPlan = false,
    ): array {
        $this->dataScope->abortUnlessCanAccessGroup($group);
        $this->dataScope->abortUnlessCanAccessStudent($student);

        $selectedDate = CarbonImmutable::parse($date)->startOfDay();
        $asOfDate = $this->effectiveAsOfDate($selectedDate);
        $monthlyPlan = $this->monthlyPlanForDate((int) $group->id, $selectedDate);
        abort_unless($monthlyPlan instanceof MonthlyPlan, 404);

        $studentPlan = StudentMonthlyPlan::query()
            ->with([
                'plan:id,name',
                'days' => static fn ($query) => $query->orderBy('date'),
                'days.items' => static fn ($query) => $query->orderBy('sort_order'),
                'days.items.planPoint:id,name,points',
            ])
            ->where('monthly_plan_id', $monthlyPlan->id)
            ->where('student_id', $student->id)
            ->firstOrFail();
        $studentPlan->setRelation('student', $student);

        $periodStart = $this->periodStart($monthlyPlan);
        $periodEnd = $this->periodEnd($monthlyPlan);
        $studentPlans = new EloquentCollection([$studentPlan]);
        $completionDates = $this->completionDates(
            groupId: (int) $group->id,
            studentPlans: $studentPlans,
            throughDate: $asOfDate,
        );
        $studentCompletionDates = $completionDates[(int) $student->id] ?? [];
        $tracking = $this->studentTrackingPayload($studentPlan, $asOfDate, $studentCompletionDates, $monthlyPlan, $selectedDate);
        $evaluations = $this->evaluationRows($group, $student, $periodStart, $asOfDate);
        $homeworkRows = $this->homeworkRows($group, $student, $periodStart, $asOfDate);
        $timeline = $this->timelinePayload(
            studentPlan: $studentPlan,
            completionDates: $studentCompletionDates,
            evaluations: $evaluations,
            homeworkRows: $homeworkRows,
            periodStart: $periodStart,
            periodEnd: $asOfDate,
        );

        return [
            'student' => [
                'id' => (int) $student->id,
                'name' => (string) $student->full_name,
                'plan_name' => (string) ($studentPlan->plan?->name ?? ''),
            ],
            'group' => [
                'id' => (int) $group->id,
                'name' => (string) $group->name,
            ],
            'period' => [
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'as_of_date' => $asOfDate->toDateString(),
                'selected_date' => $selectedDate->toDateString(),
                'label' => $periodStart->toDateString().' - '.$periodEnd->toDateString(),
            ],
            'monthly_plan' => [
                'id' => (int) $monthlyPlan->id,
                'edit_url' => $canViewMonthlyPlan
                    ? route('admin.monthly-plans.edit', $monthlyPlan, false)
                    : null,
            ],
            'tracking' => $tracking,
            'summary' => $this->reportSummary($tracking, $evaluations, $homeworkRows),
            'attendance' => $this->attendancePayload($evaluations),
            'evaluation' => $this->evaluationPayload($evaluations),
            'achievement' => [
                'labels' => $timeline['labels'],
                'expected_cumulative' => $timeline['expected_cumulative'],
                'completed_cumulative' => $timeline['completed_cumulative'],
                'planned_daily' => $timeline['planned_daily'],
                'completed_daily' => $timeline['completed_daily'],
                'assigned_daily' => $timeline['assigned_daily'],
                'outside_plan_daily' => $timeline['outside_plan_daily'],
            ],
            'insight' => $this->insightKey(
                $tracking,
                $evaluations,
                (int) array_sum($timeline['outside_plan_daily']),
            ),
        ];
    }

    private function monthlyPlanForDate(int $groupId, CarbonImmutable $date): ?MonthlyPlan
    {
        return MonthlyPlan::query()
            ->where('group_id', $groupId)
            ->where(function ($query) use ($date): void {
                $query
                    ->where(function ($period) use ($date): void {
                        $period
                            ->whereNotNull('start_date')
                            ->whereNotNull('end_date')
                            ->whereDate('start_date', '<=', $date->toDateString())
                            ->whereDate('end_date', '>=', $date->toDateString());
                    })
                    ->orWhere(function ($legacy) use ($date): void {
                        $legacy
                            ->where(function ($missingPeriod): void {
                                $missingPeriod->whereNull('start_date')->orWhereNull('end_date');
                            })
                            ->where('month', $date->month)
                            ->where('year', $date->year);
                    });
            })
            ->first();
    }

    /** @return EloquentCollection<int, StudentMonthlyPlan> */
    private function studentPlans(MonthlyPlan $monthlyPlan, int $groupId): EloquentCollection
    {
        return StudentMonthlyPlan::query()
            ->with([
                'student:id,full_name,plan_type_id',
                'plan:id,name',
                'days' => static fn ($query) => $query->orderBy('date'),
                'days.items' => static fn ($query) => $query->orderBy('sort_order'),
                'days.items.planPoint:id,name,points',
            ])
            ->join('students', 'student_monthly_plans.student_id', '=', 'students.id')
            ->where('student_monthly_plans.monthly_plan_id', $monthlyPlan->id)
            ->where('students.is_active', Student::STATUS_ACTIVE)
            ->whereExists(function ($membership) use ($groupId): void {
                $membership
                    ->selectRaw('1')
                    ->from('group_student')
                    ->whereColumn('group_student.student_id', 'student_monthly_plans.student_id')
                    ->where('group_student.group_id', $groupId);
            })
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->orderBy('students.full_name')
            ->select('student_monthly_plans.*')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, StudentMonthlyPlan>  $studentPlans
     * @return array<int, array<int, string>>
     */
    private function completionDates(int $groupId, EloquentCollection $studentPlans, CarbonImmutable $throughDate): array
    {
        $studentIds = $studentPlans->pluck('student_id')->map(static fn ($id): int => (int) $id)->unique()->all();
        $pointIds = $studentPlans
            ->flatMap(static fn (StudentMonthlyPlan $plan) => $plan->days->flatMap(
                static fn ($day) => $day->items->pluck('plan_point_id'),
            ))
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->all();

        if ($studentIds === [] || $pointIds === []) {
            return [];
        }

        $rows = HomeworkStudentPoint::query()
            ->join('homeworks', 'homework_student_points.homework_id', '=', 'homeworks.id')
            ->where('homeworks.group_id', $groupId)
            ->whereDate('homeworks.date', '<=', $throughDate->toDateString())
            ->whereIn('homework_student_points.student_id', $studentIds)
            ->whereIn('homework_student_points.plan_point_id', $pointIds)
            ->where('homework_student_points.is_done', true)
            ->select([
                'homework_student_points.student_id',
                'homework_student_points.plan_point_id',
            ])
            ->selectRaw('MIN(homeworks.date) as completed_on')
            ->groupBy('homework_student_points.student_id', 'homework_student_points.plan_point_id')
            ->get();

        $dates = [];
        foreach ($rows as $row) {
            $dates[(int) $row->student_id][(int) $row->plan_point_id] = CarbonImmutable::parse($row->completed_on)->toDateString();
        }

        return $dates;
    }

    /**
     * @param  array<int, string>  $completionDates
     * @return array<string, mixed>
     */
    private function studentTrackingPayload(
        StudentMonthlyPlan $studentPlan,
        CarbonImmutable $asOfDate,
        array $completionDates,
        MonthlyPlan $monthlyPlan,
        ?CarbonImmutable $selectedDate = null,
    ): array {
        $selectedDate ??= $asOfDate;
        $items = collect();
        foreach ($studentPlan->days as $day) {
            $scheduledDate = $day->date?->toImmutable()->startOfDay();
            foreach ($day->items as $item) {
                if ($item->plan_point_id === null || $scheduledDate === null) {
                    continue;
                }

                $pointId = (int) $item->plan_point_id;
                $items->push([
                    'plan_point_id' => $pointId,
                    'name' => (string) ($item->planPoint?->name ?? ''),
                    'points' => (int) ($item->planPoint?->points ?? 0),
                    'weight' => (float) $item->weight,
                    'scheduled_date' => $scheduledDate,
                    'completed_on' => isset($completionDates[$pointId])
                        ? CarbonImmutable::parse($completionDates[$pointId])->startOfDay()
                        : null,
                ]);
            }
        }

        $due = $items->filter(static fn (array $item): bool => $item['scheduled_date']->lte($asOfDate));
        $completed = $items->filter(static fn (array $item): bool => $item['completed_on']?->lte($asOfDate) === true);
        $dueCompleted = $due->filter(static fn (array $item): bool => $item['completed_on']?->lte($asOfDate) === true);
        $futureCompleted = $completed->filter(static fn (array $item): bool => $item['scheduled_date']->gt($asOfDate));
        $todayItems = $items->filter(static fn (array $item): bool => $item['scheduled_date']->isSameDay($selectedDate));
        $todayCompleted = $todayItems->filter(static fn (array $item): bool => $item['completed_on']?->lte($asOfDate) === true);
        $nextPlanItem = $items
            ->filter(static fn (array $item): bool => $item['scheduled_date']->gt($selectedDate))
            ->sortBy(static fn (array $item): string => $item['scheduled_date']->toDateString())
            ->first();
        $nextDate = is_array($nextPlanItem) ? $nextPlanItem['scheduled_date'] : null;
        $nextItems = $nextDate instanceof CarbonImmutable
            ? $items->filter(static fn (array $item): bool => $item['scheduled_date']->isSameDay($nextDate))
            : collect();

        $progressPercentage = $this->weightedPercentage($completed, $items);
        $expectedPercentage = $this->weightedPercentage($due, $items);
        $adherencePercentage = $this->weightedPercentage($dueCompleted, $due);
        $usesPositiveWeights = (float) $items->sum('weight') > 0;
        $statusItems = $usesPositiveWeights
            ? $items->filter(static fn (array $item): bool => (float) $item['weight'] > 0)
            : $items;
        $statusDue = $statusItems->filter(static fn (array $item): bool => $item['scheduled_date']->lte($asOfDate));
        $statusCompleted = $statusItems->filter(static fn (array $item): bool => $item['completed_on']?->lte($asOfDate) === true);
        $statusDueCompleted = $statusDue->filter(static fn (array $item): bool => $item['completed_on']?->lte($asOfDate) === true);
        $statusFutureCompleted = $statusCompleted->filter(static fn (array $item): bool => $item['scheduled_date']->gt($asOfDate));
        $planMismatch = $studentPlan->student?->plan_type_id === null
            || (int) $studentPlan->student->plan_type_id !== (int) $studentPlan->plan_id;
        $status = match (true) {
            $planMismatch => 'plan_mismatch',
            $items->isEmpty() && $studentPlan->status === StudentMonthlyPlan::STATUS_EXHAUSTED => 'completed',
            $statusDue->isEmpty() => 'not_due',
            $statusDueCompleted->count() < $statusDue->count() => 'behind',
            $statusFutureCompleted->isNotEmpty() => 'ahead',
            $statusCompleted->count() === $statusItems->count() => 'completed',
            default => 'on_track',
        };

        return [
            'student_id' => (int) $studentPlan->student_id,
            'student_name' => (string) ($studentPlan->student?->full_name ?? ''),
            'monthly_plan_id' => (int) $monthlyPlan->id,
            'student_monthly_plan_id' => (int) $studentPlan->id,
            'plan_name' => (string) ($studentPlan->plan?->name ?? ''),
            'status' => $status,
            'is_on_track' => in_array($status, ['on_track', 'ahead', 'completed'], true),
            'adherence_percentage' => $adherencePercentage,
            'progress_percentage' => $progressPercentage,
            'expected_percentage' => $expectedPercentage,
            'variance_percentage' => $progressPercentage !== null && $expectedPercentage !== null
                ? $progressPercentage - $expectedPercentage
                : null,
            'completed_items_count' => $statusCompleted->count(),
            'total_items_count' => $statusItems->count(),
            'due_items_count' => $statusDue->count(),
            'due_completed_items_count' => $statusDueCompleted->count(),
            'future_completed_items_count' => $statusFutureCompleted->count(),
            'today' => [
                'planned_items_count' => $todayItems->count(),
                'completed_items_count' => $todayCompleted->count(),
                'percentage' => $this->weightedPercentage($todayCompleted, $todayItems),
                'items' => $todayItems->map(static fn (array $item): array => [
                    'plan_point_id' => $item['plan_point_id'],
                    'name' => $item['name'],
                    'points' => $item['points'],
                    'completed' => $item['completed_on']?->lte($asOfDate) === true,
                ])->values()->all(),
            ],
            'next' => [
                'date' => $nextDate?->toDateString(),
                'items' => $nextItems->map(static fn (array $item): array => [
                    'plan_point_id' => $item['plan_point_id'],
                    'name' => $item['name'],
                    'points' => $item['points'],
                ])->values()->all(),
            ],
            'report_url' => route('admin.daily-follow-up.student-report', [
                'student' => $studentPlan->student_id,
                'group_id' => $monthlyPlan->group_id,
                'date' => $selectedDate->toDateString(),
            ], false),
        ];
    }

    private function weightedPercentage(Collection $completed, Collection $total): ?int
    {
        if ($total->isEmpty()) {
            return null;
        }

        $totalWeight = (float) $total->sum('weight');
        if ($totalWeight > 0) {
            return (int) round(((float) $completed->sum('weight') / $totalWeight) * 100);
        }

        return (int) round(($completed->count() / $total->count()) * 100);
    }

    /** @return Collection<int, EvaluationStudent> */
    private function evaluationRows(Group $group, Student $student, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return EvaluationStudent::query()
            ->join('evaluations', 'evaluations_users.evaluation_id', '=', 'evaluations.id')
            ->where('evaluations.group_id', $group->id)
            ->whereDate('evaluations.date', '>=', $start->toDateString())
            ->whereDate('evaluations.date', '<=', $end->toDateString())
            ->where(function ($query) use ($student): void {
                $query
                    ->where('evaluations_users.student_id', $student->id)
                    ->orWhere(function ($legacy) use ($student): void {
                        $legacy
                            ->whereNull('evaluations_users.student_id')
                            ->where('evaluations_users.user_id', $student->id);
                    });
            })
            ->orderBy('evaluations.date')
            ->get([
                'evaluations_users.*',
                'evaluations.date as evaluation_date',
                'evaluations.evaluation_type',
            ]);
    }

    /** @return Collection<int, object> */
    private function homeworkRows(Group $group, Student $student, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return HomeworkStudentPoint::query()
            ->join('homeworks', 'homework_student_points.homework_id', '=', 'homeworks.id')
            ->where('homeworks.group_id', $group->id)
            ->where('homework_student_points.student_id', $student->id)
            ->whereDate('homeworks.date', '>=', $start->toDateString())
            ->whereDate('homeworks.date', '<=', $end->toDateString())
            ->orderBy('homeworks.date')
            ->get([
                'homework_student_points.plan_point_id',
                'homework_student_points.is_done',
                'homework_student_points.is_next_homework',
                'homeworks.date as homework_date',
            ]);
    }

    /** @return array<string, mixed> */
    private function attendancePayload(Collection $evaluations): array
    {
        $counts = [
            'present' => $evaluations->where('attendances', EvaluationStudent::ATTENDANCE_PRESENT)->count(),
            'excused' => $evaluations->where('attendances', EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE)->count(),
            'absent' => $evaluations->where('attendances', EvaluationStudent::ATTENDANCE_ABSENCE)->count(),
            'exempt' => $evaluations->whereIn('attendances', [
                EvaluationStudent::ATTENDANCE_FROZEN,
                EvaluationStudent::ATTENDANCE_EXEMPT,
            ])->count(),
        ];
        $counted = $counts['present'] + $counts['excused'] + $counts['absent'];

        return [
            'counts' => $counts,
            'rate' => $counted > 0 ? (int) round(($counts['present'] / $counted) * 100) : null,
            'labels' => $evaluations->map(static fn ($row): string => CarbonImmutable::parse($row->evaluation_date)->toDateString())->all(),
            'present_series' => $evaluations->map(static fn ($row): ?int => match ((int) $row->attendances) {
                EvaluationStudent::ATTENDANCE_PRESENT => 100,
                EvaluationStudent::ATTENDANCE_EXCUSED_ABSENCE,
                EvaluationStudent::ATTENDANCE_ABSENCE => 0,
                default => null,
            })->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function evaluationPayload(Collection $evaluations): array
    {
        $rows = $evaluations->map(function ($row): array {
            $primaryField = (int) $row->evaluation_type === Evaluation::TYPE_TAJWID ? 'tajwid' : 'alhifz';
            $scores = collect([$row->{$primaryField}, $row->warud, $row->akhlaqi])
                ->filter(static fn ($value): bool => $value !== null)
                ->map(static fn ($value): int => (int) $value);

            return [
                'date' => CarbonImmutable::parse($row->evaluation_date)->toDateString(),
                'alhifz' => $primaryField === 'alhifz' && $row->alhifz !== null ? (int) $row->alhifz : null,
                'tajwid' => $primaryField === 'tajwid' && $row->tajwid !== null ? (int) $row->tajwid : null,
                'warud' => $row->warud !== null ? (int) $row->warud : null,
                'akhlaqi' => $row->akhlaqi !== null ? (int) $row->akhlaqi : null,
                'average' => $scores->isNotEmpty() ? round((float) $scores->average(), 1) : null,
            ];
        });

        return [
            'labels' => $rows->pluck('date')->all(),
            'alhifz' => $rows->pluck('alhifz')->all(),
            'tajwid' => $rows->pluck('tajwid')->all(),
            'warud' => $rows->pluck('warud')->all(),
            'akhlaqi' => $rows->pluck('akhlaqi')->all(),
            'average' => $rows->whereNotNull('average')->avg('average') !== null
                ? round((float) $rows->whereNotNull('average')->avg('average'), 1)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function timelinePayload(
        StudentMonthlyPlan $studentPlan,
        array $completionDates,
        Collection $evaluations,
        Collection $homeworkRows,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): array {
        $items = collect();
        foreach ($studentPlan->days as $day) {
            $scheduledDate = $day->date?->toImmutable()->toDateString();
            foreach ($day->items as $item) {
                if ($scheduledDate === null || $item->plan_point_id === null) {
                    continue;
                }
                $pointId = (int) $item->plan_point_id;
                $items->push([
                    'point_id' => $pointId,
                    'scheduled_date' => $scheduledDate,
                    'completed_date' => $completionDates[$pointId] ?? null,
                    'weight' => (float) $item->weight,
                ]);
            }
        }

        $labels = collect($studentPlan->days->pluck('date')->map(
            static fn ($date): ?string => $date?->format('Y-m-d'),
        ))
            ->merge($evaluations->map(static fn ($row): string => CarbonImmutable::parse($row->evaluation_date)->toDateString()))
            ->merge($homeworkRows->map(static fn ($row): string => CarbonImmutable::parse($row->homework_date)->toDateString()))
            ->filter(static fn ($date): bool => filled($date) && $date >= $periodStart->toDateString() && $date <= $periodEnd->toDateString())
            ->unique()
            ->sort()
            ->values();

        $totalWeight = (float) $items->sum('weight');
        $totalCount = $items->count();
        $plannedPointIds = $items->pluck('point_id')->map(static fn ($id): int => (int) $id)->unique()->all();
        $percentageThrough = static function (Collection $rows) use ($totalWeight, $totalCount): int {
            if ($totalCount === 0) {
                return 0;
            }
            if ($totalWeight > 0) {
                return (int) round(((float) $rows->sum('weight') / $totalWeight) * 100);
            }

            return (int) round(($rows->count() / $totalCount) * 100);
        };

        return [
            'labels' => $labels->all(),
            'expected_cumulative' => $labels->map(fn (string $date): int => $percentageThrough(
                $items->filter(static fn (array $item): bool => $item['scheduled_date'] <= $date),
            ))->all(),
            'completed_cumulative' => $labels->map(fn (string $date): int => $percentageThrough(
                $items->filter(static fn (array $item): bool => filled($item['completed_date']) && $item['completed_date'] <= $date),
            ))->all(),
            'planned_daily' => $labels->map(static fn (string $date): int => $items->where('scheduled_date', $date)->count())->all(),
            'completed_daily' => $labels->map(static fn (string $date): int => $homeworkRows
                ->filter(static fn ($row): bool => CarbonImmutable::parse($row->homework_date)->toDateString() === $date
                    && (bool) $row->is_done
                    && in_array((int) $row->plan_point_id, $plannedPointIds, true))
                ->count())->all(),
            'assigned_daily' => $labels->map(static fn (string $date): int => $homeworkRows
                ->filter(static fn ($row): bool => CarbonImmutable::parse($row->homework_date)->toDateString() === $date
                    && (bool) $row->is_next_homework
                    && in_array((int) $row->plan_point_id, $plannedPointIds, true))
                ->count())->all(),
            'outside_plan_daily' => $labels->map(static fn (string $date): int => $homeworkRows
                ->filter(static fn ($row): bool => CarbonImmutable::parse($row->homework_date)->toDateString() === $date
                    && ((bool) $row->is_done || (bool) $row->is_next_homework)
                    && ! in_array((int) $row->plan_point_id, $plannedPointIds, true))
                ->count())->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function reportSummary(array $tracking, Collection $evaluations, Collection $homeworkRows): array
    {
        $attendance = $this->attendancePayload($evaluations);
        $evaluation = $this->evaluationPayload($evaluations);

        return [
            'attendance_rate' => $attendance['rate'],
            'evaluation_average' => $evaluation['average'] !== null
                ? (int) round(((float) $evaluation['average'] / 10) * 100)
                : null,
            'plan_progress' => $tracking['progress_percentage'],
            'plan_adherence' => $tracking['adherence_percentage'],
            'assigned_items_count' => $homeworkRows->where('is_next_homework', true)->count(),
            'completed_items_count' => (int) $tracking['completed_items_count'],
        ];
    }

    private function insightKey(array $tracking, Collection $evaluations, int $outsidePlanItems = 0): string
    {
        if ($tracking['status'] === 'plan_mismatch') {
            return 'plan_mismatch';
        }
        if ($tracking['status'] === 'completed') {
            return 'plan_completed';
        }
        if ($tracking['status'] === 'behind') {
            return 'behind_plan';
        }
        if ($tracking['status'] === 'ahead') {
            return 'ahead_of_plan';
        }
        if ($tracking['status'] === 'not_due') {
            return 'plan_not_due';
        }
        if ($outsidePlanItems > 0) {
            return 'outside_plan_work';
        }
        if ($evaluations->isEmpty()) {
            return 'no_evaluations';
        }

        return 'on_track';
    }

    /** @return array<string, mixed> */
    private function missingPlanPayload(?int $groupId = null, ?CarbonImmutable $date = null): array
    {
        $query = [];
        if ($groupId !== null) {
            $query['group_id'] = $groupId;
            $group = Group::query()->find($groupId, ['id', 'center_id']);
            if ($group instanceof Group) {
                $query['center_id'] = (int) $group->center_id;
            }
        }
        if ($date !== null) {
            $query['month'] = $date->month;
            $query['year'] = $date->year;
            $query['start_date'] = $date->startOfMonth()->toDateString();
            $query['end_date'] = $date->endOfMonth()->toDateString();
        }

        return [
            'available' => false,
            'required' => true,
            'progress_available' => false,
            'monthly_plan' => null,
            'summary' => null,
            'students' => [],
            'create_url' => $groupId !== null && $date !== null
                ? route('admin.monthly-plans.create', $query, false)
                : null,
        ];
    }

    private function periodStart(MonthlyPlan $monthlyPlan): CarbonImmutable
    {
        return $monthlyPlan->start_date?->toImmutable()->startOfDay()
            ?? CarbonImmutable::create((int) $monthlyPlan->year, (int) $monthlyPlan->month, 1)->startOfDay();
    }

    /** @return array<string, mixed> */
    private function monthlyPlanPayload(MonthlyPlan $monthlyPlan, bool $canView): array
    {
        return [
            'id' => (int) $monthlyPlan->id,
            'group_id' => (int) $monthlyPlan->group_id,
            'start_date' => $this->periodStart($monthlyPlan)->toDateString(),
            'end_date' => $this->periodEnd($monthlyPlan)->toDateString(),
            'period_label' => $this->periodStart($monthlyPlan)->toDateString().' - '.$this->periodEnd($monthlyPlan)->toDateString(),
            'edit_url' => $canView ? route('admin.monthly-plans.edit', $monthlyPlan, false) : null,
            'public_report_url' => $canView && filled($monthlyPlan->ulid)
                ? route('monthly-plans.report', ['publicId' => $monthlyPlan->ulid], false)
                : null,
        ];
    }

    private function periodEnd(MonthlyPlan $monthlyPlan): CarbonImmutable
    {
        return $monthlyPlan->end_date?->toImmutable()->startOfDay()
            ?? CarbonImmutable::create((int) $monthlyPlan->year, (int) $monthlyPlan->month, 1)->endOfMonth()->startOfDay();
    }

    private function effectiveAsOfDate(CarbonImmutable $selectedDate): CarbonImmutable
    {
        $timezone = (string) ($this->settings->get()['timezone'] ?? 'Asia/Amman');
        $today = CarbonImmutable::now($timezone)->toDateString();
        $selected = $selectedDate->toDateString();

        return CarbonImmutable::parse(min($selected, $today))->startOfDay();
    }
}

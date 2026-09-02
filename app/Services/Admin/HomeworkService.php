<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkStudent;
use App\Models\HomeworkStudentPoint;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Services\System\DateTimeFormatterService;
use App\Support\GroupMonthlyPlanCoverage;
use App\Support\GroupWorkingDays;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeworkService
{
    private const POINT_WINDOW_SIZE = 20;

    private const PDF_ASSIGNMENT_COLUMNS = 5;

    public function __construct(
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly AdminDataScopeService $dataScope,
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
        $centerId = (int) ($filters['center_id'] ?? 0);
        $groupId = (int) ($filters['group_id'] ?? 0);
        $sortMap = [
            'id' => 'homeworks.id',
            'date' => 'homeworks.date',
            'center_name' => 'centers.name',
            'group_name' => 'groups.name',
            'admin_name' => 'admins.name',
            'created_at' => 'homeworks.created_at',
        ];

        $sortColumn = $sortMap[$sortBy] ?? 'homeworks.id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $rows = Homework::query()
            ->leftJoin('groups', 'homeworks.group_id', '=', 'groups.id')
            ->leftJoin('centers', 'groups.center_id', '=', 'centers.id')
            ->leftJoin('users as admins', 'homeworks.admin_id', '=', 'admins.id')
            ->leftJoin('homework_students', 'homeworks.id', '=', 'homework_students.homework_id')
            ->leftJoin('students as homework_scope_students', 'homework_students.student_id', '=', 'homework_scope_students.id')
            ->leftJoin('homework_student_points', 'homework_students.id', '=', 'homework_student_points.homework_student_id')
            ->select([
                'homeworks.id',
                'homeworks.date',
                'homeworks.center_id',
                'homeworks.group_id',
                'homeworks.admin_id',
                'homeworks.created_at',
                'centers.name as center_name',
                'groups.name as group_name',
                'admins.name as admin_name',
                DB::raw('COUNT(DISTINCT homework_students.id) as students_count'),
                DB::raw('COALESCE(SUM(CASE WHEN homework_student_points.is_done = true THEN 1 ELSE 0 END), 0) as completed_points_count'),
            ])
            ->when($centerId > 0, fn ($query) => $query->where('groups.center_id', $centerId))
            ->when($groupId > 0, fn ($query) => $query->where('homeworks.group_id', $groupId))
            ->when($this->dataScope->shouldScope(), fn ($query) => $this->dataScope->applyStudentAccess($query, 'homework_scope_students'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('homeworks.id', 'like', "%{$search}%")
                        ->orWhere('homeworks.date', 'like', "%{$search}%")
                        ->orWhere('centers.name', 'like', "%{$search}%")
                        ->orWhere('groups.name', 'like', "%{$search}%")
                        ->orWhere('admins.name', 'like', "%{$search}%");
                });
            })
            ->groupBy([
                'homeworks.id',
                'homeworks.date',
                'homeworks.center_id',
                'homeworks.group_id',
                'homeworks.admin_id',
                'homeworks.created_at',
                'centers.name',
                'groups.name',
                'admins.name',
            ])
            ->orderBy($sortColumn, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $rows->setCollection(
            $rows->getCollection()->map(function ($row) {
                $row->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($row->created_at));
                $row->setAttribute(
                    'date_formatted',
                    Carbon::parse((string) $row->date)->locale(app()->getLocale())->translatedFormat('l ، d/m/Y'),
                );

                return $row;
            }),
        );

        return $rows;
    }

    /**
     * @return array<int, array{id: int, name: string, working_days: array<int, string>, groups: array<int, array{id: int, name: string, center_id: int, working_days: array<int, string>}>}>
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
            ->get(['id', 'name', 'working_days'])
            ->map(static fn (Center $center): array => [
                'id' => (int) $center->id,
                'name' => (string) $center->name,
                'working_days' => is_array($center->working_days) ? $center->working_days : [],
                'groups' => $center->groups
                    ->map(static fn (Group $group): array => [
                        'id' => (int) $group->id,
                        'name' => (string) $group->name,
                        'center_id' => (int) $group->center_id,
                        'working_days' => is_array($group->working_days) ? $group->working_days : [],
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array{selected_center_id: ?int, selected_group_id: ?int, selected_date: string, students: array<int, array<string, mixed>>, existing_homework_id: ?int}
     */
    public function createFormPayload(?int $centerId, ?int $groupId, ?string $date): array
    {
        $resolvedDate = $this->resolveDate($date);
        $resolvedCenterId = $centerId !== null && $centerId > 0 ? $centerId : null;
        $resolvedGroupId = $groupId !== null && $groupId > 0 ? $groupId : null;
        $group = null;

        if ($resolvedGroupId !== null) {
            $group = Group::query()
                ->tap(fn ($query) => $this->dataScope->applyGroupAccess($query, 'groups'))
                ->with('center:id,name,working_days')
                ->find($resolvedGroupId);

            abort_unless($group instanceof Group, 404);
            $resolvedCenterId = (int) $group->center_id;
            $resolvedDate = $this->resolveWorkingDate($group, $resolvedDate);
        }

        if ($resolvedGroupId === null && $resolvedCenterId !== null) {
            $centerExists = Center::query()
                ->active()
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
                'existing_homework_id' => null,
            ];
        }

        $existingHomeworkId = Homework::query()
            ->where('group_id', $resolvedGroupId)
            ->whereDate('date', $resolvedDate)
            ->value('id');

        return [
            'selected_center_id' => $resolvedCenterId,
            'selected_group_id' => $resolvedGroupId,
            'selected_date' => $resolvedDate,
            'students' => $existingHomeworkId === null ? $this->studentRowsForCreate($resolvedGroupId, $resolvedDate) : [],
            'existing_homework_id' => $existingHomeworkId !== null ? (int) $existingHomeworkId : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function editStudentRows(Homework $homework): array
    {
        $homework->load([
            'group:id,name,center_id',
            'students.student.center',
            'students.student.groups',
            'students.student.plan',
            'students.plan',
            'students.currentPlanPoint',
            'students.points.planPoint',
        ]);

        $groupName = (string) ($homework->group?->name ?? '');
        $historicalRows = $homework->students
            ->filter(fn (HomeworkStudent $row): bool => $row->student !== null && $this->dataScope->canAccessStudent($row->student))
            ->keyBy('student_id');
        $currentRows = collect($this->studentRowsForCreate(
            (int) $homework->group_id,
            $homework->date?->toDateString() ?? now()->toDateString(),
        ))->keyBy('student_id');

        return $currentRows
            ->map(function (array $row, int $studentId) use ($groupName, $historicalRows): array {
                $historical = $historicalRows->get($studentId);

                return $historical instanceof HomeworkStudent
                    ? $this->studentRowDataFromHomework($historical, $groupName)
                    : $row;
            })
            ->merge($historicalRows
                ->reject(fn (HomeworkStudent $row): bool => $currentRows->has((int) $row->student_id))
                ->map(fn (HomeworkStudent $row): array => $this->studentRowDataFromHomework($row, $groupName)))
            ->sortBy(static fn (array $row): string => (string) ($row['full_name'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function pdfPayload(Homework $homework): array
    {
        app()->setLocale('ar');

        $homework->loadMissing([
            'group.center:id,name,working_days',
            'center:id,name,working_days',
            'admin:id,name',
        ]);

        $nextHomeworkDate = $this->nextHomeworkDate($homework);

        $students = collect($this->editStudentRows($homework))
            ->map(function (array $student): array {
                $points = collect($student['points'] ?? []);

                $student['done_points'] = $points
                    ->filter(static fn (array $point): bool => (bool) ($point['is_done'] ?? false))
                    ->values()
                    ->all();
                $student['next_homework_points'] = $points
                    ->filter(static fn (array $point): bool => (bool) ($point['is_next_homework'] ?? false))
                    ->values()
                    ->all();
                $student['pending_points'] = $points
                    ->reject(static fn (array $point): bool => (bool) ($point['is_done'] ?? false) || (bool) ($point['is_next_homework'] ?? false))
                    ->values()
                    ->all();
                $student['pdf_name'] = $this->threePartStudentName((string) ($student['full_name'] ?? ''));
                $student['pdf_homework_cells'] = $this->pdfHomeworkCells(
                    collect($student['next_homework_points'])
                        ->pluck('name')
                        ->filter(static fn ($name): bool => is_string($name) && trim($name) !== '')
                        ->values()
                        ->all()
                );

                return $student;
            })
            ->filter(static fn (array $student): bool => $nextHomeworkDate !== null
                && (int) ($student['is_active'] ?? Student::STATUS_INACTIVE) === Student::STATUS_ACTIVE
                && count($student['next_homework_points'] ?? []) > 0)
            ->values();

        $date = $homework->date?->copy()->locale(app()->getLocale());
        $nextDate = $nextHomeworkDate?->copy()->locale(app()->getLocale());
        $generatedAt = now()->locale(app()->getLocale());
        $fileDate = $homework->date?->format('Y-m-d') ?? now()->toDateString();
        $groupName = $homework->group?->name;
        $title = filled($groupName)
            ? __('homeworks.pdf_group_homework_title', ['group' => $groupName])
            : __('homeworks.pdf_center_homework_title', ['center' => $homework->center?->name ?? __('homeworks.pdf_none')]);

        return [
            'file_name' => "homework-{$homework->id}-{$fileDate}.pdf",
            'homework' => [
                'id' => (int) $homework->id,
                'date' => $homework->date?->toDateString(),
                'date_formatted' => $date?->translatedFormat('d/m/Y') ?? '',
                'date_numeric' => $date?->format('Y / n / j') ?? '',
                'day_name' => $date?->translatedFormat('l') ?? '',
                'date_full' => $date?->translatedFormat('l ، j F ، Y') ?? '',
                'next_homework_date' => $nextDate?->toDateString(),
                'next_homework_date_numeric' => $nextDate?->format('Y / n / j') ?? '',
                'next_homework_day_name' => $nextDate?->translatedFormat('l') ?? '',
                'center_name' => $homework->center?->name,
                'group_name' => $groupName,
                'admin_name' => $homework->admin?->name,
                'generated_at' => $generatedAt->translatedFormat('d/m/Y H:i'),
            ],
            'pdf' => [
                'title' => $title,
                'assignment_columns' => range(1, self::PDF_ASSIGNMENT_COLUMNS),
                'logo_data_uri' => $this->pdfLogoDataUri(),
                'fonts' => $this->pdfFontDataUris(),
            ],
            'students' => $students->all(),
            'totals' => [
                'students_count' => $students->count(),
                'completed_points_count' => $students->sum(static fn (array $student): int => count($student['done_points'] ?? [])),
                'next_homework_count' => $students->sum(static fn (array $student): int => count($student['next_homework_points'] ?? [])),
                'manual_adjustments_total' => $students->sum(static fn (array $student): int => (int) ($student['points_adjustment'] ?? 0)),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function pdfHomeworkCells(array $names): array
    {
        $cells = array_fill(0, self::PDF_ASSIGNMENT_COLUMNS, '');
        $lastIndex = self::PDF_ASSIGNMENT_COLUMNS - 1;

        foreach ($names as $index => $name) {
            if ($index < $lastIndex) {
                $cells[$index] = $name;

                continue;
            }

            $cells[$lastIndex] = trim($cells[$lastIndex]."\n".$name);
        }

        return $cells;
    }

    private function threePartStudentName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        return implode(' ', array_slice($parts, 0, 3)) ?: $name;
    }

    private function nextHomeworkDate(Homework $homework): ?Carbon
    {
        if ($homework->date === null || $homework->group === null) {
            return null;
        }

        $workingDays = $this->workingDayLookup($homework->group);
        if ($workingDays === []) {
            return null;
        }

        $cursor = $homework->date->copy()->addDay();
        for ($index = 0; $index < 31; $index++) {
            if (isset($workingDays[$this->dayName($cursor)])) {
                return $cursor;
            }

            $cursor = $cursor->addDay();
        }

        return null;
    }

    private function pdfLogoDataUri(): ?string
    {
        return $this->assetDataUri(public_path('media/logos/logo.png'), 'image/png');
    }

    /**
     * @return array<string, ?string>
     */
    private function pdfFontDataUris(): array
    {
        return [
            'naskh_regular' => $this->assetDataUri('/usr/share/fonts/truetype/noto/NotoNaskhArabic-Regular.ttf', 'font/ttf'),
            'naskh_bold' => $this->assetDataUri('/usr/share/fonts/truetype/noto/NotoNaskhArabic-Bold.ttf', 'font/ttf'),
            'kufi_regular' => $this->assetDataUri('/usr/share/fonts/truetype/noto/NotoKufiArabic-Regular.ttf', 'font/ttf'),
            'kufi_bold' => $this->assetDataUri('/usr/share/fonts/truetype/noto/NotoKufiArabic-Bold.ttf', 'font/ttf'),
        ];
    }

    private function assetDataUri(string $path, string $mime): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return "data:{$mime};base64,".base64_encode($contents);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Homework
    {
        $groupId = (int) $data['group_id'];
        $date = $this->resolveDate((string) $data['date']);
        $group = Group::query()
            ->tap(fn ($query) => $this->dataScope->applyGroupAccess($query, 'groups'))
            ->with('center:id,name,working_days')
            ->find($groupId);

        abort_unless($group instanceof Group, 404);
        $centerId = (int) $group->center_id;

        if (! GroupWorkingDays::isConfigured($group->working_days)) {
            throw ValidationException::withMessages([
                'date' => __('groups.working_days_not_configured'),
            ]);
        }

        if (! $this->isWorkingDate($group, $date)) {
            throw ValidationException::withMessages([
                'date' => __('homeworks.date_not_in_group_working_days'),
            ]);
        }

        if (! GroupMonthlyPlanCoverage::exists((int) $group->id, $date)) {
            throw ValidationException::withMessages([
                'date' => __('monthly_plans.required_for_follow_up_date'),
            ]);
        }

        $exists = Homework::query()
            ->where('group_id', $groupId)
            ->whereDate('date', $date)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'date' => __('homeworks.already_exists_for_group_date'),
            ]);
        }

        return DB::transaction(function () use ($centerId, $date, $data, $groupId): Homework {
            $homework = Homework::query()->create([
                'date' => $date,
                'center_id' => $centerId,
                'group_id' => $groupId,
                'admin_id' => Auth::id(),
            ]);

            $this->syncHomeworkRows($homework, $data['items'] ?? []);

            return $homework->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Homework $homework, array $data): Homework
    {
        $this->dataScope->abortUnlessCanAccessHomework($homework);

        return DB::transaction(function () use ($homework, $data): Homework {
            $this->syncHomeworkRows($homework, $data['items'] ?? []);

            return $homework->refresh();
        });
    }

    public function delete(Homework $homework): void
    {
        $this->dataScope->abortUnlessCanAccessHomework($homework);

        $homework->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pointHistory(Student $student): array
    {
        $this->dataScope->abortUnlessCanAccessStudent($student);

        return StudentPointTransaction::query()
            ->with(['homework:id,date', 'planPoint:id,name,points'])
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StudentPointTransaction $transaction): array => [
                'id' => $transaction->id,
                'date' => $transaction->created_at?->locale(app()->getLocale())->translatedFormat('l ، j F ، Y H:i'),
                'homework_date' => $transaction->homework?->date?->locale(app()->getLocale())->translatedFormat('l ، j F ، Y'),
                'plan_point_name' => $transaction->type === StudentPointTransaction::TYPE_HOMEWORK_MANUAL_ADJUSTMENT
                    ? __('homeworks.manual_adjustment')
                    : $transaction->planPoint?->name,
                'points' => $transaction->points,
                'balance_before' => $transaction->balance_before,
                'balance_after' => $transaction->balance_after,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function studentRowsForCreate(int $groupId, string $date): array
    {
        $students = Student::query()
            ->with([
                'plan:id,name',
                'groups' => fn ($query) => $query->whereKey($groupId)->select(['groups.id', 'groups.name']),
            ])
            ->whereHas('groups', fn ($query) => $query->whereKey($groupId))
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->where('is_active', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->orderBy('id')
            ->get([
                'id',
                'full_name',
                'plan_type_id',
                'current_plan_point_id',
                'points_balance',
            ]);

        return $students
            ->map(fn (Student $student): array => $this->studentRowDataForCreate($student, $date, $groupId))
            ->all();
    }

    private function studentRowDataForCreate(Student $student, string $date, int $groupId): array
    {
        $planId = $student->plan_type_id !== null ? (int) $student->plan_type_id : null;
        $currentPlanPoint = $planId !== null ? $this->latestCompletedPlanPoint($student, $planId) : null;
        $previousNextHomeworkAssignments = $planId !== null
            ? $this->previousNextHomeworkAssignments($student, $planId, $date, $groupId)
            : [];
        $points = $planId !== null
            ? $this->nextPlanPoints($student, $planId, $currentPlanPoint)
            : collect();

        if ($planId !== null && $previousNextHomeworkAssignments !== []) {
            $points = $this->includePreviousNextHomeworkPoints($points, $planId, $previousNextHomeworkAssignments);
        }

        return [
            'student_id' => (int) $student->id,
            'full_name' => (string) $student->full_name,
            'plan_id' => $planId,
            'plan_name' => $student->plan?->name,
            'group_name' => $student->groups->pluck('name')->implode(', '),
            'points_balance' => (int) $student->points_balance,
            'points_adjustment' => 0,
            'points_adjustment_original' => 0,
            'current_plan_point_name' => $currentPlanPoint?->name,
            'points' => $points
                ->map(fn (PlanPoint $point): array => $this->pointPayload(
                    $point,
                    $previousNextHomeworkAssignments[(int) $point->id] ?? null,
                ))
                ->all(),
        ];
    }

    private function studentRowDataFromHomework(HomeworkStudent $row, string $groupName): array
    {
        $student = $row->student;

        return [
            'student_id' => (int) $row->student_id,
            'full_name' => (string) ($student?->full_name ?? ''),
            'plan_id' => $row->plan_id !== null ? (int) $row->plan_id : null,
            'plan_name' => $row->plan?->name ?? $student?->plan?->name,
            'group_name' => $groupName,
            'is_active' => (int) ($student?->is_active ?? Student::STATUS_INACTIVE),
            'points_balance' => (int) ($student?->points_balance ?? $row->points_balance_after),
            'points_balance_before' => (int) $row->points_balance_before,
            'points_adjustment' => (int) $row->points_adjustment,
            'points_adjustment_original' => (int) $row->points_adjustment,
            'points_balance_after' => (int) $row->points_balance_after,
            'current_plan_point_name' => $row->currentPlanPoint?->name,
            'points' => $row->points
                ->sortBy('sort_order')
                ->values()
                ->map(function (HomeworkStudentPoint $point): array {
                    $planPoint = $point->planPoint;

                    return [
                        'id' => $point->id,
                        'plan_point_id' => (int) $point->plan_point_id,
                        'name' => (string) ($planPoint?->name ?? ''),
                        'points' => (int) ($planPoint?->points ?? $point->awarded_points),
                        'is_done' => (bool) $point->is_done,
                        'is_next_homework' => (bool) $point->is_next_homework,
                        'is_previous_next_homework' => false,
                        'previous_next_homework_date' => null,
                        'previous_next_homework_date_formatted' => null,
                        'is_locked' => $point->awarded_at !== null,
                    ];
                })
                ->all(),
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function syncHomeworkRows(Homework $homework, array $items): void
    {
        $studentIds = collect($items)
            ->filter(static fn ($item): bool => is_array($item))
            ->pluck('student_id')
            ->filter(static fn ($value): bool => (int) $value > 0)
            ->map(static fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        if ($studentIds === []) {
            return;
        }

        /** @var EloquentCollection<int, Student> $students */
        $students = Student::query()
            ->with('plan:id,name')
            ->whereIn('id', $studentIds)
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->get()
            ->keyBy('id');

        $existingRows = HomeworkStudent::query()
            ->with('points')
            ->where('homework_id', $homework->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $studentId = (int) ($item['student_id'] ?? 0);
            /** @var Student|null $student */
            $student = $students->get($studentId);
            if ($student === null) {
                continue;
            }

            $planId = $student->plan_type_id !== null ? (int) $student->plan_type_id : null;
            $currentPlanPoint = $planId !== null ? $this->latestCompletedPlanPoint($student, $planId) : null;
            $homeworkStudent = $existingRows->get($studentId);

            if ($homeworkStudent === null) {
                $homeworkStudent = HomeworkStudent::query()->create([
                    'homework_id' => $homework->id,
                    'student_id' => $studentId,
                    'plan_id' => $planId,
                    'current_plan_point_id' => $currentPlanPoint?->id,
                    'points_balance_before' => (int) $student->points_balance,
                    'points_adjustment' => 0,
                    'points_balance_after' => (int) $student->points_balance,
                ]);
            } else {
                $homeworkStudent->update([
                    'plan_id' => $planId,
                    'current_plan_point_id' => $homeworkStudent->current_plan_point_id ?? $currentPlanPoint?->id,
                ]);
            }

            $this->syncHomeworkStudentPoints($homework, $homeworkStudent, $student, $item['points'] ?? []);
            $this->applyManualPointsAdjustment($homework, $homeworkStudent, $student, (int) ($item['points_adjustment'] ?? 0));

            $student->refresh();
            $homeworkStudent->update([
                'points_balance_after' => (int) $student->points_balance,
            ]);
        }
    }

    /**
     * @param  array<int, mixed>  $points
     */
    private function syncHomeworkStudentPoints(
        Homework $homework,
        HomeworkStudent $homeworkStudent,
        Student $student,
        array $points,
    ): void {
        $planId = $homeworkStudent->plan_id !== null ? (int) $homeworkStudent->plan_id : null;
        $existingPoints = $homeworkStudent->points()
            ->get()
            ->keyBy('plan_point_id');
        $submittedPointIds = collect($points)
            ->filter(static fn ($point): bool => is_array($point) && (int) ($point['plan_point_id'] ?? 0) > 0)
            ->pluck('plan_point_id')
            ->map(static fn ($value): int => (int) $value)
            ->unique()
            ->values()
            ->all();

        $planPoints = PlanPoint::query()
            ->whereIn('id', $submittedPointIds)
            ->when($planId !== null, fn ($query) => $query->where('plan_id', $planId))
            ->get()
            ->keyBy('id');

        $sortOrder = 1;
        foreach ($points as $pointPayload) {
            if (! is_array($pointPayload)) {
                continue;
            }

            $planPointId = (int) ($pointPayload['plan_point_id'] ?? 0);
            /** @var PlanPoint|null $planPoint */
            $planPoint = $planPoints->get($planPointId);
            if ($planPoint === null) {
                continue;
            }

            /** @var HomeworkStudentPoint|null $homeworkPoint */
            $homeworkPoint = $existingPoints->get($planPointId);
            $isDone = filter_var($pointPayload['is_done'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $isNextHomework = filter_var($pointPayload['is_next_homework'] ?? false, FILTER_VALIDATE_BOOLEAN) && ! $isDone;

            if ($homeworkPoint === null) {
                $homeworkPoint = HomeworkStudentPoint::query()->create([
                    'homework_student_id' => $homeworkStudent->id,
                    'homework_id' => $homework->id,
                    'student_id' => $student->id,
                    'plan_point_id' => $planPointId,
                    'sort_order' => $sortOrder,
                    'is_done' => false,
                    'is_next_homework' => $isNextHomework,
                    'awarded_points' => 0,
                ]);
            } else {
                $updates = [];
                if ($homeworkPoint->sort_order !== $sortOrder) {
                    $updates['sort_order'] = $sortOrder;
                }

                $nextHomeworkValue = $homeworkPoint->awarded_at === null ? $isNextHomework : false;
                if ((bool) $homeworkPoint->is_next_homework !== $nextHomeworkValue) {
                    $updates['is_next_homework'] = $nextHomeworkValue;
                }

                if ($updates !== []) {
                    $homeworkPoint->update($updates);
                    $homeworkPoint->refresh();
                }
            }

            if ($isDone) {
                $this->awardHomeworkPoint($homeworkPoint, $student, $planPoint);
            }

            $sortOrder++;
        }
    }

    private function applyManualPointsAdjustment(
        Homework $homework,
        HomeworkStudent $homeworkStudent,
        Student $student,
        int $newAdjustment,
    ): void {
        $oldAdjustment = (int) $homeworkStudent->points_adjustment;
        $delta = $newAdjustment - $oldAdjustment;

        if ($delta === 0) {
            return;
        }

        /** @var Student $lockedStudent */
        $lockedStudent = Student::query()
            ->whereKey($student->id)
            ->lockForUpdate()
            ->firstOrFail();

        $balanceBefore = (int) $lockedStudent->points_balance;
        $balanceAfter = $balanceBefore + $delta;

        $lockedStudent->update(['points_balance' => $balanceAfter]);
        $homeworkStudent->update(['points_adjustment' => $newAdjustment]);

        StudentPointTransaction::query()->create([
            'student_id' => $lockedStudent->id,
            'homework_id' => $homework->id,
            'homework_student_point_id' => null,
            'plan_point_id' => null,
            'type' => StudentPointTransaction::TYPE_HOMEWORK_MANUAL_ADJUSTMENT,
            'points' => $delta,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'created_by' => Auth::id(),
        ]);
    }

    private function awardHomeworkPoint(HomeworkStudentPoint $homeworkPoint, Student $student, PlanPoint $planPoint): void
    {
        if ($homeworkPoint->awarded_at !== null) {
            return;
        }

        $alreadyAwarded = StudentPointTransaction::query()
            ->where('student_id', $student->id)
            ->where('plan_point_id', $planPoint->id)
            ->where('type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
            ->exists();

        if ($alreadyAwarded) {
            $homeworkPoint->update([
                'is_done' => true,
                'awarded_points' => 0,
                'awarded_at' => now(),
            ]);

            return;
        }

        /** @var Student $lockedStudent */
        $lockedStudent = Student::query()
            ->whereKey($student->id)
            ->lockForUpdate()
            ->firstOrFail();

        $points = max(0, (int) ($planPoint->points ?? 0));
        $balanceBefore = (int) $lockedStudent->points_balance;
        $balanceAfter = $balanceBefore + $points;

        $studentUpdate = ['points_balance' => $balanceAfter];
        $currentPlanPoint = $lockedStudent->current_plan_point_id !== null
            ? PlanPoint::query()->find($lockedStudent->current_plan_point_id)
            : null;

        if (
            $currentPlanPoint === null
            || $currentPlanPoint->plan_id !== $planPoint->plan_id
            || $planPoint->sort_order > $currentPlanPoint->sort_order
            || ($planPoint->sort_order === $currentPlanPoint->sort_order && $planPoint->id > $currentPlanPoint->id)
        ) {
            $studentUpdate['current_plan_point_id'] = $planPoint->id;
        }

        $lockedStudent->update($studentUpdate);

        $homeworkPoint->update([
            'is_done' => true,
            'awarded_points' => $points,
            'awarded_at' => now(),
        ]);

        StudentPointTransaction::query()->create([
            'student_id' => $lockedStudent->id,
            'homework_id' => $homeworkPoint->homework_id,
            'homework_student_point_id' => $homeworkPoint->id,
            'plan_point_id' => $planPoint->id,
            'type' => StudentPointTransaction::TYPE_HOMEWORK_COMPLETED,
            'points' => $points,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'created_by' => Auth::id(),
        ]);
    }

    private function latestCompletedPlanPoint(Student $student, int $planId): ?PlanPoint
    {
        if ($student->current_plan_point_id !== null) {
            return PlanPoint::query()
                ->whereKey($student->current_plan_point_id)
                ->where('plan_id', $planId)
                ->first();
        }

        return PlanPoint::query()
            ->join('student_point_transactions', 'plan_points.id', '=', 'student_point_transactions.plan_point_id')
            ->where('student_point_transactions.student_id', $student->id)
            ->where('student_point_transactions.type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
            ->where('plan_points.plan_id', $planId)
            ->orderByDesc('plan_points.sort_order')
            ->orderByDesc('plan_points.id')
            ->select('plan_points.*')
            ->first();
    }

    /**
     * @return EloquentCollection<int, PlanPoint>
     */
    private function nextPlanPoints(Student $student, int $planId, ?PlanPoint $currentPlanPoint): EloquentCollection
    {
        $completedIds = $student->current_plan_point_id !== null
            ? []
            : StudentPointTransaction::query()
                ->where('student_id', $student->id)
                ->where('type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED)
                ->pluck('plan_point_id')
                ->filter(static fn ($value): bool => $value !== null)
                ->map(static fn ($value): int => (int) $value)
                ->all();

        return PlanPoint::query()
            ->where('plan_id', $planId)
            ->when($currentPlanPoint !== null, function ($query) use ($currentPlanPoint): void {
                $query->where(function ($inner) use ($currentPlanPoint): void {
                    $inner->where('sort_order', '>', $currentPlanPoint->sort_order)
                        ->orWhere(function ($nested) use ($currentPlanPoint): void {
                            $nested->where('sort_order', $currentPlanPoint->sort_order)
                                ->where('id', '>', $currentPlanPoint->id);
                        });
                });
            })
            ->when($completedIds !== [], fn ($query) => $query->whereNotIn('id', $completedIds))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(self::POINT_WINDOW_SIZE)
            ->get();
    }

    /**
     * @param  EloquentCollection<int, PlanPoint>  $points
     * @param  array<int, array<string, mixed>>  $previousNextHomeworkAssignments
     */
    private function includePreviousNextHomeworkPoints(
        EloquentCollection $points,
        int $planId,
        array $previousNextHomeworkAssignments,
    ) {
        $loadedIds = $points
            ->pluck('id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
        $missingIds = array_values(array_diff(array_keys($previousNextHomeworkAssignments), $loadedIds));

        if ($missingIds === []) {
            return $points;
        }

        $previousPoints = PlanPoint::query()
            ->where('plan_id', $planId)
            ->whereIn('id', $missingIds)
            ->get();

        return $points
            ->merge($previousPoints)
            ->sortBy(static fn (PlanPoint $point): string => sprintf(
                '%010d-%010d',
                (int) $point->sort_order,
                (int) $point->id,
            ))
            ->values();
    }

    /**
     * @return array<int, array{homework_id: int, date: string, date_formatted: string}>
     */
    private function previousNextHomeworkAssignments(Student $student, int $planId, string $beforeDate, int $groupId): array
    {
        return HomeworkStudentPoint::query()
            ->join('homeworks', 'homework_student_points.homework_id', '=', 'homeworks.id')
            ->join('plan_points', 'homework_student_points.plan_point_id', '=', 'plan_points.id')
            ->where('homework_student_points.student_id', $student->id)
            ->where('homework_student_points.is_next_homework', true)
            ->where('plan_points.plan_id', $planId)
            ->where('homeworks.group_id', $groupId)
            ->whereDate('homeworks.date', '<', $beforeDate)
            ->whereNotExists(function ($query): void {
                $query
                    ->select(DB::raw(1))
                    ->from('student_point_transactions')
                    ->whereColumn('student_point_transactions.student_id', 'homework_student_points.student_id')
                    ->whereColumn('student_point_transactions.plan_point_id', 'homework_student_points.plan_point_id')
                    ->where('student_point_transactions.type', StudentPointTransaction::TYPE_HOMEWORK_COMPLETED);
            })
            ->orderByDesc('homeworks.date')
            ->orderByDesc('homeworks.id')
            ->orderBy('homework_student_points.sort_order')
            ->get([
                'homework_student_points.plan_point_id',
                'homework_student_points.homework_id',
                'homeworks.date as homework_date',
            ])
            ->unique('plan_point_id')
            ->mapWithKeys(function ($row): array {
                $date = Carbon::parse((string) $row->homework_date);

                return [
                    (int) $row->plan_point_id => [
                        'homework_id' => (int) $row->homework_id,
                        'date' => $date->toDateString(),
                        'date_formatted' => $date->locale(app()->getLocale())->translatedFormat('l ، j F ، Y'),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pointPayload(PlanPoint $point, ?array $previousNextHomeworkAssignment = null): array
    {
        return [
            'plan_point_id' => (int) $point->id,
            'name' => (string) $point->name,
            'points' => (int) ($point->points ?? 0),
            'is_done' => false,
            'is_next_homework' => false,
            'is_previous_next_homework' => $previousNextHomeworkAssignment !== null,
            'previous_next_homework_date' => $previousNextHomeworkAssignment['date'] ?? null,
            'previous_next_homework_date_formatted' => $previousNextHomeworkAssignment['date_formatted'] ?? null,
            'is_locked' => false,
        ];
    }

    private function resolveDate(?string $date): string
    {
        if ($date === null || trim($date) === '') {
            return now()->toDateString();
        }

        return Carbon::parse($date)->toDateString();
    }

    private function resolveWorkingDate(Group $group, string $date): string
    {
        if ($this->isWorkingDate($group, $date)) {
            return $date;
        }

        $workingDays = $this->workingDayLookup($group);
        if ($workingDays === []) {
            return $date;
        }

        $cursor = Carbon::parse($date);
        for ($index = 0; $index < 14; $index++) {
            if (isset($workingDays[$this->dayName($cursor)])) {
                return $cursor->toDateString();
            }

            $cursor = $cursor->addDay();
        }

        return $date;
    }

    private function isWorkingDate(Group $group, string $date): bool
    {
        return GroupWorkingDays::includes($group->working_days, $date);
    }

    /**
     * @return array<string, true>
     */
    private function workingDayLookup(Group $group): array
    {
        return GroupWorkingDays::lookup($group->working_days);
    }

    private function dayName(Carbon $date): string
    {
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        return $dayNames[$date->dayOfWeek] ?? '';
    }
}

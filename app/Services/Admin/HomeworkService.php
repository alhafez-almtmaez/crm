<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Homework;
use App\Models\HomeworkStudent;
use App\Models\HomeworkStudentPoint;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Services\System\DateTimeFormatterService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeworkService
{
    private const POINT_WINDOW_SIZE = 20;

    public function __construct(private readonly DateTimeFormatterService $dateTimeFormatter) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $sortMap = [
            'id' => 'homeworks.id',
            'date' => 'homeworks.date',
            'center_name' => 'centers.name',
            'admin_name' => 'admins.name',
            'created_at' => 'homeworks.created_at',
        ];

        $sortColumn = $sortMap[$sortBy] ?? 'homeworks.id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $rows = Homework::query()
            ->leftJoin('centers', 'homeworks.center_id', '=', 'centers.id')
            ->leftJoin('users as admins', 'homeworks.admin_id', '=', 'admins.id')
            ->leftJoin('homework_students', 'homeworks.id', '=', 'homework_students.homework_id')
            ->leftJoin('homework_student_points', 'homeworks.id', '=', 'homework_student_points.homework_id')
            ->select([
                'homeworks.id',
                'homeworks.date',
                'homeworks.center_id',
                'homeworks.admin_id',
                'homeworks.created_at',
                'centers.name as center_name',
                'admins.name as admin_name',
                DB::raw('COUNT(DISTINCT homework_students.id) as students_count'),
                DB::raw('COALESCE(SUM(CASE WHEN homework_student_points.is_done = true THEN 1 ELSE 0 END), 0) as completed_points_count'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('homeworks.id', 'like', "%{$search}%")
                        ->orWhere('homeworks.date', 'like', "%{$search}%")
                        ->orWhere('centers.name', 'like', "%{$search}%")
                        ->orWhere('admins.name', 'like', "%{$search}%");
                });
            })
            ->groupBy([
                'homeworks.id',
                'homeworks.date',
                'homeworks.center_id',
                'homeworks.admin_id',
                'homeworks.created_at',
                'centers.name',
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
                    Carbon::parse((string) $row->date)->locale(app()->getLocale())->translatedFormat('l ، j F ، Y'),
                );

                return $row;
            }),
        );

        return $rows;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function centerOptions(): array
    {
        return Center::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Center $center): array => [
                'id' => $center->id,
                'name' => $center->name,
            ])
            ->all();
    }

    /**
     * @return array{selected_center_id: ?int, selected_date: string, students: array<int, array<string, mixed>>, existing_homework_id: ?int}
     */
    public function createFormPayload(?int $centerId, ?string $date): array
    {
        $resolvedDate = $this->resolveDate($date);
        $resolvedCenterId = $centerId !== null && $centerId > 0 ? $centerId : null;

        if ($resolvedCenterId === null) {
            return [
                'selected_center_id' => null,
                'selected_date' => $resolvedDate,
                'students' => [],
                'existing_homework_id' => null,
            ];
        }

        $existingHomeworkId = Homework::query()
            ->where('center_id', $resolvedCenterId)
            ->whereDate('date', $resolvedDate)
            ->value('id');

        return [
            'selected_center_id' => $resolvedCenterId,
            'selected_date' => $resolvedDate,
            'students' => $existingHomeworkId === null ? $this->studentRowsForCreate($resolvedCenterId, $resolvedDate) : [],
            'existing_homework_id' => $existingHomeworkId !== null ? (int) $existingHomeworkId : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function editStudentRows(Homework $homework): array
    {
        $homework->load([
            'students.student.center',
            'students.student.group',
            'students.student.plan',
            'students.currentPlanPoint',
            'students.points.planPoint',
        ]);

        return $homework->students
            ->sortBy(static fn (HomeworkStudent $row): string => (string) ($row->student?->full_name ?? ''))
            ->values()
            ->map(fn (HomeworkStudent $row): array => $this->studentRowDataFromHomework($row))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Homework
    {
        $centerId = (int) $data['center_id'];
        $date = $this->resolveDate((string) $data['date']);

        $exists = Homework::query()
            ->where('center_id', $centerId)
            ->whereDate('date', $date)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'date' => __('homeworks.already_exists_for_center_date'),
            ]);
        }

        return DB::transaction(function () use ($centerId, $date, $data): Homework {
            $homework = Homework::query()->create([
                'date' => $date,
                'center_id' => $centerId,
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
        return DB::transaction(function () use ($homework, $data): Homework {
            $this->syncHomeworkRows($homework, $data['items'] ?? []);

            return $homework->refresh();
        });
    }

    public function delete(Homework $homework): void
    {
        $homework->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pointHistory(Student $student): array
    {
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
    private function studentRowsForCreate(int $centerId, string $date): array
    {
        $students = Student::query()
            ->with(['plan:id,name', 'group:id,name'])
            ->where('center_id', $centerId)
            ->where('is_active', Student::STATUS_ACTIVE)
            ->orderBy('plan_type_id')
            ->orderBy('full_name')
            ->get([
                'id',
                'full_name',
                'group_id',
                'plan_type_id',
                'current_plan_point_id',
                'points_balance',
            ]);

        return $students
            ->map(fn (Student $student): array => $this->studentRowDataForCreate($student, $date))
            ->all();
    }

    private function studentRowDataForCreate(Student $student, string $date): array
    {
        $planId = $student->plan_type_id !== null ? (int) $student->plan_type_id : null;
        $currentPlanPoint = $planId !== null ? $this->latestCompletedPlanPoint($student, $planId) : null;
        $previousNextHomeworkAssignments = $planId !== null
            ? $this->previousNextHomeworkAssignments($student, $planId, $date)
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
            'group_name' => $student->group?->name,
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

    private function studentRowDataFromHomework(HomeworkStudent $row): array
    {
        $student = $row->student;

        return [
            'student_id' => (int) $row->student_id,
            'full_name' => (string) ($student?->full_name ?? ''),
            'plan_id' => $row->plan_id !== null ? (int) $row->plan_id : null,
            'plan_name' => $row->plan?->name ?? $student?->plan?->name,
            'group_name' => $student?->group?->name,
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

        if ($balanceAfter < 0) {
            throw ValidationException::withMessages([
                'items' => __('homeworks.points_adjustment_insufficient_balance'),
            ]);
        }

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
    private function previousNextHomeworkAssignments(Student $student, int $planId, string $beforeDate): array
    {
        return HomeworkStudentPoint::query()
            ->join('homeworks', 'homework_student_points.homework_id', '=', 'homeworks.id')
            ->join('plan_points', 'homework_student_points.plan_point_id', '=', 'plan_points.id')
            ->where('homework_student_points.student_id', $student->id)
            ->where('homework_student_points.is_next_homework', true)
            ->where('plan_points.plan_id', $planId)
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
}

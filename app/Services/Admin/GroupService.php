<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Group;
use App\Models\HomeworkStudentPoint;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Services\System\DateTimeFormatterService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

class GroupService
{
    private const TASK_WINDOW_SIZE = 10;

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
        $allowedSorts = ['id', 'name', 'center_name', 'created_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';
        $sortColumn = $sortBy === 'center_name' ? 'centers.name' : "groups.{$sortBy}";

        $query = Group::query()
            ->leftJoin('centers', 'groups.center_id', '=', 'centers.id')
            ->select(['groups.id', 'groups.ulid', 'groups.name', 'groups.center_id', 'groups.created_at', 'centers.name as center_name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('groups.name', 'like', "%{$search}%")
                        ->orWhere('centers.name', 'like', "%{$search}%");
                });
            })
            ->orderBy($sortColumn, $sortDir);

        $groups = $query->paginate($perPage)->withQueryString();
        $groups->setCollection(
            $groups->getCollection()->map(function ($group) {
                $group->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($group->created_at));
                $group->setAttribute(
                    'homework_report_url',
                    filled($group->ulid) ? route('groups.homeworks.report', ['publicId' => $group->ulid], false) : null,
                );

                return $group;
            }),
        );

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Group
    {
        return Group::query()->create([
            'ulid' => (string) Str::ulid(),
            'name' => $data['name'],
            'center_id' => (int) $data['center_id'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Group $group, array $data): Group
    {
        $group->update([
            'name' => $data['name'],
            'center_id' => (int) $data['center_id'],
        ]);

        return $group->refresh();
    }

    public function delete(Group $group): void
    {
        $group->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function homeworkReportPayload(string $publicId): array
    {
        $publicId = trim($publicId);
        abort_if($publicId === '', 404);

        /** @var Group $group */
        $group = Group::query()
            ->with('center:id,name')
            ->where('ulid', $publicId)
            ->firstOrFail();

        $rows = Student::query()
            ->with(['plan:id,name'])
            ->where('group_id', $group->id)
            ->where('is_active', Student::STATUS_ACTIVE)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'plan_type_id', 'current_plan_point_id', 'points_balance'])
            ->values()
            ->map(fn (Student $student, int $index): array => $this->homeworkReportStudentRow($student, $index))
            ->all();

        return [
            'title' => 'واجبات المرة القادمة',
            'group_name' => $group->name,
            'center_name' => $group->center?->name ?? '-',
            'generated_at' => Carbon::now()->locale('ar')->translatedFormat('l ، j F ، Y'),
            'rows' => $rows,
            'summary' => [
                'students_count' => count($rows),
                'tasks_count' => collect($rows)->sum(static fn (array $row): int => count($row['tasks'] ?? [])),
            ],
        ];
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

    private function homeworkReportStudentRow(Student $student, int $index): array
    {
        $planId = $student->plan_type_id !== null ? (int) $student->plan_type_id : null;
        $currentPlanPoint = $planId !== null ? $this->latestCompletedPlanPoint($student, $planId) : null;
        $tasks = $planId !== null ? $this->latestAssignedNextHomeworkPoints($student, $planId) : collect();

        return [
            'number' => $index + 1,
            'student_id' => $student->id,
            'full_name' => $student->full_name,
            'plan_name' => $student->plan?->name ?? '-',
            'points_balance' => (int) ($student->points_balance ?? 0),
            'current_plan_point_name' => $currentPlanPoint?->name,
            'tasks' => $tasks
                ->values()
                ->map(static fn (PlanPoint $point): array => [
                    'id' => $point->id,
                    'name' => $point->name,
                    'surah_name' => $point->surah_name,
                    'part_name' => $point->part_name,
                    'three_parts' => $point->three_parts,
                ])
                ->all(),
        ];
    }

    /**
     * @return EloquentCollection<int, PlanPoint>
     */
    private function latestAssignedNextHomeworkPoints(Student $student, int $planId): EloquentCollection
    {
        $latestHomeworkId = HomeworkStudentPoint::query()
            ->join('plan_points', 'homework_student_points.plan_point_id', '=', 'plan_points.id')
            ->join('homeworks', 'homework_student_points.homework_id', '=', 'homeworks.id')
            ->where('homework_student_points.student_id', $student->id)
            ->where('homework_student_points.is_next_homework', true)
            ->where('plan_points.plan_id', $planId)
            ->orderByDesc('homeworks.date')
            ->orderByDesc('homeworks.id')
            ->value('homework_student_points.homework_id');

        if ($latestHomeworkId === null) {
            return new EloquentCollection;
        }

        return PlanPoint::query()
            ->join('homework_student_points', 'plan_points.id', '=', 'homework_student_points.plan_point_id')
            ->where('homework_student_points.homework_id', $latestHomeworkId)
            ->where('homework_student_points.student_id', $student->id)
            ->where('homework_student_points.is_next_homework', true)
            ->where('plan_points.plan_id', $planId)
            ->orderBy('homework_student_points.sort_order')
            ->orderBy('plan_points.sort_order')
            ->orderBy('plan_points.id')
            ->select('plan_points.*')
            ->get();
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
            ->limit(self::TASK_WINDOW_SIZE)
            ->get();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function listByCenter(Center $center): array
    {
        return Group::query()
            ->where('center_id', $center->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Group $group): array => [
                'id' => $group->id,
                'name' => $group->name,
            ])
            ->all();
    }
}

<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Group;
use App\Models\MonthlyPlan;
use App\Models\StudentMonthlyPlan;
use App\Services\System\DateTimeFormatterService;
use App\Support\DailyWeightLimits;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentMonthlyPlanService
{
    public function __construct(
        private readonly DateTimeFormatterService $dateTimeFormatter,
        private readonly AdminDataScopeService $dataScope,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function savedPlansPage(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);
        $sortBy = (string) ($filters['sort_by'] ?? 'id');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');
        $sortMap = [
            'id' => 'monthly_plans.id',
            'center_name' => 'centers.name',
            'group_name' => 'groups.name',
            'month' => 'monthly_plans.month',
            'year' => 'monthly_plans.year',
            'students_count' => 'monthly_plans.students_count',
            'generated_items_count' => 'monthly_plans.generated_items_count',
            'generated_at' => 'monthly_plans.generated_at',
        ];

        $sortColumn = $sortMap[$sortBy] ?? 'monthly_plans.id';
        $sortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $rows = MonthlyPlan::query()
            ->leftJoin('centers', 'monthly_plans.center_id', '=', 'centers.id')
            ->leftJoin('groups', 'monthly_plans.group_id', '=', 'groups.id')
            ->select([
                'monthly_plans.id',
                'monthly_plans.ulid',
                'monthly_plans.center_id',
                'monthly_plans.group_id',
                'monthly_plans.month',
                'monthly_plans.year',
                'monthly_plans.students_count',
                'monthly_plans.generated_items_count',
                'monthly_plans.skipped_items_count',
                'monthly_plans.generated_at',
                'monthly_plans.created_at',
                'centers.name as center_name',
                'groups.name as group_name',
            ])
            ->tap(fn ($query) => $this->dataScope->applyMonthlyPlanAccess($query, 'monthly_plans'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('centers.name', 'like', "%{$search}%")
                        ->orWhere('groups.name', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $builder
                            ->orWhere('monthly_plans.id', (int) $search)
                            ->orWhere('monthly_plans.month', (int) $search)
                            ->orWhere('monthly_plans.year', (int) $search);
                    }
                });
            })
            ->orderBy($sortColumn, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $rows->setCollection(
            $rows->getCollection()->map(function (MonthlyPlan $row): MonthlyPlan {
                if ($this->dataScope->shouldScope()) {
                    $summary = $this->monthlyPlanSummary((int) $row->id, scoped: true);
                    $row->setAttribute('students_count', $summary['students_count']);
                    $row->setAttribute('generated_items_count', $summary['generated_items_count']);
                    $row->setAttribute('skipped_items_count', $summary['skipped_items_count']);
                    $row->setAttribute('generated_at', $summary['generated_at']);
                }

                $row->setAttribute('center_name', $row->center_name ?? '');
                $row->setAttribute('group_name', $row->group_name ?? '');
                $row->setAttribute(
                    'public_report_url',
                    filled($row->ulid) ? route('monthly-plans.report', ['publicId' => $row->ulid], false) : null,
                );
                $row->setAttribute('generated_at_formatted', $this->dateTimeFormatter->formatForAdmin($row->generated_at));
                $row->setAttribute('created_at_formatted', $this->dateTimeFormatter->formatForAdmin($row->created_at));

                return $row;
            }),
        );

        return $rows;
    }

    /**
     * @return array<int, array{id: int, name: string, working_days: array<int, string>}>
     */
    public function centerOptions(): array
    {
        return Center::query()
            ->tap(fn ($query) => $this->dataScope->applyCenterAccess($query, 'centers'))
            ->orderBy('name')
            ->get(['id', 'name', 'working_days'])
            ->map(static fn (Center $center): array => [
                'id' => (int) $center->id,
                'name' => (string) $center->name,
                'working_days' => is_array($center->working_days) ? $center->working_days : [],
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, center_id: int}>
     */
    public function groupOptions(): array
    {
        return Group::query()
            ->tap(fn ($query) => $this->dataScope->applyGroupAccess($query, 'groups'))
            ->orderBy('center_id')
            ->orderBy('name')
            ->get(['id', 'name', 'center_id'])
            ->map(static fn (Group $group): array => [
                'id' => (int) $group->id,
                'name' => (string) $group->name,
                'center_id' => (int) $group->center_id,
            ])
            ->all();
    }

    public function hasSavedPlan(Group $group, int $month, int $year): bool
    {
        return $this->savedPlanForGroup($group, $month, $year) !== null;
    }

    public function savedPlanForGroup(Group $group, int $month, int $year): ?MonthlyPlan
    {
        return MonthlyPlan::query()
            ->where('group_id', $group->id)
            ->where('month', $month)
            ->where('year', $year)
            ->whereHas('studentPlans.items')
            ->tap(fn ($query) => $this->dataScope->applyMonthlyPlanAccess($query, 'monthly_plans'))
            ->first();
    }

    public function delete(MonthlyPlan $monthlyPlan): void
    {
        $this->dataScope->abortUnlessCanAccessMonthlyPlan($monthlyPlan);

        if ($this->dataScope->shouldScope()) {
            $studentPlanIds = StudentMonthlyPlan::query()
                ->join('students', 'student_monthly_plans.student_id', '=', 'students.id')
                ->where('student_monthly_plans.monthly_plan_id', $monthlyPlan->id)
                ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
                ->pluck('student_monthly_plans.id')
                ->all();

            if ($studentPlanIds !== []) {
                StudentMonthlyPlan::query()
                    ->whereIn('id', $studentPlanIds)
                    ->delete();
            }

            if (! StudentMonthlyPlan::query()->where('monthly_plan_id', $monthlyPlan->id)->exists()) {
                $monthlyPlan->delete();

                return;
            }

            $this->refreshStoredMonthlyPlanTotals($monthlyPlan);

            return;
        }

        $monthlyPlan->delete();
    }

    /**
     * @return array{monthly_plan: array<string, mixed>, dates: array<int, array<string, mixed>>, plans: array<int, array<string, mixed>>}
     */
    public function savedPlanPayload(MonthlyPlan $monthlyPlan): array
    {
        $this->dataScope->abortUnlessCanAccessMonthlyPlan($monthlyPlan);

        $monthlyPlan->loadMissing(['center:id,name,working_days', 'group:id,name']);

        $studentPlanModels = StudentMonthlyPlan::query()
            ->with([
                'student:id,full_name,max_daily_weight',
                'center:id,name',
                'group:id,name',
                'plan:id,name',
                'startsAfterPlanPoint:id,name',
                'endsAtPlanPoint:id,name',
                'items' => fn ($query) => $query->where('status', 'skipped')->orderBy('sort_order'),
                'items.planPoint:id,name,sort_order',
                'days' => fn ($query) => $query->orderBy('date'),
                'days.items' => fn ($query) => $query->orderBy('sort_order'),
                'days.items.planPoint:id,name,sort_order',
            ])
            ->join('students', 'student_monthly_plans.student_id', '=', 'students.id')
            ->where('monthly_plan_id', $monthlyPlan->id)
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->orderBy('student_monthly_plans.student_id')
            ->select('student_monthly_plans.*')
            ->get();

        $studentPlans = $studentPlanModels
            ->map(fn (StudentMonthlyPlan $plan): array => $this->studentPlanPayload($plan))
            ->all();
        $generatedItemsCount = $studentPlanModels->sum(static fn (StudentMonthlyPlan $plan): int => (int) $plan->generated_items_count);
        $skippedItemsCount = $studentPlanModels->sum(static fn (StudentMonthlyPlan $plan): int => (int) $plan->skipped_items_count);

        return [
            'monthly_plan' => [
                'id' => (int) $monthlyPlan->id,
                'center_id' => (int) $monthlyPlan->center_id,
                'center_name' => (string) ($monthlyPlan->center?->name ?? ''),
                'group_id' => (int) $monthlyPlan->group_id,
                'group_name' => (string) ($monthlyPlan->group?->name ?? ''),
                'month' => (int) $monthlyPlan->month,
                'year' => (int) $monthlyPlan->year,
                'students_count' => $studentPlanModels->count(),
                'generated_items_count' => $generatedItemsCount,
                'skipped_items_count' => $skippedItemsCount,
                'generated_at' => $this->dateTimeFormatter->formatForAdmin($monthlyPlan->generated_at),
            ],
            'dates' => $monthlyPlan->center !== null
                ? $this->workingDatesForMonth($monthlyPlan->center, (int) $monthlyPlan->month, (int) $monthlyPlan->year)
                : [],
            'plans' => $studentPlans,
        ];
    }

    /**
     * @return array{monthly_plan: array<string, mixed>, dates: array<int, array<string, mixed>>, plans: array<int, array<string, mixed>>}
     */
    public function publicReportPayload(string $publicId): array
    {
        $publicId = trim($publicId);
        abort_if($publicId === '', 404);

        /** @var MonthlyPlan $monthlyPlan */
        $monthlyPlan = MonthlyPlan::query()
            ->where('ulid', $publicId)
            ->firstOrFail();

        $monthlyPlan->loadMissing(['center:id,name,working_days', 'group:id,name']);

        $studentPlanModels = StudentMonthlyPlan::query()
            ->with([
                'student:id,full_name',
                'plan:id,name',
                'days' => fn ($query) => $query->orderBy('date'),
                'days.items' => fn ($query) => $query->orderBy('sort_order'),
                'days.items.planPoint:id,name,sort_order',
            ])
            ->join('students', 'student_monthly_plans.student_id', '=', 'students.id')
            ->where('monthly_plan_id', $monthlyPlan->id)
            ->orderBy('students.full_name')
            ->select('student_monthly_plans.*')
            ->get();

        return [
            'monthly_plan' => [
                'id' => (int) $monthlyPlan->id,
                'center_name' => (string) ($monthlyPlan->center?->name ?? ''),
                'group_name' => (string) ($monthlyPlan->group?->name ?? ''),
                'month' => (int) $monthlyPlan->month,
                'year' => (int) $monthlyPlan->year,
                'students_count' => $studentPlanModels->count(),
                'generated_items_count' => $studentPlanModels->sum(static fn (StudentMonthlyPlan $plan): int => (int) $plan->generated_items_count),
                'generated_at' => $this->dateTimeFormatter->formatForAdmin($monthlyPlan->generated_at),
            ],
            'dates' => $monthlyPlan->center !== null
                ? $this->workingDatesForMonth($monthlyPlan->center, (int) $monthlyPlan->month, (int) $monthlyPlan->year)
                : [],
            'plans' => $studentPlanModels
                ->map(fn (StudentMonthlyPlan $plan): array => $this->publicStudentPlanPayload($plan))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function studentPlanPayload(StudentMonthlyPlan $plan): array
    {
        return [
            'id' => (int) $plan->id,
            'student_id' => (int) $plan->student_id,
            'student_name' => (string) ($plan->student?->full_name ?? ''),
            'center_name' => (string) ($plan->center?->name ?? ''),
            'group_name' => (string) ($plan->group?->name ?? ''),
            'plan_name' => (string) ($plan->plan?->name ?? ''),
            'month' => (int) $plan->month,
            'year' => (int) $plan->year,
            'max_daily_weight' => (int) $plan->max_daily_weight,
            'daily_weight_limits' => DailyWeightLimits::normalize($plan->daily_weight_limits, $plan->max_daily_weight),
            'starts_after_plan_point_name' => $plan->startsAfterPlanPoint?->name,
            'ends_at_plan_point_name' => $plan->endsAtPlanPoint?->name,
            'generated_items_count' => (int) $plan->generated_items_count,
            'skipped_items_count' => (int) $plan->skipped_items_count,
            'status' => (string) $plan->status,
            'skipped_items' => $plan->items->map(static fn ($item): array => [
                'id' => (int) $item->id,
                'plan_point_id' => (int) $item->plan_point_id,
                'name' => (string) ($item->planPoint?->name ?? ''),
                'weight' => (float) $item->weight,
                'is_standalone' => (bool) $item->is_standalone,
                'status' => (string) $item->status,
            ])->all(),
            'days' => $plan->days->map(fn ($day): array => [
                'id' => (int) $day->id,
                'date' => $day->date?->format('Y-m-d'),
                'day_number' => (int) $day->day_number,
                'total_weight' => (float) $day->total_weight,
                'daily_weight_limit' => $day->date !== null
                    ? DailyWeightLimits::limitForDate($plan->daily_weight_limits, $day->date, $plan->max_daily_weight)
                    : (int) $plan->max_daily_weight,
                'items' => $day->items->map(static fn ($item): array => [
                    'id' => (int) $item->id,
                    'plan_point_id' => (int) $item->plan_point_id,
                    'name' => (string) ($item->planPoint?->name ?? ''),
                    'weight' => (float) $item->weight,
                    'is_standalone' => (bool) $item->is_standalone,
                    'status' => (string) $item->status,
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicStudentPlanPayload(StudentMonthlyPlan $plan): array
    {
        return [
            'id' => (int) $plan->id,
            'student_id' => (int) $plan->student_id,
            'student_name' => (string) ($plan->student?->full_name ?? ''),
            'plan_name' => (string) ($plan->plan?->name ?? ''),
            'generated_items_count' => (int) $plan->generated_items_count,
            'days' => $plan->days->map(fn ($day): array => [
                'id' => (int) $day->id,
                'date' => $day->date?->format('Y-m-d'),
                'day_number' => (int) $day->day_number,
                'daily_weight_limit' => $day->date !== null
                    ? DailyWeightLimits::limitForDate($plan->daily_weight_limits, $day->date, $plan->max_daily_weight)
                    : (int) $plan->max_daily_weight,
                'items' => $day->items->map(static fn ($item): array => [
                    'id' => (int) $item->id,
                    'plan_point_id' => (int) $item->plan_point_id,
                    'name' => (string) ($item->planPoint?->name ?? ''),
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * @return array{students_count: int, generated_items_count: int, skipped_items_count: int, generated_at: mixed}
     */
    private function monthlyPlanSummary(int $monthlyPlanId, bool $scoped): array
    {
        $query = StudentMonthlyPlan::query()
            ->where('student_monthly_plans.monthly_plan_id', $monthlyPlanId)
            ->selectRaw('COUNT(*) as students_count')
            ->selectRaw('COALESCE(SUM(student_monthly_plans.generated_items_count), 0) as generated_items_count')
            ->selectRaw('COALESCE(SUM(student_monthly_plans.skipped_items_count), 0) as skipped_items_count')
            ->selectRaw('MAX(student_monthly_plans.generated_at) as generated_at');

        if ($scoped) {
            $query
                ->join('students', 'student_monthly_plans.student_id', '=', 'students.id')
                ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'));
        }

        $summary = $query->first();

        return [
            'students_count' => (int) ($summary->students_count ?? 0),
            'generated_items_count' => (int) ($summary->generated_items_count ?? 0),
            'skipped_items_count' => (int) ($summary->skipped_items_count ?? 0),
            'generated_at' => $summary->generated_at ?? null,
        ];
    }

    private function refreshStoredMonthlyPlanTotals(MonthlyPlan $monthlyPlan): void
    {
        $summary = $this->monthlyPlanSummary((int) $monthlyPlan->id, scoped: false);

        $monthlyPlan->update([
            'students_count' => $summary['students_count'],
            'generated_items_count' => $summary['generated_items_count'],
            'skipped_items_count' => $summary['skipped_items_count'],
            'generated_at' => $summary['generated_at'] ?? $monthlyPlan->generated_at,
        ]);
    }

    /**
     * @return array<int, array{date: string, day_number: int, day_name: string, day_label: string}>
     */
    private function workingDatesForMonth(Center $center, int $month, int $year): array
    {
        $workingDays = is_array($center->working_days) ? $center->working_days : [];
        if ($workingDays === []) {
            $workingDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        }

        $workingDayLookup = array_fill_keys(array_map(static fn (string $day): string => strtolower($day), $workingDays), true);
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();
        $dates = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dayName = $dayNames[$date->dayOfWeek];
            if (! isset($workingDayLookup[$dayName])) {
                continue;
            }

            $dates[] = [
                'date' => $date->toDateString(),
                'day_number' => $date->day,
                'day_name' => $dayName,
                'day_label' => __('days.'.$dayName),
            ];
        }

        return $dates;
    }
}

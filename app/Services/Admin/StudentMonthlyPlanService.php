<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Group;
use App\Models\MonthlyPlan;
use App\Models\StudentMonthlyPlan;
use App\Services\System\DateTimeFormatterService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StudentMonthlyPlanService
{
    public function __construct(private readonly DateTimeFormatterService $dateTimeFormatter) {}

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
                $row->setAttribute('center_name', $row->center_name ?? '');
                $row->setAttribute('group_name', $row->group_name ?? '');
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
            ->first();
    }

    public function delete(MonthlyPlan $monthlyPlan): void
    {
        $monthlyPlan->delete();
    }

    /**
     * @return array{monthly_plan: array<string, mixed>, dates: array<int, array<string, mixed>>, plans: array<int, array<string, mixed>>}
     */
    public function savedPlanPayload(MonthlyPlan $monthlyPlan): array
    {
        $monthlyPlan->loadMissing(['center:id,name,working_days', 'group:id,name']);

        $studentPlans = StudentMonthlyPlan::query()
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
            ->where('monthly_plan_id', $monthlyPlan->id)
            ->orderBy('student_id')
            ->get()
            ->map(fn (StudentMonthlyPlan $plan): array => $this->studentPlanPayload($plan))
            ->all();

        return [
            'monthly_plan' => [
                'id' => (int) $monthlyPlan->id,
                'center_id' => (int) $monthlyPlan->center_id,
                'center_name' => (string) ($monthlyPlan->center?->name ?? ''),
                'group_id' => (int) $monthlyPlan->group_id,
                'group_name' => (string) ($monthlyPlan->group?->name ?? ''),
                'month' => (int) $monthlyPlan->month,
                'year' => (int) $monthlyPlan->year,
                'students_count' => (int) $monthlyPlan->students_count,
                'generated_items_count' => (int) $monthlyPlan->generated_items_count,
                'skipped_items_count' => (int) $monthlyPlan->skipped_items_count,
                'generated_at' => $this->dateTimeFormatter->formatForAdmin($monthlyPlan->generated_at),
            ],
            'dates' => $monthlyPlan->center !== null
                ? $this->workingDatesForMonth($monthlyPlan->center, (int) $monthlyPlan->month, (int) $monthlyPlan->year)
                : [],
            'plans' => $studentPlans,
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
            'days' => $plan->days->map(static fn ($day): array => [
                'id' => (int) $day->id,
                'date' => $day->date?->format('Y-m-d'),
                'day_number' => (int) $day->day_number,
                'total_weight' => (float) $day->total_weight,
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
     * @return array<int, array{date: string, day_number: int, day_label: string}>
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
                'day_label' => __('days.'.$dayName),
            ];
        }

        return $dates;
    }
}

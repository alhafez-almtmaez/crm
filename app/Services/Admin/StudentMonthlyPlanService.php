<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Group;
use App\Models\StudentMonthlyPlan;
use Carbon\CarbonImmutable;

class StudentMonthlyPlanService
{
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

    /**
     * @param  array<string, mixed>  $filters
     * @return array{center: ?array<string, mixed>, dates: array<int, array<string, mixed>>, plans: array<int, array<string, mixed>>}
     */
    public function list(array $filters): array
    {
        $month = (int) $filters['month'];
        $year = (int) $filters['year'];
        $centerId = isset($filters['center_id']) ? (int) $filters['center_id'] : null;
        $groupId = isset($filters['group_id']) ? (int) $filters['group_id'] : null;
        $center = $centerId !== null ? Center::query()->find($centerId) : null;

        if ($center === null) {
            return [
                'center' => null,
                'dates' => [],
                'plans' => [],
            ];
        }

        $plans = StudentMonthlyPlan::query()
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
            ->where('month', $month)
            ->where('year', $year)
            ->where('center_id', $centerId)
            ->when($groupId !== null, fn ($query) => $query->where('group_id', $groupId))
            ->orderBy('group_id')
            ->orderBy('student_id')
            ->get()
            ->map(static fn (StudentMonthlyPlan $plan): array => [
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
            ])
            ->all();

        return [
            'center' => [
                'id' => (int) $center->id,
                'name' => (string) $center->name,
            ],
            'dates' => $this->workingDatesForMonth($center, $month, $year),
            'plans' => $plans,
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

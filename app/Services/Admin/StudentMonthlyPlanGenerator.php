<?php

namespace App\Services\Admin;

use App\Models\Center;
use App\Models\Group;
use App\Models\MonthlyPlan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentMonthlyPlan;
use App\Models\StudentMonthlyPlanDay;
use App\Models\StudentMonthlyPlanItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentMonthlyPlanGenerator
{
    /**
     * @return array{generated: int, skipped_students: int, plan_ids: array<int, int>, monthly_plan_ids: array<int, int>}
     */
    public function generateForCenter(Center $center, int $month, int $year, ?int $groupId = null): array
    {
        $students = Student::query()
            ->with(['center:id,working_days', 'group:id,center_id', 'plan:id,name'])
            ->where('center_id', $center->id)
            ->when($groupId !== null, fn ($query) => $query->where('group_id', $groupId))
            ->where('is_active', Student::STATUS_ACTIVE)
            ->whereNotNull('plan_type_id')
            ->orderBy('full_name')
            ->get();

        return $this->generateForStudents($students, $month, $year);
    }

    /**
     * @return array{generated: int, skipped_students: int, plan_ids: array<int, int>, monthly_plan_ids: array<int, int>}
     */
    public function generateForGroup(Group $group, int $month, int $year): array
    {
        $students = Student::query()
            ->with(['center:id,working_days', 'group:id,center_id', 'plan:id,name'])
            ->where('group_id', $group->id)
            ->where('is_active', Student::STATUS_ACTIVE)
            ->whereNotNull('plan_type_id')
            ->orderBy('full_name')
            ->get();

        return $this->generateForStudents($students, $month, $year);
    }

    /**
     * @param  EloquentCollection<int, Student>  $students
     * @return array{generated: int, skipped_students: int, plan_ids: array<int, int>, monthly_plan_ids: array<int, int>}
     */
    private function generateForStudents(EloquentCollection $students, int $month, int $year): array
    {
        $planIds = [];
        $monthlyPlanIds = [];
        $skipped = 0;

        foreach ($students as $student) {
            $monthlyPlan = $this->generateForStudent($student, $month, $year);
            if ($monthlyPlan === null) {
                $skipped++;

                continue;
            }

            $planIds[] = (int) $monthlyPlan->id;
            if ($monthlyPlan->monthly_plan_id !== null) {
                $monthlyPlanIds[] = (int) $monthlyPlan->monthly_plan_id;
            }
        }

        return [
            'generated' => count($planIds),
            'skipped_students' => $skipped,
            'plan_ids' => $planIds,
            'monthly_plan_ids' => array_values(array_unique($monthlyPlanIds)),
        ];
    }

    public function generateForStudent(Student $student, int $month, int $year): ?StudentMonthlyPlan
    {
        if ($student->plan_type_id === null) {
            return null;
        }

        $student->loadMissing(['center:id,working_days', 'group:id,center_id']);
        $dates = $this->workingDatesForMonth($student, $month, $year);
        if ($dates->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($student, $month, $year, $dates): ?StudentMonthlyPlan {
            $existingPlan = StudentMonthlyPlan::query()
                ->withCount('items')
                ->where('student_id', $student->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($existingPlan !== null) {
                if ($existingPlan->items_count > 0) {
                    return null;
                }

                $existingPlan->delete();
            }

            /** @var Student $lockedStudent */
            $lockedStudent = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();
            $startPoint = $this->startPoint($lockedStudent);

            $monthlyPlan = $this->monthlyPlanForStudent($lockedStudent, $month, $year);

            $plan = StudentMonthlyPlan::query()->create([
                'monthly_plan_id' => $monthlyPlan->id,
                'student_id' => $lockedStudent->id,
                'center_id' => $lockedStudent->center_id,
                'group_id' => $lockedStudent->group_id,
                'plan_id' => $lockedStudent->plan_type_id,
                'month' => $month,
                'year' => $year,
                'max_daily_weight' => max(1, (int) ($lockedStudent->max_daily_weight ?? 2)),
                'starts_after_plan_point_id' => $startPoint?->id,
                'status' => StudentMonthlyPlan::STATUS_GENERATED,
                'generated_at' => now(),
            ]);

            $points = $this->planPointsAfter($lockedStudent, $startPoint);
            $result = $this->fillMonthlyPlan($plan, $lockedStudent, $points, $dates);

            $plan->update([
                'ends_at_plan_point_id' => $result['last_plan_point_id'],
                'generated_items_count' => $result['generated_items_count'],
                'skipped_items_count' => $result['skipped_items_count'],
                'status' => $result['generated_items_count'] > 0
                    ? StudentMonthlyPlan::STATUS_GENERATED
                    : StudentMonthlyPlan::STATUS_EXHAUSTED,
            ]);

            $this->refreshMonthlyPlanTotals($monthlyPlan);

            return $plan->refresh()->load(['days.items.planPoint', 'student', 'plan']);
        });
    }

    private function monthlyPlanForStudent(Student $student, int $month, int $year): MonthlyPlan
    {
        $attributes = [
            'group_id' => $student->group_id,
            'month' => $month,
            'year' => $year,
        ];

        if ($student->group_id === null) {
            $attributes['center_id'] = $student->center_id;
        }

        return MonthlyPlan::query()->firstOrCreate($attributes, [
            'center_id' => $student->center_id,
            'admin_id' => Auth::id(),
            'generated_at' => now(),
        ]);
    }

    private function refreshMonthlyPlanTotals(MonthlyPlan $monthlyPlan): void
    {
        $summary = StudentMonthlyPlan::query()
            ->where('monthly_plan_id', $monthlyPlan->id)
            ->selectRaw('COUNT(*) as students_count')
            ->selectRaw('COALESCE(SUM(generated_items_count), 0) as generated_items_count')
            ->selectRaw('COALESCE(SUM(skipped_items_count), 0) as skipped_items_count')
            ->selectRaw('MAX(generated_at) as generated_at')
            ->first();

        $monthlyPlan->update([
            'students_count' => (int) ($summary->students_count ?? 0),
            'generated_items_count' => (int) ($summary->generated_items_count ?? 0),
            'skipped_items_count' => (int) ($summary->skipped_items_count ?? 0),
            'generated_at' => $summary->generated_at ?? $monthlyPlan->generated_at,
        ]);
    }

    /**
     * @param  EloquentCollection<int, PlanPoint>  $points
     * @param  Collection<int, CarbonImmutable>  $dates
     * @return array{last_plan_point_id: ?int, generated_items_count: int, skipped_items_count: int}
     */
    private function fillMonthlyPlan(
        StudentMonthlyPlan $plan,
        Student $student,
        EloquentCollection $points,
        Collection $dates,
    ): array {
        $dateIndex = 0;
        $sortOrder = 1;
        $generatedCount = 0;
        $lastPlanPointId = null;
        $currentDay = null;
        $lastGeneratedDay = null;
        $currentWeight = 0.0;
        $pendingZeroPoints = [];
        $maxDailyWeight = max(1, (int) $plan->max_daily_weight);

        foreach ($points as $point) {
            $weightData = $this->weightDataForPoint($point);
            $weight = max(0, (float) $weightData['weight']);
            $isStandalone = (bool) $weightData['is_standalone'];

            if ($weight <= 0) {
                if ($lastGeneratedDay !== null) {
                    $this->createItem($plan, $lastGeneratedDay, $student, $point, $weightData, $sortOrder++, StudentMonthlyPlanItem::STATUS_ATTACHED);
                    $generatedCount++;
                    $lastPlanPointId = (int) $point->id;

                    continue;
                }

                $pendingZeroPoints[] = [$point, $weightData];

                continue;
            }

            if ($isStandalone || $weight > $maxDailyWeight) {
                if ($currentDay !== null && $currentWeight > 0) {
                    $dateIndex++;
                    $currentDay = null;
                    $currentWeight = 0.0;
                }

                $day = $this->dayAt($plan, $dates, $dateIndex);
                if ($day === null) {
                    break;
                }

                foreach ($pendingZeroPoints as [$zeroPoint, $zeroWeightData]) {
                    $this->createItem($plan, $day, $student, $zeroPoint, $zeroWeightData, $sortOrder++, StudentMonthlyPlanItem::STATUS_ATTACHED);
                    $generatedCount++;
                    $lastPlanPointId = (int) $zeroPoint->id;
                }
                $pendingZeroPoints = [];

                $this->createItem(
                    $plan,
                    $day,
                    $student,
                    $point,
                    $weightData,
                    $sortOrder++,
                    $weight > $maxDailyWeight ? StudentMonthlyPlanItem::STATUS_SPECIAL : StudentMonthlyPlanItem::STATUS_GENERATED,
                );
                $day->update(['total_weight' => $weight]);
                $generatedCount++;
                $lastPlanPointId = (int) $point->id;
                $lastGeneratedDay = $day;
                $dateIndex++;
                $currentDay = null;
                $currentWeight = 0.0;

                continue;
            }

            if ($currentDay === null) {
                $currentDay = $this->dayAt($plan, $dates, $dateIndex);
                $currentWeight = 0.0;
            }

            if ($currentDay === null) {
                break;
            }

            if ($currentWeight > 0 && $currentWeight + $weight > $maxDailyWeight) {
                $dateIndex++;
                $currentDay = $this->dayAt($plan, $dates, $dateIndex);
                $currentWeight = 0.0;
            }

            if ($currentDay === null) {
                break;
            }

            foreach ($pendingZeroPoints as [$zeroPoint, $zeroWeightData]) {
                $this->createItem($plan, $currentDay, $student, $zeroPoint, $zeroWeightData, $sortOrder++, StudentMonthlyPlanItem::STATUS_ATTACHED);
                $generatedCount++;
                $lastPlanPointId = (int) $zeroPoint->id;
                $lastGeneratedDay = $currentDay;
            }
            $pendingZeroPoints = [];

            $this->createItem($plan, $currentDay, $student, $point, $weightData, $sortOrder++, StudentMonthlyPlanItem::STATUS_GENERATED);
            $currentWeight += $weight;
            $currentDay->update(['total_weight' => $currentWeight]);
            $generatedCount++;
            $lastPlanPointId = (int) $point->id;
            $lastGeneratedDay = $currentDay;
        }

        return [
            'last_plan_point_id' => $lastPlanPointId,
            'generated_items_count' => $generatedCount,
            'skipped_items_count' => 0,
        ];
    }

    /**
     * @return array{weight: float, is_standalone: bool}
     */
    private function weightDataForPoint(PlanPoint $point): array
    {
        return [
            'weight' => max(0, (float) ($point->weight ?? 1)),
            'is_standalone' => (bool) ($point->is_standalone ?? false),
        ];
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $dates
     */
    private function dayAt(StudentMonthlyPlan $plan, Collection $dates, int $index): ?StudentMonthlyPlanDay
    {
        /** @var CarbonImmutable|null $date */
        $date = $dates->get($index);
        if ($date === null) {
            return null;
        }

        return StudentMonthlyPlanDay::query()->firstOrCreate([
            'student_monthly_plan_id' => $plan->id,
            'date' => $date->toDateString(),
        ], [
            'day_number' => $date->day,
            'total_weight' => 0,
        ]);
    }

    /**
     * @param  array{weight: float, is_standalone: bool}  $weightData
     */
    private function createItem(
        StudentMonthlyPlan $plan,
        StudentMonthlyPlanDay $day,
        Student $student,
        PlanPoint $point,
        array $weightData,
        int $sortOrder,
        string $status,
    ): void {
        StudentMonthlyPlanItem::query()->create([
            'student_monthly_plan_id' => $plan->id,
            'student_monthly_plan_day_id' => $day->id,
            'student_id' => $student->id,
            'plan_point_id' => $point->id,
            'sort_order' => $sortOrder,
            'weight' => max(0, (float) $weightData['weight']),
            'is_standalone' => (bool) $weightData['is_standalone'],
            'status' => $status,
        ]);
    }

    private function startPoint(Student $student): ?PlanPoint
    {
        if ($student->current_plan_point_id === null) {
            return null;
        }

        return PlanPoint::query()
            ->whereKey($student->current_plan_point_id)
            ->where('plan_id', $student->plan_type_id)
            ->first();
    }

    /**
     * @return EloquentCollection<int, PlanPoint>
     */
    private function planPointsAfter(Student $student, ?PlanPoint $startPoint): EloquentCollection
    {
        return PlanPoint::query()
            ->where('plan_id', $student->plan_type_id)
            ->when($startPoint !== null, function ($query) use ($startPoint): void {
                $query->where(function ($inner) use ($startPoint): void {
                    $inner->where('sort_order', '>', $startPoint->sort_order)
                        ->orWhere(function ($nested) use ($startPoint): void {
                            $nested->where('sort_order', $startPoint->sort_order)
                                ->where('id', '>', $startPoint->id);
                        });
                });
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function workingDatesForMonth(Student $student, int $month, int $year): Collection
    {
        $workingDays = $student->center?->working_days;
        if (! is_array($workingDays) || $workingDays === []) {
            $workingDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        }

        $workingDayLookup = array_fill_keys(array_map(static fn (string $day): string => strtolower($day), $workingDays), true);
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();
        $dates = collect();

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if (isset($workingDayLookup[$dayNames[$date->dayOfWeek]])) {
                $dates->push($date);
            }
        }

        return $dates;
    }
}

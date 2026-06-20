<?php

namespace App\Services\Admin;

use App\Models\Group;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentMonthlyPlan;
use App\Models\StudentMonthlyPlanDay;
use App\Models\StudentMonthlyPlanItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentMonthlyPlanGenerator
{
    public function __construct(private readonly PlanPointWeightClassifier $classifier) {}

    /**
     * @return array{generated: int, skipped_students: int, plan_ids: array<int, int>}
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

        $planIds = [];
        $skipped = 0;

        foreach ($students as $student) {
            $monthlyPlan = $this->generateForStudent($student, $month, $year);
            if ($monthlyPlan === null) {
                $skipped++;
                continue;
            }

            $planIds[] = (int) $monthlyPlan->id;
        }

        return [
            'generated' => count($planIds),
            'skipped_students' => $skipped,
            'plan_ids' => $planIds,
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
                ->where('student_id', $student->id)
                ->where('year', $year)
                ->where('month', $month)
                ->first();
            $startPoint = $this->startPoint($student, $existingPlan);

            if ($existingPlan !== null) {
                $existingPlan->delete();
            }

            /** @var Student $lockedStudent */
            $lockedStudent = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();

            $plan = StudentMonthlyPlan::query()->create([
                'student_id' => $lockedStudent->id,
                'center_id' => $lockedStudent->center_id,
                'group_id' => $lockedStudent->group_id,
                'plan_id' => $lockedStudent->plan_type_id,
                'month' => $month,
                'year' => $year,
                'max_daily_weight' => max(0.01, (float) ($lockedStudent->max_daily_weight ?? 2)),
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

            if ($result['last_plan_point_id'] !== null) {
                $lockedStudent->update([
                    'monthly_plan_cursor_point_id' => $this->furthestPlanPointId(
                        $lockedStudent->monthly_plan_cursor_point_id,
                        $result['last_plan_point_id'],
                    ),
                ]);
            }

            return $plan->refresh()->load(['days.items.planPoint', 'student', 'plan']);
        });
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
        $skippedCount = 0;
        $lastPlanPointId = null;
        $currentDay = null;
        $currentWeight = 0.0;
        $pendingZeroPoints = [];
        $maxDailyWeight = max(0.01, (float) $plan->max_daily_weight);

        foreach ($points as $point) {
            $classification = $this->classifier->classify($point);
            $weight = max(0, (float) $classification['weight']);
            $isStandalone = (bool) $classification['is_standalone'];

            $point->forceFill([
                'weight' => $weight,
                'is_standalone' => $isStandalone,
                'plan_weight_rule_id' => $classification['rule_id'],
            ])->save();

            if ($weight <= 0) {
                $pendingZeroPoints[] = [$point, $classification];
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
                    $this->createSkippedItem($plan, $student, $point, $classification, $sortOrder++);
                    $skippedCount++;
                    continue;
                }

                foreach ($pendingZeroPoints as [$zeroPoint, $zeroClassification]) {
                    $this->createItem($plan, $day, $student, $zeroPoint, $zeroClassification, $sortOrder++, StudentMonthlyPlanItem::STATUS_ATTACHED);
                    $generatedCount++;
                    $lastPlanPointId = (int) $zeroPoint->id;
                }
                $pendingZeroPoints = [];

                $this->createItem(
                    $plan,
                    $day,
                    $student,
                    $point,
                    $classification,
                    $sortOrder++,
                    $weight > $maxDailyWeight ? StudentMonthlyPlanItem::STATUS_SPECIAL : StudentMonthlyPlanItem::STATUS_GENERATED,
                );
                $day->update(['total_weight' => $weight]);
                $generatedCount++;
                $lastPlanPointId = (int) $point->id;
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
                $this->createSkippedItem($plan, $student, $point, $classification, $sortOrder++);
                $skippedCount++;
                continue;
            }

            if ($currentWeight > 0 && $currentWeight + $weight > $maxDailyWeight) {
                $dateIndex++;
                $currentDay = $this->dayAt($plan, $dates, $dateIndex);
                $currentWeight = 0.0;
            }

            if ($currentDay === null) {
                $this->createSkippedItem($plan, $student, $point, $classification, $sortOrder++);
                $skippedCount++;
                continue;
            }

            foreach ($pendingZeroPoints as [$zeroPoint, $zeroClassification]) {
                $this->createItem($plan, $currentDay, $student, $zeroPoint, $zeroClassification, $sortOrder++, StudentMonthlyPlanItem::STATUS_ATTACHED);
                $generatedCount++;
                $lastPlanPointId = (int) $zeroPoint->id;
            }
            $pendingZeroPoints = [];

            $this->createItem($plan, $currentDay, $student, $point, $classification, $sortOrder++, StudentMonthlyPlanItem::STATUS_GENERATED);
            $currentWeight += $weight;
            $currentDay->update(['total_weight' => $currentWeight]);
            $generatedCount++;
            $lastPlanPointId = (int) $point->id;
        }

        if ($pendingZeroPoints !== []) {
            if ($currentDay === null) {
                $currentDay = $this->dayAt($plan, $dates, max(0, min($dateIndex, $dates->count() - 1)));
            }

            if ($currentDay !== null && $generatedCount > 0) {
                foreach ($pendingZeroPoints as [$zeroPoint, $zeroClassification]) {
                    $this->createItem($plan, $currentDay, $student, $zeroPoint, $zeroClassification, $sortOrder++, StudentMonthlyPlanItem::STATUS_ATTACHED);
                    $generatedCount++;
                    $lastPlanPointId = (int) $zeroPoint->id;
                }
            } else {
                foreach ($pendingZeroPoints as [$zeroPoint, $zeroClassification]) {
                    $this->createSkippedItem($plan, $student, $zeroPoint, $zeroClassification, $sortOrder++);
                    $skippedCount++;
                }
            }
        }

        return [
            'last_plan_point_id' => $lastPlanPointId,
            'generated_items_count' => $generatedCount,
            'skipped_items_count' => $skippedCount,
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
     * @param array{weight: float, is_standalone: bool, rule_id: ?int, estimated_pages: ?int} $classification
     */
    private function createItem(
        StudentMonthlyPlan $plan,
        StudentMonthlyPlanDay $day,
        Student $student,
        PlanPoint $point,
        array $classification,
        int $sortOrder,
        string $status,
    ): void {
        StudentMonthlyPlanItem::query()->create([
            'student_monthly_plan_id' => $plan->id,
            'student_monthly_plan_day_id' => $day->id,
            'student_id' => $student->id,
            'plan_point_id' => $point->id,
            'sort_order' => $sortOrder,
            'weight' => max(0, (float) $classification['weight']),
            'is_standalone' => (bool) $classification['is_standalone'],
            'status' => $status,
        ]);
    }

    /**
     * @param array{weight: float, is_standalone: bool, rule_id: ?int, estimated_pages: ?int} $classification
     */
    private function createSkippedItem(
        StudentMonthlyPlan $plan,
        Student $student,
        PlanPoint $point,
        array $classification,
        int $sortOrder,
    ): void {
        StudentMonthlyPlanItem::query()->create([
            'student_monthly_plan_id' => $plan->id,
            'student_monthly_plan_day_id' => null,
            'student_id' => $student->id,
            'plan_point_id' => $point->id,
            'sort_order' => $sortOrder,
            'weight' => max(0, (float) $classification['weight']),
            'is_standalone' => (bool) $classification['is_standalone'],
            'status' => StudentMonthlyPlanItem::STATUS_SKIPPED,
        ]);
    }

    private function startPoint(Student $student, ?StudentMonthlyPlan $existingPlan): ?PlanPoint
    {
        if ($existingPlan?->starts_after_plan_point_id !== null) {
            return PlanPoint::query()
                ->whereKey($existingPlan->starts_after_plan_point_id)
                ->where('plan_id', $student->plan_type_id)
                ->first();
        }

        $cursorId = $student->monthly_plan_cursor_point_id ?? $student->current_plan_point_id;
        if ($cursorId === null) {
            return null;
        }

        return PlanPoint::query()
            ->whereKey($cursorId)
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

    private function furthestPlanPointId(?int $currentId, int $candidateId): int
    {
        if ($currentId === null) {
            return $candidateId;
        }

        $points = PlanPoint::query()
            ->whereIn('id', [$currentId, $candidateId])
            ->get()
            ->keyBy('id');
        $current = $points->get($currentId);
        $candidate = $points->get($candidateId);

        if ($current === null || $candidate === null || $current->plan_id !== $candidate->plan_id) {
            return $candidateId;
        }

        if ($candidate->sort_order > $current->sort_order) {
            return $candidateId;
        }

        if ($candidate->sort_order === $current->sort_order && $candidate->id > $current->id) {
            return $candidateId;
        }

        return $currentId;
    }
}

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
use App\Support\DailyWeightLimits;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentMonthlyPlanGenerator
{
    public function __construct(private readonly AdminDataScopeService $dataScope) {}

    /**
     * @return array{generated: int, skipped_students: int, plan_ids: array<int, int>, monthly_plan_ids: array<int, int>}
     */
    public function generateForCenter(Center $center, int $month, int $year, ?int $groupId = null): array
    {
        $students = Student::query()
            ->with(['center:id,working_days', 'group:id,center_id', 'plan:id,name'])
            ->where('center_id', $center->id)
            ->when($groupId !== null, fn ($query) => $query->where('group_id', $groupId))
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
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
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
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

        $this->dataScope->abortUnlessCanAccessStudent($student);

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
            $lockedStudent->loadMissing('center:id,working_days');
            $startPoint = $this->startPoint($lockedStudent);

            $monthlyPlan = $this->monthlyPlanForStudent($lockedStudent, $month, $year);
            $maxDailyWeight = DailyWeightLimits::normalizeLimit($lockedStudent->max_daily_weight ?? 2);
            $dailyWeightLimits = DailyWeightLimits::normalize(
                $lockedStudent->daily_weight_limits,
                $maxDailyWeight,
                $lockedStudent->center?->working_days,
            );

            $plan = StudentMonthlyPlan::query()->create([
                'monthly_plan_id' => $monthlyPlan->id,
                'student_id' => $lockedStudent->id,
                'center_id' => $lockedStudent->center_id,
                'group_id' => $lockedStudent->group_id,
                'plan_id' => $lockedStudent->plan_type_id,
                'month' => $month,
                'year' => $year,
                'max_daily_weight' => $maxDailyWeight,
                'daily_weight_limits' => $dailyWeightLimits,
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

    /**
     * @return array{student_plans: int, generated_items: int}
     */
    public function regenerateFutureForMonthlyPlan(MonthlyPlan $monthlyPlan, CarbonImmutable $fromDate): array
    {
        $this->dataScope->abortUnlessCanAccessMonthlyPlan($monthlyPlan);
        $fromDate = $fromDate->startOfDay();

        return DB::transaction(function () use ($monthlyPlan, $fromDate): array {
            /** @var MonthlyPlan $lockedMonthlyPlan */
            $lockedMonthlyPlan = MonthlyPlan::query()
                ->with('center:id,working_days')
                ->whereKey($monthlyPlan->id)
                ->lockForUpdate()
                ->firstOrFail();

            $dates = $this->workingDatesForPeriod(
                $lockedMonthlyPlan->center?->working_days,
                (int) $lockedMonthlyPlan->month,
                (int) $lockedMonthlyPlan->year,
                $fromDate,
            );

            $studentPlanIds = StudentMonthlyPlan::query()
                ->join('students', 'student_monthly_plans.student_id', '=', 'students.id')
                ->where('student_monthly_plans.monthly_plan_id', $lockedMonthlyPlan->id)
                ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
                ->orderBy('student_monthly_plans.student_id')
                ->pluck('student_monthly_plans.id')
                ->all();

            $generatedItems = 0;

            foreach ($studentPlanIds as $studentPlanId) {
                /** @var StudentMonthlyPlan $studentPlan */
                $studentPlan = StudentMonthlyPlan::query()
                    ->whereKey($studentPlanId)
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var Student $student */
                $student = Student::query()
                    ->whereKey($studentPlan->student_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $generatedItems += $this->regenerateFutureForStudentPlan(
                    plan: $studentPlan,
                    student: $student,
                    dates: $dates,
                    fromDate: $fromDate,
                    workingDays: $lockedMonthlyPlan->center?->working_days,
                );
            }

            $this->refreshMonthlyPlanTotals($lockedMonthlyPlan);

            return [
                'student_plans' => count($studentPlanIds),
                'generated_items' => $generatedItems,
            ];
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
     * @param  Collection<int, CarbonImmutable>  $dates
     */
    private function regenerateFutureForStudentPlan(
        StudentMonthlyPlan $plan,
        Student $student,
        Collection $dates,
        CarbonImmutable $fromDate,
        mixed $workingDays,
    ): int {
        $planId = (int) ($plan->plan_id ?? $student->plan_type_id);
        $lastPreservedPlanPointId = $this->lastPlanPointIdBefore($plan, $fromDate)
            ?? $plan->starts_after_plan_point_id;

        $futureDayIds = StudentMonthlyPlanDay::query()
            ->where('student_monthly_plan_id', $plan->id)
            ->whereDate('date', '>=', $fromDate->toDateString())
            ->pluck('id')
            ->all();

        if ($futureDayIds !== []) {
            StudentMonthlyPlanItem::query()
                ->whereIn('student_monthly_plan_day_id', $futureDayIds)
                ->delete();

            StudentMonthlyPlanDay::query()
                ->whereIn('id', $futureDayIds)
                ->delete();
        }

        $maxDailyWeight = DailyWeightLimits::normalizeLimit($student->max_daily_weight ?? $plan->max_daily_weight ?? 2);
        $dailyWeightLimits = DailyWeightLimits::normalize(
            $student->daily_weight_limits,
            $maxDailyWeight,
            $workingDays,
        );

        $plan->forceFill([
            'max_daily_weight' => $maxDailyWeight,
            'daily_weight_limits' => $dailyWeightLimits,
        ])->save();

        $startPoint = $this->planPointByIdForPlan($lastPreservedPlanPointId, $planId);
        $points = $this->planPointsAfter($student, $startPoint, $planId);
        $startingSortOrder = ((int) $plan->items()->max('sort_order')) + 1;
        $result = $this->fillMonthlyPlan($plan, $student, $points, $dates, $startingSortOrder);

        $totalItems = $plan->items()->count();
        $lastPlanPointId = $result['last_plan_point_id'] ?? $lastPreservedPlanPointId;

        $plan->update([
            'ends_at_plan_point_id' => $lastPlanPointId,
            'generated_items_count' => $totalItems,
            'skipped_items_count' => 0,
            'status' => $totalItems > 0
                ? StudentMonthlyPlan::STATUS_GENERATED
                : StudentMonthlyPlan::STATUS_EXHAUSTED,
            'generated_at' => now(),
        ]);

        return $result['generated_items_count'];
    }

    private function lastPlanPointIdBefore(StudentMonthlyPlan $plan, CarbonImmutable $fromDate): ?int
    {
        $item = StudentMonthlyPlanItem::query()
            ->join('student_monthly_plan_days', 'student_monthly_plan_items.student_monthly_plan_day_id', '=', 'student_monthly_plan_days.id')
            ->where('student_monthly_plan_items.student_monthly_plan_id', $plan->id)
            ->whereDate('student_monthly_plan_days.date', '<', $fromDate->toDateString())
            ->whereNotNull('student_monthly_plan_items.plan_point_id')
            ->orderByDesc('student_monthly_plan_items.sort_order')
            ->select('student_monthly_plan_items.plan_point_id')
            ->first();

        return $item?->plan_point_id !== null ? (int) $item->plan_point_id : null;
    }

    private function planPointByIdForPlan(?int $planPointId, int $planId): ?PlanPoint
    {
        if ($planPointId === null || $planId <= 0) {
            return null;
        }

        return PlanPoint::query()
            ->whereKey($planPointId)
            ->where('plan_id', $planId)
            ->first();
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
        int $startingSortOrder = 1,
    ): array {
        $dateIndex = 0;
        $sortOrder = $startingSortOrder;
        $generatedCount = 0;
        $lastPlanPointId = null;
        $currentDay = null;
        $lastGeneratedDay = null;
        $currentWeight = 0.0;
        $pendingZeroPoints = [];
        $currentDailyLimit = DailyWeightLimits::normalizeLimit($plan->max_daily_weight);

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

            if ($isStandalone) {
                if ($currentDay !== null && $currentWeight > 0) {
                    $dateIndex++;
                    $currentDay = null;
                    $currentWeight = 0.0;
                }

                $day = $this->dayAt($plan, $dates, $dateIndex);
                if ($day === null) {
                    break;
                }
                $currentDailyLimit = $this->dailyLimitAt($plan, $dates, $dateIndex);

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
                    $weight > $currentDailyLimit ? StudentMonthlyPlanItem::STATUS_SPECIAL : StudentMonthlyPlanItem::STATUS_GENERATED,
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
                $currentDailyLimit = $this->dailyLimitAt($plan, $dates, $dateIndex);
            }

            if ($currentDay === null) {
                break;
            }

            if ($currentWeight > 0 && $currentWeight + $weight > $currentDailyLimit) {
                $dateIndex++;
                $currentDay = $this->dayAt($plan, $dates, $dateIndex);
                $currentWeight = 0.0;
                $currentDailyLimit = $this->dailyLimitAt($plan, $dates, $dateIndex);
            }

            if ($currentDay === null) {
                break;
            }

            if ($weight > $currentDailyLimit) {
                foreach ($pendingZeroPoints as [$zeroPoint, $zeroWeightData]) {
                    $this->createItem($plan, $currentDay, $student, $zeroPoint, $zeroWeightData, $sortOrder++, StudentMonthlyPlanItem::STATUS_ATTACHED);
                    $generatedCount++;
                    $lastPlanPointId = (int) $zeroPoint->id;
                }
                $pendingZeroPoints = [];

                $this->createItem($plan, $currentDay, $student, $point, $weightData, $sortOrder++, StudentMonthlyPlanItem::STATUS_SPECIAL);
                $currentDay->update(['total_weight' => $weight]);
                $generatedCount++;
                $lastPlanPointId = (int) $point->id;
                $lastGeneratedDay = $currentDay;
                $dateIndex++;
                $currentDay = null;
                $currentWeight = 0.0;

                continue;
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
     * @param  Collection<int, CarbonImmutable>  $dates
     */
    private function dailyLimitAt(StudentMonthlyPlan $plan, Collection $dates, int $index): int
    {
        /** @var CarbonImmutable|null $date */
        $date = $dates->get($index);
        if ($date === null) {
            return DailyWeightLimits::normalizeLimit($plan->max_daily_weight);
        }

        return DailyWeightLimits::limitForDate($plan->daily_weight_limits, $date, $plan->max_daily_weight);
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

        $dailyLimit = $this->dailyLimitAt($plan, $dates, $index);
        $day = StudentMonthlyPlanDay::query()->firstOrCreate([
            'student_monthly_plan_id' => $plan->id,
            'date' => $date->toDateString(),
        ], [
            'day_number' => $date->day,
            'total_weight' => 0,
            'daily_weight_limit' => $dailyLimit,
        ]);

        if ($day->daily_weight_limit === null) {
            $day->update(['daily_weight_limit' => $dailyLimit]);
        }

        return $day;
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
    private function planPointsAfter(Student $student, ?PlanPoint $startPoint, ?int $planId = null): EloquentCollection
    {
        $planId ??= (int) $student->plan_type_id;

        return PlanPoint::query()
            ->where('plan_id', $planId)
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
        return $this->workingDatesForPeriod($student->center?->working_days, $month, $year);
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function workingDatesForPeriod(mixed $workingDays, int $month, int $year, ?CarbonImmutable $fromDate = null): Collection
    {
        if (! is_array($workingDays) || $workingDays === []) {
            $workingDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        }

        $workingDayLookup = array_fill_keys(array_map(static fn (string $day): string => strtolower($day), $workingDays), true);
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth();
        $dates = collect();

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if ($fromDate !== null && $date->lt($fromDate)) {
                continue;
            }

            if (isset($workingDayLookup[$dayNames[$date->dayOfWeek]])) {
                $dates->push($date);
            }
        }

        return $dates;
    }
}

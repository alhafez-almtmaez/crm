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
use App\Services\System\SystemSettingsService;
use App\Support\DailyWeightLimits;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudentMonthlyPlanGenerator
{
    public function __construct(
        private readonly AdminDataScopeService $dataScope,
        private readonly SystemSettingsService $settings,
    ) {}

    /**
     * Generate the existing plan that covered each membership when it began.
     *
     * @param  array<int, int|string>  $groupIds
     * @return array{generated: int, skipped_groups: int, plan_ids: array<int, int>, monthly_plan_ids: array<int, int>}
     */
    public function syncStudentToExistingGroupPlans(Student $student, array $groupIds): array
    {
        $groupIds = collect($groupIds)
            ->map(static fn ($groupId): int => (int) $groupId)
            ->filter(static fn (int $groupId): bool => $groupId > 0)
            ->unique()
            ->values();

        if (
            $groupIds->isEmpty()
            || (int) $student->is_active !== Student::STATUS_ACTIVE
            || $student->plan_type_id === null
        ) {
            return [
                'generated' => 0,
                'skipped_groups' => $groupIds->count(),
                'plan_ids' => [],
                'monthly_plan_ids' => [],
            ];
        }

        $this->dataScope->abortUnlessCanAccessStudent($student);

        $memberships = DB::table('group_student')
            ->where('student_id', $student->id)
            ->whereIn('group_id', $groupIds->all())
            ->get(['group_id', 'created_at'])
            ->keyBy(static fn (object $membership): int => (int) $membership->group_id);
        $planIds = [];
        $monthlyPlanIds = [];
        $generatedGroupIds = [];

        foreach ($groupIds as $groupId) {
            $membership = $memberships->get($groupId);
            if ($membership === null) {
                continue;
            }

            $effectiveStartDate = $this->membershipEffectiveDate($membership->created_at);
            $monthlyPlan = $this->monthlyPlanCoveringDate($groupId, $effectiveStartDate);
            if (! $monthlyPlan instanceof MonthlyPlan) {
                continue;
            }

            $studentPlan = $this->generateStudentForMonthlyPlan($student, $monthlyPlan, $effectiveStartDate);
            if (! $studentPlan instanceof StudentMonthlyPlan) {
                continue;
            }

            $planIds[] = (int) $studentPlan->id;
            $monthlyPlanIds[] = (int) $monthlyPlan->id;
            $generatedGroupIds[] = $groupId;
        }

        return [
            'generated' => count($planIds),
            'skipped_groups' => $groupIds->diff(array_unique($generatedGroupIds))->count(),
            'plan_ids' => $planIds,
            'monthly_plan_ids' => array_values(array_unique($monthlyPlanIds)),
        ];
    }

    /**
     * Repair memberships that were added without a student plan.
     *
     * Ended plans receive an empty audit marker because rebuilding historical
     * assignments from the student's current cursor would fabricate history.
     * Active plans are generated only from today onward.
     *
     * @param  array<int, int|string>  $groupIds
     * @return array{generated: int, skipped_groups: int, plan_ids: array<int, int>, monthly_plan_ids: array<int, int>}
     */
    public function repairStudentToExistingGroupPlans(Student $student, array $groupIds): array
    {
        $groupIds = collect($groupIds)
            ->map(static fn ($groupId): int => (int) $groupId)
            ->filter(static fn (int $groupId): bool => $groupId > 0)
            ->unique()
            ->values();

        if (
            $groupIds->isEmpty()
            || (int) $student->is_active !== Student::STATUS_ACTIVE
            || $student->plan_type_id === null
        ) {
            return [
                'generated' => 0,
                'skipped_groups' => $groupIds->count(),
                'plan_ids' => [],
                'monthly_plan_ids' => [],
            ];
        }

        $this->dataScope->abortUnlessCanAccessStudent($student);

        $memberships = DB::table('group_student')
            ->where('student_id', $student->id)
            ->whereIn('group_id', $groupIds->all())
            ->get(['group_id', 'created_at'])
            ->keyBy(static fn (object $membership): int => (int) $membership->group_id);
        $today = CarbonImmutable::now($this->systemTimezone())->startOfDay();
        $planIds = [];
        $monthlyPlanIds = [];
        $repairedGroupIds = [];

        foreach ($groupIds as $groupId) {
            $membership = $memberships->get($groupId);
            if ($membership === null) {
                continue;
            }

            $membershipDate = $this->membershipEffectiveDate($membership->created_at);
            $candidatePlans = collect([
                $this->monthlyPlanCoveringDate($groupId, $membershipDate),
                $this->monthlyPlanCoveringDate($groupId, $today),
            ])
                ->filter(static fn ($plan): bool => $plan instanceof MonthlyPlan)
                ->unique(static fn (MonthlyPlan $plan): int => (int) $plan->id)
                ->values();

            foreach ($candidatePlans as $monthlyPlan) {
                [, $periodEnd] = $this->periodForMonthlyPlan($monthlyPlan);
                $studentPlan = $periodEnd->lt($today)
                    ? $this->createHistoricalMembershipMarker($student, $monthlyPlan, $membershipDate)
                    : $this->generateStudentForMonthlyPlan(
                        student: $student,
                        monthlyPlan: $monthlyPlan,
                        effectiveStartDate: $this->laterDate($membershipDate, $today) ?? $membershipDate,
                    );

                if (! $studentPlan instanceof StudentMonthlyPlan) {
                    continue;
                }

                $planIds[] = (int) $studentPlan->id;
                $monthlyPlanIds[] = (int) $monthlyPlan->id;
                $repairedGroupIds[] = $groupId;
            }
        }

        return [
            'generated' => count($planIds),
            'skipped_groups' => $groupIds->diff(array_unique($repairedGroupIds))->count(),
            'plan_ids' => $planIds,
            'monthly_plan_ids' => array_values(array_unique($monthlyPlanIds)),
        ];
    }

    /**
     * @return array{generated: int, skipped_students: int, plan_ids: array<int, int>, monthly_plan_ids: array<int, int>}
     */
    public function generateForCenter(
        Center $center,
        int $month,
        int $year,
        ?int $groupId = null,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
        array $holidayDates = [],
    ): array {
        $students = Student::query()
            ->with(['center:id,working_days', 'groups:id,center_id,working_days', 'plan:id,name'])
            ->where('center_id', $center->id)
            ->when($groupId !== null, fn ($query) => $query->whereHas(
                'groups',
                static fn ($groupQuery) => $groupQuery->where('groups.id', $groupId),
            ))
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->where('is_active', Student::STATUS_ACTIVE)
            ->whereNotNull('plan_type_id')
            ->orderBy('full_name')
            ->get();

        return $this->generateForStudents($students, $month, $year, $startDate, $endDate, $holidayDates, $groupId);
    }

    /**
     * @return array{generated: int, skipped_students: int, plan_ids: array<int, int>, monthly_plan_ids: array<int, int>}
     */
    public function generateForGroup(
        Group $group,
        int $month,
        int $year,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
        array $holidayDates = [],
    ): array {
        [$periodStart, $periodEnd] = $this->periodForMonth($month, $year, $startDate, $endDate);
        $holidayDates = $this->normalizeHolidayDates($holidayDates, $periodStart, $periodEnd);
        $monthlyPlan = $this->monthlyPlanHeader(
            centerId: (int) $group->center_id,
            groupId: (int) $group->id,
            month: $month,
            year: $year,
            startDate: $periodStart,
            endDate: $periodEnd,
            holidayDates: $holidayDates,
        );

        // Once a header has student data, its stored period is authoritative.
        // This keeps newly joined students aligned with the original group plan.
        [$periodStart, $periodEnd] = $this->periodForMonthlyPlan($monthlyPlan);
        $holidayDates = $this->normalizeHolidayDates(
            (array) $monthlyPlan->holiday_dates,
            $periodStart,
            $periodEnd,
        );

        $students = Student::query()
            ->with(['center:id,working_days', 'groups:id,center_id,working_days', 'plan:id,name'])
            ->whereHas('groups', static fn ($query) => $query->where('groups.id', $group->id))
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->where('is_active', Student::STATUS_ACTIVE)
            ->whereNotNull('plan_type_id')
            ->orderBy('full_name')
            ->get();

        $result = $this->generateForStudents(
            $students,
            $month,
            $year,
            $periodStart,
            $periodEnd,
            $holidayDates,
            (int) $group->id,
        );
        $result['monthly_plan_ids'] = array_values(array_unique([
            (int) $monthlyPlan->id,
            ...$result['monthly_plan_ids'],
        ]));

        $this->refreshMonthlyPlanTotals($monthlyPlan);

        return $result;
    }

    /**
     * @param  EloquentCollection<int, Student>  $students
     * @return array{generated: int, skipped_students: int, plan_ids: array<int, int>, monthly_plan_ids: array<int, int>}
     */
    private function generateForStudents(
        EloquentCollection $students,
        int $month,
        int $year,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
        array $holidayDates = [],
        ?int $groupId = null,
    ): array {
        $planIds = [];
        $monthlyPlanIds = [];
        $skipped = 0;

        foreach ($students as $student) {
            $monthlyPlan = $this->generateForStudent($student, $month, $year, $startDate, $endDate, $holidayDates, $groupId);
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

    public function generateForStudent(
        Student $student,
        int $month,
        int $year,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
        array $holidayDates = [],
        ?int $groupId = null,
        ?CarbonImmutable $effectiveStartDate = null,
    ): ?StudentMonthlyPlan {
        if ($student->plan_type_id === null) {
            return null;
        }

        $this->dataScope->abortUnlessCanAccessStudent($student);
        [$periodStart, $periodEnd] = $this->periodForMonth($month, $year, $startDate, $endDate);
        $holidayDates = $this->normalizeHolidayDates($holidayDates, $periodStart, $periodEnd);

        $student->loadMissing(['center:id,working_days', 'groups:id,center_id,working_days']);
        $resolvedGroupId = $this->resolvedGroupId($student, $groupId);
        $storedEffectiveStartDate = StudentMonthlyPlan::query()
            ->where('student_id', $student->id)
            ->when(
                $resolvedGroupId === null,
                static fn ($query) => $query->whereNull('group_id'),
                static fn ($query) => $query->where('group_id', $resolvedGroupId),
            )
            ->where('year', $year)
            ->where('month', $month)
            ->value('effective_start_date');
        $effectiveStartDate = $this->laterDate(
            $effectiveStartDate?->startOfDay(),
            $this->carbonDate($storedEffectiveStartDate),
        );

        if ($effectiveStartDate?->gt($periodEnd)) {
            return null;
        }

        $scheduleStart = $this->laterDate($periodStart, $effectiveStartDate) ?? $periodStart;
        $planEffectiveStartDate = $effectiveStartDate !== null ? $scheduleStart : null;
        $dates = $this->workingDatesForMonth($student, $resolvedGroupId, $month, $year, $scheduleStart, $periodEnd, $holidayDates);
        if ($dates->isEmpty() && $planEffectiveStartDate === null) {
            return null;
        }

        return DB::transaction(function () use ($student, $month, $year, $periodStart, $periodEnd, $holidayDates, $dates, $resolvedGroupId, $planEffectiveStartDate): ?StudentMonthlyPlan {
            $existingPlan = StudentMonthlyPlan::query()
                ->withCount('items')
                ->where('student_id', $student->id)
                ->when(
                    $resolvedGroupId === null,
                    static fn ($query) => $query->whereNull('group_id'),
                    static fn ($query) => $query->where('group_id', $resolvedGroupId),
                )
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($existingPlan !== null) {
                if (
                    $existingPlan->items_count > 0
                    || ($dates->isEmpty() && $existingPlan->effective_start_date !== null)
                ) {
                    return null;
                }

                $existingPlan->delete();
            }

            /** @var Student $lockedStudent */
            $lockedStudent = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedStudent->loadMissing(['center:id,working_days', 'groups:id,center_id,working_days']);
            $startPoint = $this->startPoint($lockedStudent);
            $workingDays = $this->workingDaysForStudent($lockedStudent, $resolvedGroupId);

            $monthlyPlan = $this->monthlyPlanForStudent($lockedStudent, $month, $year, $periodStart, $periodEnd, $holidayDates, $resolvedGroupId);
            $maxDailyWeight = DailyWeightLimits::normalizeLimit($lockedStudent->max_daily_weight ?? 2);
            $dailyWeightLimits = DailyWeightLimits::normalize(
                $lockedStudent->daily_weight_limits,
                $maxDailyWeight,
                $workingDays,
            );

            $plan = StudentMonthlyPlan::query()->create([
                'monthly_plan_id' => $monthlyPlan->id,
                'student_id' => $lockedStudent->id,
                'center_id' => $lockedStudent->center_id,
                'group_id' => $resolvedGroupId,
                'plan_id' => $lockedStudent->plan_type_id,
                'month' => $month,
                'year' => $year,
                'effective_start_date' => $planEffectiveStartDate?->toDateString(),
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
                'status' => $dates->isEmpty() || $result['generated_items_count'] > 0
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
    public function regenerateFutureForMonthlyPlan(MonthlyPlan $monthlyPlan, CarbonImmutable $fromDate, ?array $holidayDates = null): array
    {
        $this->dataScope->abortUnlessCanAccessMonthlyPlan($monthlyPlan);
        $fromDate = $fromDate->startOfDay();

        [$currentPeriodStart, $currentPeriodEnd] = $this->periodForMonthlyPlan($monthlyPlan);
        if ($fromDate->lt($currentPeriodStart) || $fromDate->gt($currentPeriodEnd)) {
            throw new InvalidArgumentException('Refresh date must be within the monthly plan period.');
        }

        if ($monthlyPlan->group_id !== null) {
            $this->syncMissingStudentsForMonthlyPlan(
                $monthlyPlan,
                $holidayDates ?? (array) $monthlyPlan->holiday_dates,
            );
        }

        return DB::transaction(function () use ($monthlyPlan, $fromDate, $holidayDates): array {
            /** @var MonthlyPlan $lockedMonthlyPlan */
            $lockedMonthlyPlan = MonthlyPlan::query()
                ->with(['center:id,working_days', 'group:id,working_days'])
                ->whereKey($monthlyPlan->id)
                ->lockForUpdate()
                ->firstOrFail();
            [$periodStart, $periodEnd] = $this->periodForMonthlyPlan($lockedMonthlyPlan);

            if ($fromDate->lt($periodStart) || $fromDate->gt($periodEnd)) {
                throw new InvalidArgumentException('Refresh date must be within the monthly plan period.');
            }

            $holidayDates = $this->normalizeHolidayDates($holidayDates ?? (array) $lockedMonthlyPlan->holiday_dates, $periodStart, $periodEnd);
            $lockedMonthlyPlan->forceFill(['holiday_dates' => $holidayDates])->save();
            $workingDays = $this->workingDaysForMonthlyPlan($lockedMonthlyPlan);

            $dates = $this->workingDatesForPeriod(
                $workingDays,
                (int) $lockedMonthlyPlan->month,
                (int) $lockedMonthlyPlan->year,
                $fromDate,
                $periodEnd,
                $holidayDates,
            );

            $studentPlanIds = StudentMonthlyPlan::query()
                ->join('students', 'student_monthly_plans.student_id', '=', 'students.id')
                ->where('student_monthly_plans.monthly_plan_id', $lockedMonthlyPlan->id)
                ->where('student_monthly_plans.status', '!=', StudentMonthlyPlan::STATUS_HISTORICAL_MARKER)
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
                    workingDays: $workingDays,
                );
            }

            $this->refreshMonthlyPlanTotals($lockedMonthlyPlan);

            return [
                'student_plans' => count($studentPlanIds),
                'generated_items' => $generatedItems,
            ];
        });
    }

    private function syncMissingStudentsForMonthlyPlan(MonthlyPlan $monthlyPlan, array $holidayDates): void
    {
        if ($monthlyPlan->group_id === null) {
            return;
        }

        [, $periodEnd] = $this->periodForMonthlyPlan($monthlyPlan);
        $students = Student::query()
            ->with(['center:id,working_days', 'groups:id,center_id,working_days', 'plan:id,name'])
            ->whereHas('groups', static fn ($query) => $query->where('groups.id', $monthlyPlan->group_id))
            ->whereDoesntHave('monthlyPlans', static fn ($query) => $query->where('monthly_plan_id', $monthlyPlan->id))
            ->tap(fn ($query) => $this->dataScope->applyStudentAccess($query, 'students'))
            ->where('is_active', Student::STATUS_ACTIVE)
            ->whereNotNull('plan_type_id')
            ->orderBy('students.id')
            ->get();

        if ($students->isEmpty()) {
            return;
        }

        $memberships = DB::table('group_student')
            ->where('group_id', $monthlyPlan->group_id)
            ->whereIn('student_id', $students->modelKeys())
            ->get(['student_id', 'created_at'])
            ->keyBy(static fn (object $membership): int => (int) $membership->student_id);

        foreach ($students as $student) {
            $membership = $memberships->get((int) $student->id);
            if ($membership === null) {
                continue;
            }

            $effectiveStartDate = $this->membershipEffectiveDate($membership->created_at);
            if ($effectiveStartDate->gt($periodEnd)) {
                continue;
            }

            $this->generateStudentForMonthlyPlan(
                student: $student,
                monthlyPlan: $monthlyPlan,
                effectiveStartDate: $effectiveStartDate,
                holidayDates: $holidayDates,
            );
        }
    }

    private function generateStudentForMonthlyPlan(
        Student $student,
        MonthlyPlan $monthlyPlan,
        CarbonImmutable $effectiveStartDate,
        ?array $holidayDates = null,
    ): ?StudentMonthlyPlan {
        if (StudentMonthlyPlan::query()
            ->where('monthly_plan_id', $monthlyPlan->id)
            ->where('student_id', $student->id)
            ->exists()) {
            return null;
        }

        [$periodStart, $periodEnd] = $this->periodForMonthlyPlan($monthlyPlan);
        if ($effectiveStartDate->gt($periodEnd)) {
            return null;
        }

        return $this->generateForStudent(
            student: $student,
            month: (int) $monthlyPlan->month,
            year: (int) $monthlyPlan->year,
            startDate: $periodStart,
            endDate: $periodEnd,
            holidayDates: $holidayDates ?? (array) $monthlyPlan->holiday_dates,
            groupId: (int) $monthlyPlan->group_id,
            effectiveStartDate: $effectiveStartDate,
        );
    }

    private function createHistoricalMembershipMarker(
        Student $student,
        MonthlyPlan $monthlyPlan,
        CarbonImmutable $membershipDate,
    ): ?StudentMonthlyPlan {
        if ($monthlyPlan->group_id === null) {
            return null;
        }

        [$periodStart, $periodEnd] = $this->periodForMonthlyPlan($monthlyPlan);
        $effectiveStartDate = $this->laterDate($periodStart, $membershipDate) ?? $periodStart;
        if ($effectiveStartDate->gt($periodEnd)) {
            return null;
        }

        return DB::transaction(function () use ($student, $monthlyPlan, $effectiveStartDate): ?StudentMonthlyPlan {
            /** @var MonthlyPlan $lockedMonthlyPlan */
            $lockedMonthlyPlan = MonthlyPlan::query()
                ->whereKey($monthlyPlan->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingPlan = StudentMonthlyPlan::query()
                ->where('student_id', $student->id)
                ->where('group_id', $lockedMonthlyPlan->group_id)
                ->where('year', $lockedMonthlyPlan->year)
                ->where('month', $lockedMonthlyPlan->month)
                ->first();
            if ($existingPlan instanceof StudentMonthlyPlan) {
                return null;
            }

            /** @var Student $lockedStudent */
            $lockedStudent = Student::query()
                ->whereKey($student->id)
                ->lockForUpdate()
                ->firstOrFail();
            if (
                (int) $lockedStudent->is_active !== Student::STATUS_ACTIVE
                || $lockedStudent->plan_type_id === null
            ) {
                return null;
            }

            $lockedStudent->loadMissing(['center:id,working_days', 'groups:id,center_id,working_days']);
            $workingDays = $this->workingDaysForStudent($lockedStudent, (int) $lockedMonthlyPlan->group_id);
            $maxDailyWeight = DailyWeightLimits::normalizeLimit($lockedStudent->max_daily_weight ?? 2);
            $dailyWeightLimits = DailyWeightLimits::normalize(
                $lockedStudent->daily_weight_limits,
                $maxDailyWeight,
                $workingDays,
            );

            $plan = StudentMonthlyPlan::query()->create([
                'monthly_plan_id' => $lockedMonthlyPlan->id,
                'student_id' => $lockedStudent->id,
                'center_id' => $lockedMonthlyPlan->center_id,
                'group_id' => $lockedMonthlyPlan->group_id,
                'plan_id' => $lockedStudent->plan_type_id,
                'month' => $lockedMonthlyPlan->month,
                'year' => $lockedMonthlyPlan->year,
                'effective_start_date' => $effectiveStartDate->toDateString(),
                'max_daily_weight' => $maxDailyWeight,
                'daily_weight_limits' => $dailyWeightLimits,
                'starts_after_plan_point_id' => null,
                'ends_at_plan_point_id' => null,
                'generated_items_count' => 0,
                'skipped_items_count' => 0,
                'status' => StudentMonthlyPlan::STATUS_HISTORICAL_MARKER,
                'generated_at' => now(),
            ]);

            $this->refreshMonthlyPlanTotals($lockedMonthlyPlan);

            return $plan->refresh()->load(['days.items.planPoint', 'student', 'plan']);
        });
    }

    private function monthlyPlanForStudent(
        Student $student,
        int $month,
        int $year,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        array $holidayDates,
        ?int $groupId,
    ): MonthlyPlan {
        return $this->monthlyPlanHeader(
            centerId: (int) $student->center_id,
            groupId: $groupId,
            month: $month,
            year: $year,
            startDate: $startDate,
            endDate: $endDate,
            holidayDates: $holidayDates,
        );
    }

    private function monthlyPlanHeader(
        int $centerId,
        ?int $groupId,
        int $month,
        int $year,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        array $holidayDates,
    ): MonthlyPlan {
        $attributes = [
            'group_id' => $groupId,
            'month' => $month,
            'year' => $year,
        ];

        if ($groupId === null) {
            $attributes['center_id'] = $centerId;
        }

        $monthlyPlan = MonthlyPlan::query()->firstOrCreate($attributes, [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'holiday_dates' => $holidayDates,
            'center_id' => $centerId,
            'admin_id' => Auth::id(),
            'generated_at' => now(),
        ]);

        $hasStoredItems = ! $monthlyPlan->wasRecentlyCreated
            && StudentMonthlyPlan::query()
                ->where('monthly_plan_id', $monthlyPlan->id)
                ->whereHas('items')
                ->exists();

        if (! $monthlyPlan->wasRecentlyCreated && ! $hasStoredItems) {
            $monthlyPlan->forceFill([
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'holiday_dates' => $holidayDates,
            ])->save();
        }

        return $monthlyPlan;
    }

    private function resolvedGroupId(Student $student, ?int $requestedGroupId): ?int
    {
        if ($requestedGroupId !== null) {
            return $requestedGroupId;
        }

        if ($student->group_id !== null && $student->groups->contains('id', (int) $student->group_id)) {
            return (int) $student->group_id;
        }

        $groupId = $student->groups->pluck('id')->sort()->first();

        return $groupId !== null ? (int) $groupId : null;
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
        $fromDate = $this->laterDate(
            $fromDate,
            $this->carbonDate($plan->effective_start_date),
        ) ?? $fromDate;
        $dates = $dates
            ->filter(static fn (CarbonImmutable $date): bool => $date->gte($fromDate))
            ->values();
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

        $emptyStatus = $dates->isEmpty()
            ? $plan->status
            : StudentMonthlyPlan::STATUS_EXHAUSTED;

        $plan->update([
            'ends_at_plan_point_id' => $lastPlanPointId,
            'generated_items_count' => $totalItems,
            'skipped_items_count' => 0,
            'status' => $totalItems > 0
                ? StudentMonthlyPlan::STATUS_GENERATED
                : $emptyStatus,
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
    private function workingDatesForMonth(
        Student $student,
        ?int $groupId,
        int $month,
        int $year,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
        array $holidayDates = [],
    ): Collection {
        return $this->workingDatesForPeriod(
            $this->workingDaysForStudent($student, $groupId),
            $month,
            $year,
            $startDate,
            $endDate,
            $holidayDates,
        );
    }

    /**
     * Prefer the selected group's schedule while retaining the center schedule for legacy groups.
     *
     * @return array<int, string>|null
     */
    private function workingDaysForStudent(Student $student, ?int $groupId): ?array
    {
        if ($groupId !== null) {
            $groupWorkingDays = $student->groups
                ->firstWhere('id', $groupId)
                ?->working_days;

            if (is_array($groupWorkingDays) && $groupWorkingDays !== []) {
                return $groupWorkingDays;
            }
        }

        return is_array($student->center?->working_days)
            ? $student->center->working_days
            : null;
    }

    /**
     * @return array<int, string>|null
     */
    private function workingDaysForMonthlyPlan(MonthlyPlan $monthlyPlan): ?array
    {
        $groupWorkingDays = $monthlyPlan->group_id !== null
            ? $monthlyPlan->group?->working_days
            : null;

        if (is_array($groupWorkingDays) && $groupWorkingDays !== []) {
            return $groupWorkingDays;
        }

        return is_array($monthlyPlan->center?->working_days)
            ? $monthlyPlan->center->working_days
            : null;
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function workingDatesForPeriod(
        mixed $workingDays,
        int $month,
        int $year,
        ?CarbonImmutable $fromDate = null,
        ?CarbonImmutable $toDate = null,
        array $holidayDates = [],
    ): Collection {
        if (! is_array($workingDays) || $workingDays === []) {
            $workingDays = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        }

        $workingDayLookup = array_fill_keys(array_map(static fn (string $day): string => strtolower($day), $workingDays), true);
        $holidayLookup = array_fill_keys(array_values($holidayDates), true);
        $dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $start = ($fromDate ?? $monthStart)->startOfDay();
        $end = ($toDate ?? $monthEnd)->startOfDay();

        if ($start->lt($monthStart)) {
            $start = $monthStart;
        }

        if ($end->gt($monthEnd)) {
            $end = $monthEnd;
        }

        $dates = collect();
        if ($start->gt($end)) {
            return $dates;
        }

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            if (isset($holidayLookup[$date->toDateString()])) {
                continue;
            }

            if (isset($workingDayLookup[$dayNames[$date->dayOfWeek]])) {
                $dates->push($date);
            }
        }

        return $dates;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodForMonth(
        int $month,
        int $year,
        ?CarbonImmutable $startDate = null,
        ?CarbonImmutable $endDate = null,
    ): array {
        $monthStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->endOfMonth()->startOfDay();
        $startDate = ($startDate ?? $monthStart)->startOfDay();
        $endDate = ($endDate ?? $monthEnd)->startOfDay();

        foreach ([$startDate, $endDate] as $date) {
            if ((int) $date->month !== $month || (int) $date->year !== $year) {
                throw new InvalidArgumentException('Monthly plan period must be within the selected month and year.');
            }
        }

        if ($startDate->gt($endDate)) {
            throw new InvalidArgumentException('Monthly plan start date must be before or equal to the end date.');
        }

        return [$startDate, $endDate];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodForMonthlyPlan(MonthlyPlan $monthlyPlan): array
    {
        return $this->periodForMonth(
            (int) $monthlyPlan->month,
            (int) $monthlyPlan->year,
            $this->carbonDate($monthlyPlan->start_date),
            $this->carbonDate($monthlyPlan->end_date),
        );
    }

    private function monthlyPlanCoveringDate(int $groupId, CarbonImmutable $date): ?MonthlyPlan
    {
        $dateString = $date->toDateString();

        return MonthlyPlan::query()
            ->where('group_id', $groupId)
            ->where(function ($coverage) use ($date, $dateString): void {
                $coverage
                    ->where(function ($bounded) use ($dateString): void {
                        $bounded
                            ->whereNotNull('start_date')
                            ->whereNotNull('end_date')
                            ->whereDate('start_date', '<=', $dateString)
                            ->whereDate('end_date', '>=', $dateString);
                    })
                    ->orWhere(function ($legacy) use ($date): void {
                        $legacy
                            ->where(function ($missingBoundary): void {
                                $missingBoundary
                                    ->whereNull('start_date')
                                    ->orWhereNull('end_date');
                            })
                            ->where('year', $date->year)
                            ->where('month', $date->month);
                    });
            })
            ->first();
    }

    private function membershipEffectiveDate(mixed $createdAt): CarbonImmutable
    {
        $timezone = $this->systemTimezone();
        if ($createdAt instanceof DateTimeInterface) {
            return CarbonImmutable::instance($createdAt)
                ->setTimezone($timezone)
                ->startOfDay();
        }

        if (filled($createdAt)) {
            return CarbonImmutable::parse((string) $createdAt, (string) config('app.timezone', 'UTC'))
                ->setTimezone($timezone)
                ->startOfDay();
        }

        return CarbonImmutable::now($timezone)->startOfDay();
    }

    private function systemTimezone(): string
    {
        return (string) ($this->settings->get()['timezone'] ?? 'Asia/Amman');
    }

    private function laterDate(?CarbonImmutable $first, ?CarbonImmutable $second): ?CarbonImmutable
    {
        if ($first === null) {
            return $second;
        }

        if ($second === null) {
            return $first;
        }

        return $first->gte($second) ? $first : $second;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeHolidayDates(array $holidayDates, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $dates = [];

        foreach ($holidayDates as $holidayDate) {
            $date = $this->carbonDate($holidayDate);
            if ($date === null || $date->lt($startDate) || $date->gt($endDate)) {
                continue;
            }

            $dates[$date->toDateString()] = $date->toDateString();
        }

        ksort($dates);

        return array_values($dates);
    }

    private function carbonDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value->startOfDay();
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (blank($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->startOfDay();
    }
}

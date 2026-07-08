<?php

use App\Models\Center;
use App\Models\Group;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentMonthlyPlan;
use App\Models\StudentMonthlyPlanItem;
use App\Models\User;
use App\Services\Admin\StudentMonthlyPlanGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function monthlyPlanFixture(float $maxDailyWeight = 2): array
{
    $center = Center::factory()->create([
        'working_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
    ]);
    $group = Group::factory()->create(['center_id' => $center->id]);
    $plan = Plan::factory()->create();
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'max_daily_weight' => $maxDailyWeight,
            'current_plan_point_id' => null,
            'monthly_plan_cursor_point_id' => null,
        ]);

    return [$center, $group, $plan, $student];
}

function createPlanPoint(Plan $plan, string $name, int $sortOrder, array $extra = []): PlanPoint
{
    return PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => $sortOrder,
        'name' => $name,
        ...$extra,
    ]);
}

test('weight zero item is attached and does not open a new day', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 1);
    createPlanPoint($plan, 'تسميع صفحة 1', 1);
    $freePoint = createPlanPoint($plan, 'دوري', 2, ['weight' => 0]);
    createPlanPoint($plan, 'تسميع صفحة 2', 3);

    $monthlyPlan = app(StudentMonthlyPlanGenerator::class)->generateForStudent($student, 6, 2026);

    expect($monthlyPlan)->not->toBeNull()
        ->and($monthlyPlan->days()->count())->toBe(2);

    $attached = StudentMonthlyPlanItem::query()
        ->where('plan_point_id', $freePoint->id)
        ->firstOrFail();

    expect($attached->status)->toBe(StudentMonthlyPlanItem::STATUS_ATTACHED)
        ->and((float) $attached->weight)->toBe(0.0)
        ->and($attached->day->items()->count())->toBe(2);
});

test('zero weight item after the last generated item is attached without storing the next weighted item', function () {
    [$center, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 1);
    $center->update(['working_days' => ['sunday']]);

    createPlanPoint($plan, 'تسميع 1', 1, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع 2', 2, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع 3', 3, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع 4', 4, ['weight' => 1]);
    $trailingReview = createPlanPoint($plan, 'تسميع (2 - 4)', 5, ['weight' => 0]);
    $nextMonthPoint = createPlanPoint($plan, 'تسميع 5', 6, ['weight' => 1]);

    $monthlyPlan = app(StudentMonthlyPlanGenerator::class)->generateForStudent($student, 6, 2026);
    $lastDay = $monthlyPlan->days()->orderByDesc('date')->firstOrFail();
    $reviewItem = StudentMonthlyPlanItem::query()
        ->where('plan_point_id', $trailingReview->id)
        ->firstOrFail();

    expect($lastDay->items()->count())->toBe(2)
        ->and($reviewItem->status)->toBe(StudentMonthlyPlanItem::STATUS_ATTACHED)
        ->and($reviewItem->student_monthly_plan_day_id)->toBe($lastDay->id)
        ->and(StudentMonthlyPlanItem::query()->where('plan_point_id', $nextMonthPoint->id)->exists())->toBeFalse();
});

test('weight one items are combined until the daily limit', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 2);
    createPlanPoint($plan, 'تسميع صفحة 1', 1);
    createPlanPoint($plan, 'تسميع صفحة 2', 2);
    createPlanPoint($plan, 'تسميع صفحة 3', 3);

    $monthlyPlan = app(StudentMonthlyPlanGenerator::class)->generateForStudent($student, 6, 2026);
    $days = $monthlyPlan->days()->withCount('items')->orderBy('date')->get();

    expect($days)->toHaveCount(2)
        ->and($days[0]->items_count)->toBe(2)
        ->and((float) $days[0]->total_weight)->toBe(2.0)
        ->and($days[1]->items_count)->toBe(1);
});

test('weekday daily limits control monthly plan distribution', function () {
    [$center, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 2);
    $center->update(['working_days' => ['monday', 'tuesday']]);
    $student->update([
        'daily_weight_limits' => [
            'monday' => 1,
            'tuesday' => 2,
        ],
    ]);

    createPlanPoint($plan, 'تسميع صفحة 1', 1, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع صفحة 2', 2, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع صفحة 3', 3, ['weight' => 1]);

    $monthlyPlan = app(StudentMonthlyPlanGenerator::class)->generateForStudent($student->refresh(), 6, 2026);
    $days = $monthlyPlan->days()->withCount('items')->orderBy('date')->get();

    expect($monthlyPlan->daily_weight_limits)->toBe([
        'monday' => 1,
        'tuesday' => 2,
    ])
        ->and($days)->toHaveCount(2)
        ->and($days[0]->date->format('Y-m-d'))->toBe('2026-06-01')
        ->and($days[0]->items_count)->toBe(1)
        ->and((float) $days[0]->total_weight)->toBe(1.0)
        ->and($days[1]->date->format('Y-m-d'))->toBe('2026-06-02')
        ->and($days[1]->items_count)->toBe(2)
        ->and((float) $days[1]->total_weight)->toBe(2.0);
});

test('monthly generation respects the selected start and end dates', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 1);

    createPlanPoint($plan, 'تسميع 1', 1, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع 2', 2, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع 3', 3, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع 4', 4, ['weight' => 1]);

    $studentPlan = app(StudentMonthlyPlanGenerator::class)->generateForStudent(
        student: $student,
        month: 6,
        year: 2026,
        startDate: CarbonImmutable::create(2026, 6, 10),
        endDate: CarbonImmutable::create(2026, 6, 12),
    );

    $monthlyPlan = MonthlyPlan::query()->findOrFail($studentPlan->monthly_plan_id);
    $days = $studentPlan->days()->orderBy('date')->get();

    expect($monthlyPlan->start_date->toDateString())->toBe('2026-06-10')
        ->and($monthlyPlan->end_date->toDateString())->toBe('2026-06-12')
        ->and($days->pluck('date')->map(fn ($date) => $date->format('Y-m-d'))->all())->toBe([
            '2026-06-10',
            '2026-06-11',
            '2026-06-12',
        ])
        ->and($studentPlan->items()->count())->toBe(3)
        ->and(StudentMonthlyPlanItem::query()->where('plan_point_id', $plan->points()->orderByDesc('sort_order')->firstOrFail()->id)->exists())->toBeFalse();
});

test('monthly generation skips selected holiday dates', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 1);

    createPlanPoint($plan, 'تسميع 1', 1, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع 2', 2, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع 3', 3, ['weight' => 1]);

    $studentPlan = app(StudentMonthlyPlanGenerator::class)->generateForStudent(
        student: $student,
        month: 6,
        year: 2026,
        startDate: CarbonImmutable::create(2026, 6, 10),
        endDate: CarbonImmutable::create(2026, 6, 12),
        holidayDates: ['2026-06-11'],
    );

    $monthlyPlan = MonthlyPlan::query()->findOrFail($studentPlan->monthly_plan_id);
    $days = $studentPlan->days()->orderBy('date')->get();

    expect($monthlyPlan->holiday_dates)->toBe(['2026-06-11'])
        ->and($days->pluck('date')->map(fn ($date) => $date->format('Y-m-d'))->all())->toBe([
            '2026-06-10',
            '2026-06-12',
        ])
        ->and($studentPlan->items()->count())->toBe(2);
});

test('regenerating a saved monthly plan from a date leaves previous days untouched', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 1);

    $first = createPlanPoint($plan, 'تسميع 1', 1, ['weight' => 1]);
    $second = createPlanPoint($plan, 'تسميع 2', 2, ['weight' => 1]);
    $third = createPlanPoint($plan, 'تسميع 3', 3, ['weight' => 1]);
    $fourth = createPlanPoint($plan, 'تسميع 4', 4, ['weight' => 1]);
    $fifth = createPlanPoint($plan, 'تسميع 5', 5, ['weight' => 1]);

    $studentPlan = app(StudentMonthlyPlanGenerator::class)->generateForStudent($student, 6, 2026);
    $monthlyPlan = MonthlyPlan::query()->findOrFail($studentPlan->monthly_plan_id);
    $preservedItems = $studentPlan->items()
        ->join('student_monthly_plan_days', 'student_monthly_plan_items.student_monthly_plan_day_id', '=', 'student_monthly_plan_days.id')
        ->whereDate('student_monthly_plan_days.date', '<', '2026-06-03')
        ->orderBy('student_monthly_plan_items.sort_order')
        ->pluck('student_monthly_plan_items.id', 'student_monthly_plan_items.plan_point_id')
        ->all();

    $student->update(['max_daily_weight' => 2]);

    $result = app(StudentMonthlyPlanGenerator::class)
        ->regenerateFutureForMonthlyPlan($monthlyPlan, CarbonImmutable::create(2026, 6, 3));

    $studentPlan->refresh();
    $days = $studentPlan->days()->with('items')->orderBy('date')->get();

    expect($result['student_plans'])->toBe(1)
        ->and($preservedItems)->toHaveKeys([$first->id, $second->id])
        ->and($days->pluck('date')->map(fn ($date) => $date->format('Y-m-d'))->all())->toBe([
            '2026-06-01',
            '2026-06-02',
            '2026-06-03',
            '2026-06-04',
        ])
        ->and($days[0]->items->pluck('plan_point_id')->all())->toBe([$first->id])
        ->and($days[1]->items->pluck('plan_point_id')->all())->toBe([$second->id])
        ->and($days[0]->items->first()->id)->toBe($preservedItems[$first->id])
        ->and($days[1]->items->first()->id)->toBe($preservedItems[$second->id])
        ->and($days[2]->items->pluck('plan_point_id')->all())->toBe([$third->id, $fourth->id])
        ->and((int) $days[2]->daily_weight_limit)->toBe(2)
        ->and($days[3]->items->pluck('plan_point_id')->all())->toBe([$fifth->id]);
});

test('standalone item is placed by itself', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 5);
    createPlanPoint($plan, 'تسميع صفحة 1', 1);
    $standalone = createPlanPoint($plan, 'ثلاثة أجزاء', 2, ['weight' => 3, 'is_standalone' => true]);
    createPlanPoint($plan, 'تسميع صفحة 2', 3);

    app(StudentMonthlyPlanGenerator::class)->generateForStudent($student, 6, 2026);

    $standaloneItem = StudentMonthlyPlanItem::query()
        ->where('plan_point_id', $standalone->id)
        ->firstOrFail();

    expect($standaloneItem->is_standalone)->toBeTrue()
        ->and($standaloneItem->day->items()->count())->toBe(1);
});

test('large surah text is not treated as standalone unless stored on the plan point', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 2);
    $largeSurah = createPlanPoint($plan, 'سورة البقرة', 1, [
        'surah_name' => 'البقرة',
        'weight' => 1,
        'is_standalone' => false,
    ]);
    createPlanPoint($plan, 'تسميع صفحة 1', 2, ['weight' => 1]);

    app(StudentMonthlyPlanGenerator::class)->generateForStudent($student, 6, 2026);

    $largeSurahItem = StudentMonthlyPlanItem::query()
        ->where('plan_point_id', $largeSurah->id)
        ->firstOrFail();

    expect($largeSurahItem->is_standalone)->toBeFalse()
        ->and($largeSurahItem->day->items()->count())->toBe(2);
});

test('monthly plans for two students do not mix their data', function () {
    [$center, $group, $plan, $firstStudent] = monthlyPlanFixture(maxDailyWeight: 2);
    $secondStudent = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'max_daily_weight' => 2,
        ]);
    createPlanPoint($plan, 'تسميع صفحة 1', 1);
    createPlanPoint($plan, 'تسميع صفحة 2', 2);

    $result = app(StudentMonthlyPlanGenerator::class)->generateForGroup($group, 6, 2026);

    expect($result['generated'])->toBe(2)
        ->and(MonthlyPlan::query()->count())->toBe(1)
        ->and(StudentMonthlyPlan::query()->count())->toBe(2);

    $firstPlan = StudentMonthlyPlan::query()->where('student_id', $firstStudent->id)->firstOrFail();
    $secondPlan = StudentMonthlyPlan::query()->where('student_id', $secondStudent->id)->firstOrFail();

    expect($firstPlan->monthly_plan_id)->toBe($secondPlan->monthly_plan_id)
        ->and($firstPlan->items()->where('student_id', $secondStudent->id)->exists())->toBeFalse()
        ->and($secondPlan->items()->where('student_id', $firstStudent->id)->exists())->toBeFalse();
});

test('saved monthly plan records include the public monthly plan report link', function () {
    [, $group, $plan] = monthlyPlanFixture(maxDailyWeight: 2);
    createPlanPoint($plan, 'تسميع صفحة 1', 1);

    app(StudentMonthlyPlanGenerator::class)->generateForGroup($group, 6, 2026);
    $monthlyPlan = MonthlyPlan::query()->firstOrFail();

    $adminRole = Role::query()->create([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);
    $admin = User::factory()->create();
    $admin->assignRole($adminRole);

    $this->actingAs($admin, 'web')
        ->getJson('/admin/monthly-plans/records')
        ->assertOk()
        ->assertJsonPath(
            'data.0.public_report_url',
            route('monthly-plans.report', ['publicId' => $monthlyPlan->ulid], false),
        );
});

test('regenerating a monthly plan does not duplicate data', function () {
    [, $group, $plan, $student] = monthlyPlanFixture(maxDailyWeight: 2);
    createPlanPoint($plan, 'تسميع صفحة 1', 1);
    createPlanPoint($plan, 'تسميع صفحة 2', 2);

    app(StudentMonthlyPlanGenerator::class)->generateForGroup($group, 6, 2026);
    app(StudentMonthlyPlanGenerator::class)->generateForGroup($group, 6, 2026);

    expect(StudentMonthlyPlan::query()->where('student_id', $student->id)->where('year', 2026)->where('month', 6)->count())->toBe(1)
        ->and(StudentMonthlyPlanItem::query()->where('student_id', $student->id)->count())->toBe(2);
});

test('monthly generation starts from current plan point and ignores old monthly cursor', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 2);
    $first = createPlanPoint($plan, 'تسميع 1', 1);
    $second = createPlanPoint($plan, 'تسميع 2', 2);
    $third = createPlanPoint($plan, 'تسميع 3', 3);
    createPlanPoint($plan, 'تسميع 4', 4);

    $student->update([
        'current_plan_point_id' => $first->id,
        'monthly_plan_cursor_point_id' => $third->id,
    ]);

    $monthlyPlan = app(StudentMonthlyPlanGenerator::class)->generateForStudent($student->refresh(), 6, 2026);
    $firstGeneratedItem = $monthlyPlan->items()->orderBy('sort_order')->firstOrFail();

    expect($firstGeneratedItem->plan_point_id)->toBe($second->id)
        ->and($student->refresh()->monthly_plan_cursor_point_id)->toBe($third->id);
});

test('generation uses stored plan point weight and ignores text rules', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 2);
    $reviewPoint = createPlanPoint($plan, 'دوري', 1, ['weight' => 1]);
    createPlanPoint($plan, 'تسميع صفحة 1', 2, ['weight' => 1]);

    app(StudentMonthlyPlanGenerator::class)->generateForStudent($student, 6, 2026);

    $reviewItem = StudentMonthlyPlanItem::query()
        ->where('plan_point_id', $reviewPoint->id)
        ->firstOrFail();

    expect((float) $reviewItem->weight)->toBe(1.0)
        ->and($reviewItem->status)->toBe(StudentMonthlyPlanItem::STATUS_GENERATED)
        ->and($reviewItem->day->items()->count())->toBe(2);
});

test('center monthly generation stores one plan per student per month without duplicates', function () {
    [$center, $group, $plan, $firstStudent] = monthlyPlanFixture(maxDailyWeight: 2);
    $secondGroup = Group::factory()->create(['center_id' => $center->id]);
    $secondStudent = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $secondGroup->id,
            'plan_type_id' => $plan->id,
            'max_daily_weight' => 2,
        ]);

    createPlanPoint($plan, 'تسميع صفحة 1', 1);
    createPlanPoint($plan, 'تسميع صفحة 2', 2);

    app(StudentMonthlyPlanGenerator::class)->generateForCenter($center, 6, 2026);
    app(StudentMonthlyPlanGenerator::class)->generateForCenter($center, 6, 2026);

    expect(MonthlyPlan::query()->where('center_id', $center->id)->where('year', 2026)->where('month', 6)->count())->toBe(2)
        ->and(StudentMonthlyPlan::query()->where('center_id', $center->id)->where('year', 2026)->where('month', 6)->count())->toBe(2)
        ->and(StudentMonthlyPlan::query()->where('student_id', $firstStudent->id)->where('year', 2026)->where('month', 6)->count())->toBe(1)
        ->and(StudentMonthlyPlan::query()->where('student_id', $secondStudent->id)->where('year', 2026)->where('month', 6)->count())->toBe(1)
        ->and(StudentMonthlyPlanItem::query()->where('student_id', $firstStudent->id)->count())->toBe(2)
        ->and(StudentMonthlyPlanItem::query()->where('student_id', $secondStudent->id)->count())->toBe(2);
});

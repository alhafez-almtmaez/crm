<?php

use App\Models\Center;
use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentMonthlyPlan;
use App\Models\StudentMonthlyPlanItem;
use App\Services\Admin\StudentMonthlyPlanGenerator;
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
    $freePoint = createPlanPoint($plan, 'دوري', 2);
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

test('standalone item is placed by itself', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 5);
    createPlanPoint($plan, 'تسميع صفحة 1', 1);
    $standalone = createPlanPoint($plan, 'ثلاثة أجزاء', 2);
    createPlanPoint($plan, 'تسميع صفحة 2', 3);

    app(StudentMonthlyPlanGenerator::class)->generateForStudent($student, 6, 2026);

    $standaloneItem = StudentMonthlyPlanItem::query()
        ->where('plan_point_id', $standalone->id)
        ->firstOrFail();

    expect($standaloneItem->is_standalone)->toBeTrue()
        ->and($standaloneItem->day->items()->count())->toBe(1);
});

test('large surah over fifteen pages is treated as standalone', function () {
    [, , $plan, $student] = monthlyPlanFixture(maxDailyWeight: 5);
    $largeSurah = createPlanPoint($plan, 'سورة البقرة', 1, ['surah_name' => 'البقرة']);
    createPlanPoint($plan, 'تسميع صفحة 1', 2);

    app(StudentMonthlyPlanGenerator::class)->generateForStudent($student, 6, 2026);

    $largeSurahItem = StudentMonthlyPlanItem::query()
        ->where('plan_point_id', $largeSurah->id)
        ->firstOrFail();

    expect($largeSurahItem->is_standalone)->toBeTrue()
        ->and($largeSurahItem->day->items()->count())->toBe(1);
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
        ->and(StudentMonthlyPlan::query()->count())->toBe(2);

    $firstPlan = StudentMonthlyPlan::query()->where('student_id', $firstStudent->id)->firstOrFail();
    $secondPlan = StudentMonthlyPlan::query()->where('student_id', $secondStudent->id)->firstOrFail();

    expect($firstPlan->items()->where('student_id', $secondStudent->id)->exists())->toBeFalse()
        ->and($secondPlan->items()->where('student_id', $firstStudent->id)->exists())->toBeFalse();
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

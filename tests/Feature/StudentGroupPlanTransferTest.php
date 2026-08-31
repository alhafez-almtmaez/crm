<?php

use App\Models\Center;
use App\Models\Group;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentMonthlyPlan;
use App\Models\User;
use App\Services\Admin\StudentMonthlyPlanGenerator;
use App\Services\Admin\StudentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{
 *     user: User,
 *     center: Center,
 *     source_group: Group,
 *     destination_group: Group,
 *     plan: Plan,
 *     student: Student,
 *     source_student_plan: StudentMonthlyPlan,
 *     destination_monthly_plan: MonthlyPlan
 * }
 */
function studentGroupTransferFixture(): array
{
    Role::findOrCreate('admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('admin');
    test()->actingAs($user, 'web');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-01 12:00:00', 'Asia/Amman'));
    $center = Center::factory()->create();
    $sourceGroup = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => ['wednesday', 'saturday'],
    ]);
    $destinationGroup = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => ['wednesday', 'saturday'],
    ]);
    $plan = Plan::factory()->create();
    foreach (range(1, 12) as $sortOrder) {
        PlanPoint::factory()->create([
            'plan_id' => $plan->id,
            'sort_order' => $sortOrder,
            'name' => "Point {$sortOrder}",
            'weight' => 1,
        ]);
    }

    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $sourceGroup->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => null,
        'monthly_plan_cursor_point_id' => null,
        'max_daily_weight' => 1,
        'daily_weight_limits' => ['wednesday' => 1, 'saturday' => 1],
        'admin_id' => $user->id,
    ]);
    $student->groups()->sync([$sourceGroup->id]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-20 12:00:00', 'Asia/Amman'));
    $generator = app(StudentMonthlyPlanGenerator::class);
    $generator->generateForGroup(
        group: $sourceGroup,
        month: 8,
        year: 2026,
        startDate: CarbonImmutable::parse('2026-08-20'),
        endDate: CarbonImmutable::parse('2026-08-31'),
    );
    $destinationResult = $generator->generateForGroup(
        group: $destinationGroup,
        month: 8,
        year: 2026,
        startDate: CarbonImmutable::parse('2026-08-20'),
        endDate: CarbonImmutable::parse('2026-08-31'),
    );

    return [
        'user' => $user,
        'center' => $center,
        'source_group' => $sourceGroup,
        'destination_group' => $destinationGroup,
        'plan' => $plan,
        'student' => $student,
        'source_student_plan' => StudentMonthlyPlan::query()
            ->where('student_id', $student->id)
            ->where('group_id', $sourceGroup->id)
            ->firstOrFail(),
        'destination_monthly_plan' => MonthlyPlan::query()
            ->findOrFail($destinationResult['monthly_plan_ids'][0]),
    ];
}

test('moving a student automatically joins the destination plan from the transfer date', function () {
    $fixture = studentGroupTransferFixture();
    $student = $fixture['student'];

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-26 13:00:00', 'Asia/Amman'));
    app(StudentService::class)->update($student, [
        'first_name' => $student->first_name,
        'second_name' => $student->second_name,
        'middle_name' => $student->middle_name,
        'last_name' => $student->last_name,
        'parent_phone_number' => $student->parent_phone_number,
        'phone_number' => $student->phone_number,
        'email' => $student->email,
        'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
        'center_id' => $fixture['center']->id,
        'group_ids' => [$fixture['destination_group']->id],
        'plan_type_id' => $fixture['plan']->id,
        'current_plan_point_id' => $student->current_plan_point_id,
        'max_daily_weight' => 1,
        'daily_weight_limits' => ['wednesday' => 1, 'saturday' => 1],
        'points_balance' => $student->points_balance,
        'admin_id' => $fixture['user']->id,
        'is_active' => Student::STATUS_ACTIVE,
    ]);

    $destinationPlan = StudentMonthlyPlan::query()
        ->where('student_id', $student->id)
        ->where('group_id', $fixture['destination_group']->id)
        ->firstOrFail();
    $dates = $destinationPlan->days()
        ->orderBy('date')
        ->pluck('date')
        ->map(static fn ($date): string => CarbonImmutable::parse($date)->toDateString());

    expect($destinationPlan->monthly_plan_id)->toBe($fixture['destination_monthly_plan']->id)
        ->and($destinationPlan->effective_start_date?->toDateString())->toBe('2026-08-26')
        ->and($dates)->not->toBeEmpty()
        ->and($dates->first())->toBe('2026-08-26')
        ->and($dates->every(static fn (string $date): bool => $date >= '2026-08-26'))->toBeTrue()
        ->and(StudentMonthlyPlan::query()->whereKey($fixture['source_student_plan']->id)->exists())->toBeTrue()
        ->and($student->fresh()->groups()->pluck('groups.id')->all())
        ->toBe([$fixture['destination_group']->id]);
});

test('membership sync command repairs students transferred after a plan was created', function () {
    $fixture = studentGroupTransferFixture();
    $student = $fixture['student'];

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-26 13:00:00', 'Asia/Amman'));
    $student->forceFill([
        'group_id' => $fixture['destination_group']->id,
        'center_id' => $fixture['center']->id,
    ])->save();
    $student->groups()->sync([$fixture['destination_group']->id]);

    expect(StudentMonthlyPlan::query()
        ->where('student_id', $student->id)
        ->where('group_id', $fixture['destination_group']->id)
        ->exists())->toBeFalse();

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 09:00:00', 'Asia/Amman'));
    $this->artisan('monthly-plans:sync-memberships', [
        '--group' => $fixture['destination_group']->id,
    ])
        ->expectsOutput('Student plans generated: 1')
        ->assertSuccessful();

    $destinationPlan = StudentMonthlyPlan::query()
        ->where('student_id', $student->id)
        ->where('group_id', $fixture['destination_group']->id)
        ->firstOrFail();

    expect($destinationPlan->effective_start_date?->toDateString())->toBe('2026-08-26')
        ->and($destinationPlan->status)->toBe(StudentMonthlyPlan::STATUS_HISTORICAL_MARKER)
        ->and($destinationPlan->days()->count())->toBe(0)
        ->and($destinationPlan->items()->count())->toBe(0)
        ->and(StudentMonthlyPlan::query()->whereKey($fixture['source_student_plan']->id)->exists())->toBeTrue();

    $this->artisan('monthly-plans:sync-memberships', [
        '--group' => $fixture['destination_group']->id,
    ])
        ->expectsOutput('Student plans generated: 0')
        ->assertSuccessful();

    $refreshResult = app(StudentMonthlyPlanGenerator::class)->regenerateFutureForMonthlyPlan(
        $fixture['destination_monthly_plan'],
        CarbonImmutable::parse('2026-08-26'),
    );

    $destinationPlan->refresh();
    expect($refreshResult['student_plans'])->toBe(0)
        ->and($destinationPlan->status)->toBe(StudentMonthlyPlan::STATUS_HISTORICAL_MARKER)
        ->and($destinationPlan->days()->count())->toBe(0)
        ->and($destinationPlan->items()->count())->toBe(0);
});

test('membership repair checks the transfer month and the current month without future plans', function () {
    $fixture = studentGroupTransferFixture();
    $student = $fixture['student'];

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-26 13:00:00', 'Asia/Amman'));
    $student->forceFill([
        'group_id' => $fixture['destination_group']->id,
        'center_id' => $fixture['center']->id,
    ])->save();
    $student->groups()->sync([$fixture['destination_group']->id]);

    $septemberPlan = MonthlyPlan::query()->create([
        'month' => 9,
        'year' => 2026,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'holiday_dates' => [],
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['destination_group']->id,
        'admin_id' => $fixture['user']->id,
        'generated_at' => now(),
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-01 09:00:00', 'Asia/Amman'));
    $this->artisan('monthly-plans:sync-memberships', [
        '--group' => $fixture['destination_group']->id,
    ])
        ->expectsOutput('Student plans generated: 2')
        ->assertSuccessful();

    $plans = StudentMonthlyPlan::query()
        ->where('student_id', $student->id)
        ->where('group_id', $fixture['destination_group']->id)
        ->orderBy('month')
        ->get();
    $septemberStudentPlan = StudentMonthlyPlan::query()
        ->where('monthly_plan_id', $septemberPlan->id)
        ->where('student_id', $student->id)
        ->firstOrFail();
    $firstSeptemberDate = $septemberStudentPlan->days()->orderBy('date')->value('date');

    expect($plans)->toHaveCount(2)
        ->and($plans->first()->days()->count())->toBe(0)
        ->and($septemberStudentPlan)->toBeInstanceOf(StudentMonthlyPlan::class)
        ->and($septemberStudentPlan->effective_start_date?->toDateString())->toBe('2026-09-01')
        ->and(CarbonImmutable::parse((string) $firstSeptemberDate)->toDateString())->toBe('2026-09-02');

    $this->artisan('monthly-plans:sync-memberships', [
        '--group' => $fixture['destination_group']->id,
    ])
        ->expectsOutput('Student plans generated: 0')
        ->assertSuccessful();
});

test('membership repair for an active plan schedules only from the repair date onward', function () {
    $fixture = studentGroupTransferFixture();
    $student = $fixture['student'];

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-26 13:00:00', 'Asia/Amman'));
    $student->forceFill([
        'group_id' => $fixture['destination_group']->id,
        'center_id' => $fixture['center']->id,
    ])->save();
    $student->groups()->sync([$fixture['destination_group']->id]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-28 09:00:00', 'Asia/Amman'));
    $this->artisan('monthly-plans:sync-memberships', [
        '--group' => $fixture['destination_group']->id,
    ])->assertSuccessful();

    $destinationPlan = StudentMonthlyPlan::query()
        ->where('student_id', $student->id)
        ->where('group_id', $fixture['destination_group']->id)
        ->firstOrFail();
    $dates = $destinationPlan->days()
        ->orderBy('date')
        ->pluck('date')
        ->map(static fn ($date): string => CarbonImmutable::parse($date)->toDateString())
        ->all();

    expect($destinationPlan->effective_start_date?->toDateString())->toBe('2026-08-28')
        ->and($dates)->toBe(['2026-08-29'])
        ->and(collect($dates)->every(static fn (string $date): bool => $date >= '2026-08-28'))->toBeTrue();
});

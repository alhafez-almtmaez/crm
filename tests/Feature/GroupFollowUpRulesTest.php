<?php

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkStudent;
use App\Models\MonthlyPlan;
use App\Models\Student;
use App\Models\User;
use App\Services\Auth\PermissionSyncService;
use App\Support\GroupMonthlyPlanCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionSyncService::class)->sync();

    $this->rulesAdmin = User::factory()->create();
    $this->rulesAdmin->assignRole('admin');
    $this->actingAs($this->rulesAdmin, 'web');
});

test('new evaluation homework and unified follow-up require a covering monthly group plan', function () {
    [$center, $group, $student] = groupFollowUpRulesFixture(['sunday']);

    $evaluationPayload = groupFollowUpRulesEvaluationPayload($center, $group, $student);
    $homeworkPayload = groupFollowUpRulesHomeworkPayload($center, $group, $student);

    $this->post('/admin/evaluations', $evaluationPayload)->assertSessionHasErrors('date');
    $this->post('/admin/homeworks', $homeworkPayload)->assertSessionHasErrors('date');
    $this->post('/admin/daily-follow-up/save', [
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
        'evaluation' => [
            'evaluation_type' => $evaluationPayload['evaluation_type'],
            'items' => $evaluationPayload['items'],
        ],
        'homework' => ['items' => $homeworkPayload['items']],
    ])->assertSessionHasErrors('date');

    expect(Evaluation::query()->count())->toBe(0)
        ->and(Homework::query()->count())->toBe(0);
});

test('new follow-up is rejected outside group working days even when a plan covers the date', function () {
    [$center, $group, $student] = groupFollowUpRulesFixture(['sunday']);
    createGroupFollowUpRulesMonthlyPlan($group, '2026-08-01', '2026-08-31');

    $evaluationPayload = groupFollowUpRulesEvaluationPayload($center, $group, $student, '2026-08-31');
    $homeworkPayload = groupFollowUpRulesHomeworkPayload($center, $group, $student, '2026-08-31');

    $this->post('/admin/evaluations', $evaluationPayload)->assertSessionHasErrors('date');
    $this->post('/admin/homeworks', $homeworkPayload)->assertSessionHasErrors('date');
    $this->post('/admin/daily-follow-up/save', [
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-08-31',
        'evaluation' => [
            'evaluation_type' => $evaluationPayload['evaluation_type'],
            'items' => $evaluationPayload['items'],
        ],
        'homework' => ['items' => $homeworkPayload['items']],
    ])->assertSessionHasErrors('date');

    expect(Evaluation::query()->count())->toBe(0)
        ->and(Homework::query()->count())->toBe(0);
});

test('historical unified records remain editable without a current schedule or monthly plan', function () {
    [$center, $group, $student] = groupFollowUpRulesFixture([]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $student->id,
        'user_id' => $student->id,
    ]);
    $homework = Homework::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
    ]);
    HomeworkStudent::query()->create([
        'homework_id' => $homework->id,
        'student_id' => $student->id,
        'plan_id' => $student->plan_type_id,
        'points_balance_before' => 0,
        'points_adjustment' => 0,
        'points_balance_after' => 0,
    ]);

    $this->post('/admin/daily-follow-up/save', [
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
        'evaluation' => [
            'evaluation_type' => Evaluation::TYPE_ALHIFZ,
            'items' => [[
                'student_id' => $student->id,
                'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
                'alhifz' => 7,
                'warud' => 8,
                'akhlaqi' => 9,
                'tajwid' => null,
                'note' => 'historical update',
            ]],
        ],
        'homework' => [
            'items' => [[
                'student_id' => $student->id,
                'points_adjustment' => 2,
                'points' => [],
            ]],
        ],
    ])->assertSessionHasNoErrors();

    expect(Evaluation::query()->count())->toBe(1)
        ->and(Homework::query()->count())->toBe(1)
        ->and(EvaluationStudent::query()->where('evaluation_id', $evaluation->id)->value('alhifz'))->toBe(7)
        ->and(HomeworkStudent::query()->where('homework_id', $homework->id)->value('points_adjustment'))->toBe(2);
});

test('monthly plan creation requires selecting a group with configured working days', function () {
    $center = Center::factory()->create();

    $this->post('/admin/monthly-plans', groupFollowUpRulesMonthlyPlanPayload($center))
        ->assertSessionHasErrors('group_id');

    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => null,
    ]);

    $this->post('/admin/monthly-plans', [
        ...groupFollowUpRulesMonthlyPlanPayload($center),
        'group_id' => $group->id,
    ])->assertSessionHasErrors('group_id');

    expect(MonthlyPlan::query()->count())->toBe(0);
});

test('monthly plan coverage honors explicit periods and legacy month records', function () {
    [, $group] = groupFollowUpRulesFixture(['sunday']);
    createGroupFollowUpRulesMonthlyPlan($group, '2026-08-20', '2026-08-31');

    expect(GroupMonthlyPlanCoverage::exists($group->id, '2026-08-19'))->toBeFalse()
        ->and(GroupMonthlyPlanCoverage::exists($group->id, '2026-08-20'))->toBeTrue()
        ->and(GroupMonthlyPlanCoverage::exists($group->id, '2026-08-31'))->toBeTrue();

    MonthlyPlan::query()->create([
        'month' => 9,
        'year' => 2026,
        'start_date' => null,
        'end_date' => null,
        'center_id' => $group->center_id,
        'group_id' => $group->id,
    ]);

    expect(GroupMonthlyPlanCoverage::exists($group->id, '2026-09-15'))->toBeTrue();
});

/**
 * @param  array<int, string>  $workingDays
 * @return array{0: Center, 1: Group, 2: Student}
 */
function groupFollowUpRulesFixture(array $workingDays): array
{
    $center = Center::factory()->create(['working_days' => $workingDays]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => $workingDays,
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
    ]);

    return [$center, $group, $student];
}

/** @return array<string, mixed> */
function groupFollowUpRulesEvaluationPayload(
    Center $center,
    Group $group,
    Student $student,
    string $date = '2026-08-30',
): array {
    return [
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => $date,
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [[
            'student_id' => $student->id,
            'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
            'alhifz' => 8,
            'warud' => 8,
            'akhlaqi' => 8,
            'tajwid' => null,
            'note' => null,
        ]],
    ];
}

/** @return array<string, mixed> */
function groupFollowUpRulesHomeworkPayload(
    Center $center,
    Group $group,
    Student $student,
    string $date = '2026-08-30',
): array {
    return [
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => $date,
        'items' => [[
            'student_id' => $student->id,
            'points_adjustment' => 0,
            'points' => [],
        ]],
    ];
}

function createGroupFollowUpRulesMonthlyPlan(Group $group, string $startDate, string $endDate): MonthlyPlan
{
    $start = Carbon::parse($startDate);

    return MonthlyPlan::query()->create([
        'month' => $start->month,
        'year' => $start->year,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'center_id' => $group->center_id,
        'group_id' => $group->id,
    ]);
}

/** @return array<string, mixed> */
function groupFollowUpRulesMonthlyPlanPayload(Center $center): array
{
    return [
        'center_id' => $center->id,
        'month' => 8,
        'year' => 2026,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'holiday_dates' => [],
    ];
}

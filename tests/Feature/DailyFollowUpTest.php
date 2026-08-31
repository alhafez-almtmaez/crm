<?php

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkStudent;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\HomeworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/** @return list<string> */
function dailyFollowUpPermissions(): array
{
    return [
        'evaluations.view',
        'evaluations.create',
        'evaluations.update',
        'homeworks.view',
        'homeworks.create',
        'homeworks.update',
    ];
}

/**
 * @param  list<string>  $permissions
 */
function dailyFollowUpUser(array $permissions = []): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

/** @return list<string> */
function dailyFollowUpAllWorkingDays(): array
{
    return ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
}

/**
 * @return array{
 *     user: User,
 *     center: Center,
 *     group: Group,
 *     plan: Plan,
 *     point: PlanPoint,
 *     student: Student
 * }
 */
function dailyFollowUpFixture(?array $workingDays = null): array
{
    $user = dailyFollowUpUser(dailyFollowUpPermissions());
    $center = Center::factory()->create([
        'working_days' => $workingDays ?? dailyFollowUpAllWorkingDays(),
    ]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => $workingDays ?? dailyFollowUpAllWorkingDays(),
    ]);
    $plan = Plan::factory()->create();
    $point = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 1,
        'name' => 'Daily follow-up point',
        'points' => 5,
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'admin_id' => $user->id,
        'full_name' => 'Daily Follow Up Student',
        'points_balance' => 0,
    ]);
    MonthlyPlan::query()->create([
        'month' => 9,
        'year' => 2026,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $user->id,
        'generated_at' => now(),
    ]);

    return compact('user', 'center', 'group', 'plan', 'point', 'student');
}

/** @return array<string, mixed> */
function dailyFollowUpEvaluationPayload(array $fixture, array $overrides = []): array
{
    return [
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-05',
        'evaluation_type' => Evaluation::TYPE_TAJWID,
        'items' => [[
            'student_id' => $fixture['student']->id,
            'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
            'alhifz' => null,
            'warud' => 9,
            'akhlaqi' => 7,
            'tajwid' => 8,
            'note' => 'Unified evaluation entry',
        ]],
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function dailyFollowUpHomeworkPayload(array $fixture, array $overrides = []): array
{
    return [
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-05',
        'items' => [[
            'student_id' => $fixture['student']->id,
            'points_adjustment' => 2,
            'points' => [[
                'plan_point_id' => $fixture['point']->id,
                'is_done' => false,
                'is_next_homework' => true,
            ]],
        ]],
        ...$overrides,
    ];
}

/** @return array<string, mixed> */
function dailyFollowUpUnifiedPayload(array $fixture, array $overrides = []): array
{
    $evaluation = dailyFollowUpEvaluationPayload($fixture);
    $homework = dailyFollowUpHomeworkPayload($fixture);

    return [
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-05',
        'evaluation' => [
            'evaluation_type' => $evaluation['evaluation_type'],
            'items' => $evaluation['items'],
        ],
        'homework' => ['items' => $homework['items']],
        ...$overrides,
    ];
}

test('daily follow-up renders both specialized payloads from one selection', function () {
    $fixture = dailyFollowUpFixture();

    $this->actingAs($fixture['user'], 'web')
        ->get('/admin/daily-follow-up?'.http_build_query([
            'center_id' => $fixture['center']->id,
            'group_id' => $fixture['group']->id,
            'date' => '2026-09-02',
            'section' => 'homework',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/DailyFollowUp', false)
            ->has('centers', 1)
            ->where('centers.0.id', $fixture['center']->id)
            ->where('centers.0.groups.0.id', $fixture['group']->id)
            ->where('centers.0.groups.0.working_days', dailyFollowUpAllWorkingDays())
            ->where('selection.center_id', $fixture['center']->id)
            ->where('selection.group_id', $fixture['group']->id)
            ->where('selection.date', '2026-09-02')
            ->where('evaluation.id', null)
            ->where('evaluation.accessible', true)
            ->where('evaluation.evaluation_type', Evaluation::TYPE_ALHIFZ)
            ->has('evaluation.students', 1)
            ->where('evaluation.students.0.student_id', $fixture['student']->id)
            ->where('evaluation.students.0.attendances', EvaluationStudent::ATTENDANCE_PRESENT)
            ->where('evaluation.students.0.alhifz', 10)
            ->where('evaluation.students.0.warud', 10)
            ->where('homework.id', null)
            ->where('homework.accessible', true)
            ->has('homework.students', 1)
            ->where('homework.students.0.student_id', $fixture['student']->id)
            ->where('homework.students.0.points_balance', 0)
            ->has('homework.students.0.points', 1)
            ->where('homework.students.0.points.0.plan_point_id', $fixture['point']->id)
            ->where('permissions.can_view_evaluations', true)
            ->where('permissions.can_create_evaluation', true)
            ->where('permissions.can_update_evaluation', true)
            ->where('permissions.can_view_homeworks', true)
            ->where('permissions.can_create_homework', true)
            ->where('permissions.can_update_homework', true)
            ->where('active_section', 'homework'));
});

test('daily follow-up resolves existing evaluation and homework ids independently', function () {
    $fixture = dailyFollowUpFixture();
    $evaluationDate = '2026-09-03';
    $homeworkDate = '2026-09-04';

    $evaluation = Evaluation::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => $evaluationDate,
        'admin_id' => $fixture['user']->id,
        'evaluation_type' => Evaluation::TYPE_TAJWID,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $fixture['student']->id,
        'user_id' => $fixture['student']->id,
    ]);

    $homework = Homework::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => $homeworkDate,
        'admin_id' => $fixture['user']->id,
    ]);
    HomeworkStudent::query()->create([
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
        'plan_id' => $fixture['plan']->id,
        'points_balance_before' => 0,
        'points_adjustment' => 0,
        'points_balance_after' => 0,
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->get('/admin/daily-follow-up?'.http_build_query([
            'group_id' => $fixture['group']->id,
            'date' => $evaluationDate,
            'section' => 'evaluation',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.id', $evaluation->id)
            ->where('evaluation.evaluation_type', Evaluation::TYPE_TAJWID)
            ->where('homework.id', null));

    $this->actingAs($fixture['user'], 'web')
        ->get('/admin/daily-follow-up?'.http_build_query([
            'group_id' => $fixture['group']->id,
            'date' => $homeworkDate,
            'section' => 'homework',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.id', null)
            ->where('homework.id', $homework->id));
});

test('daily follow-up uses the homework canonical working date for evaluation data', function () {
    $fixture = dailyFollowUpFixture(['monday']);
    $requestedDate = '2026-09-01';
    $canonicalDate = '2026-09-07';

    $requestedDateEvaluation = Evaluation::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => $requestedDate,
        'admin_id' => $fixture['user']->id,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $requestedDateEvaluation->id,
        'student_id' => $fixture['student']->id,
        'user_id' => $fixture['student']->id,
    ]);

    $canonicalDateEvaluation = Evaluation::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => $canonicalDate,
        'admin_id' => $fixture['user']->id,
        'evaluation_type' => Evaluation::TYPE_TAJWID,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $canonicalDateEvaluation->id,
        'student_id' => $fixture['student']->id,
        'user_id' => $fixture['student']->id,
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->get('/admin/daily-follow-up?'.http_build_query([
            'group_id' => $fixture['group']->id,
            'date' => $requestedDate,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.date', $canonicalDate)
            ->where('date_adjustment.from', $requestedDate)
            ->where('date_adjustment.to', $canonicalDate)
            ->where('evaluation.id', $canonicalDateEvaluation->id)
            ->where('evaluation.evaluation_type', Evaluation::TYPE_TAJWID)
            ->where('homework.id', null));
});

test('daily follow-up creates evaluations and homeworks then redirects to the workspace', function () {
    $fixture = dailyFollowUpFixture();

    $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/evaluations', dailyFollowUpEvaluationPayload($fixture))
        ->assertSessionHasNoErrors()
        ->assertRedirectContains('/admin/daily-follow-up');

    $evaluation = Evaluation::query()
        ->where('group_id', $fixture['group']->id)
        ->whereDate('date', '2026-09-05')
        ->sole();

    expect($evaluation->center_id)->toBe($fixture['center']->id)
        ->and($evaluation->evaluation_type)->toBe(Evaluation::TYPE_TAJWID);
    $this->assertDatabaseHas('evaluations_users', [
        'evaluation_id' => $evaluation->id,
        'student_id' => $fixture['student']->id,
        'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
        'alhifz' => null,
        'warud' => 9,
        'akhlaqi' => 7,
        'tajwid' => 8,
        'note' => 'Unified evaluation entry',
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/homeworks', dailyFollowUpHomeworkPayload($fixture))
        ->assertSessionHasNoErrors()
        ->assertRedirectContains('/admin/daily-follow-up');

    $homework = Homework::query()
        ->where('group_id', $fixture['group']->id)
        ->whereDate('date', '2026-09-05')
        ->sole();
    $homeworkStudent = HomeworkStudent::query()
        ->where('homework_id', $homework->id)
        ->where('student_id', $fixture['student']->id)
        ->sole();

    expect($homework->center_id)->toBe($fixture['center']->id)
        ->and($homeworkStudent->points_adjustment)->toBe(2)
        ->and($homeworkStudent->points_balance_after)->toBe(2);
    $this->assertDatabaseHas('homework_student_points', [
        'homework_id' => $homework->id,
        'homework_student_id' => $homeworkStudent->id,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $fixture['point']->id,
        'is_done' => false,
        'is_next_homework' => true,
    ]);
});

test('daily follow-up updates existing evaluations and homeworks in place', function () {
    $fixture = dailyFollowUpFixture();
    $date = '2026-09-06';

    $evaluation = Evaluation::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => $date,
        'admin_id' => $fixture['user']->id,
        'evaluation_type' => Evaluation::TYPE_TAJWID,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $fixture['student']->id,
        'user_id' => $fixture['student']->id,
        'tajwid' => 6,
    ]);

    $homework = Homework::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => $date,
        'admin_id' => $fixture['user']->id,
    ]);
    $homeworkStudent = HomeworkStudent::query()->create([
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
        'plan_id' => $fixture['plan']->id,
        'points_balance_before' => 0,
        'points_adjustment' => 0,
        'points_balance_after' => 0,
    ]);

    $evaluationUpdatePayload = dailyFollowUpEvaluationPayload($fixture, [
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [[
            'student_id' => $fixture['student']->id,
            'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
            'alhifz' => 7,
            'warud' => 8,
            'akhlaqi' => 9,
            'tajwid' => null,
            'note' => 'Updated from daily follow-up',
        ]],
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->put("/admin/daily-follow-up/evaluations/{$evaluation->id}", $evaluationUpdatePayload)
        ->assertSessionHasNoErrors()
        ->assertRedirectContains('/admin/daily-follow-up');

    expect(Evaluation::query()->count())->toBe(1)
        ->and($evaluation->fresh()->evaluation_type)->toBe(Evaluation::TYPE_ALHIFZ);
    $this->assertDatabaseHas('evaluations_users', [
        'evaluation_id' => $evaluation->id,
        'student_id' => $fixture['student']->id,
        'alhifz' => 7,
        'warud' => 8,
        'akhlaqi' => 9,
        'tajwid' => null,
        'note' => 'Updated from daily follow-up',
    ]);

    $homeworkUpdatePayload = dailyFollowUpHomeworkPayload($fixture, [
        'items' => [[
            'student_id' => $fixture['student']->id,
            'points_adjustment' => 3,
            'points' => [[
                'plan_point_id' => $fixture['point']->id,
                'is_done' => true,
                'is_next_homework' => false,
            ]],
        ]],
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->put("/admin/daily-follow-up/homeworks/{$homework->id}", $homeworkUpdatePayload)
        ->assertSessionHasNoErrors()
        ->assertRedirectContains('/admin/daily-follow-up');

    expect(Homework::query()->count())->toBe(1)
        ->and($homeworkStudent->fresh()->points_adjustment)->toBe(3)
        ->and($fixture['student']->fresh()->points_balance)->toBe(8);
    $this->assertDatabaseHas('homework_student_points', [
        'homework_id' => $homework->id,
        'homework_student_id' => $homeworkStudent->id,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $fixture['point']->id,
        'is_done' => true,
        'is_next_homework' => false,
        'awarded_points' => 5,
    ]);
});

test('daily follow-up denies guests and users without a relevant permission', function () {
    $this->get('/admin/daily-follow-up')
        ->assertRedirect('/admin/login');

    $user = dailyFollowUpUser();

    $this->actingAs($user, 'web')
        ->get('/admin/daily-follow-up')
        ->assertForbidden();
});

test('an inaccessible shared group record does not block the other follow-up section', function () {
    $supervisor = dailyFollowUpUser(dailyFollowUpPermissions());
    $otherSupervisor = dailyFollowUpUser(dailyFollowUpPermissions());
    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => dailyFollowUpAllWorkingDays(),
    ]);
    $supervisorStudent = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $supervisor->id,
    ]);
    $otherStudent = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $otherSupervisor->id,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-09-08',
        'admin_id' => $otherSupervisor->id,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $otherStudent->id,
        'user_id' => $otherStudent->id,
    ]);

    $this->actingAs($supervisor, 'web')
        ->get('/admin/daily-follow-up?'.http_build_query([
            'group_id' => $group->id,
            'date' => '2026-09-08',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('evaluation.id', $evaluation->id)
            ->where('evaluation.accessible', false)
            ->has('evaluation.students', 0)
            ->where('homework.id', null)
            ->where('homework.accessible', true)
            ->has('homework.students', 1)
            ->where('homework.students.0.student_id', $supervisorStudent->id)
            ->where('active_section', 'homework'));
});

test('daily follow-up rejects groups whose parent center is archived', function () {
    $fixture = dailyFollowUpFixture();
    $fixture['center']->forceFill(['archived_at' => now()])->save();

    $this->actingAs($fixture['user'], 'web')
        ->from('/admin/daily-follow-up')
        ->get('/admin/daily-follow-up?group_id='.$fixture['group']->id.'&date=2026-09-09')
        ->assertRedirect('/admin/daily-follow-up')
        ->assertSessionHasErrors('group_id');

    $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/evaluations', dailyFollowUpEvaluationPayload($fixture, [
            'date' => '2026-09-09',
        ]))
        ->assertSessionHasErrors('group_id');

    $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/homeworks', dailyFollowUpHomeworkPayload($fixture, [
            'date' => '2026-09-09',
        ]))
        ->assertSessionHasErrors('group_id');

    expect(Evaluation::query()->count())->toBe(0)
        ->and(Homework::query()->count())->toBe(0);
});

test('unified daily follow-up atomically creates evaluation and homework using the group center', function () {
    $fixture = dailyFollowUpFixture();

    $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/save', dailyFollowUpUnifiedPayload($fixture, ['center_id' => null]))
        ->assertSessionHasNoErrors()
        ->assertRedirectContains('/admin/daily-follow-up');

    $evaluation = Evaluation::query()->sole();
    $homework = Homework::query()->sole();
    $homeworkStudent = HomeworkStudent::query()->sole();

    expect((int) $evaluation->center_id)->toBe($fixture['center']->id)
        ->and((int) $homework->center_id)->toBe($fixture['center']->id)
        ->and((int) $evaluation->group_id)->toBe($fixture['group']->id)
        ->and((int) $homework->group_id)->toBe($fixture['group']->id)
        ->and($homeworkStudent->points_adjustment)->toBe(2)
        ->and($fixture['student']->fresh()->points_balance)->toBe(2);

    $this->assertDatabaseHas('evaluations_users', [
        'evaluation_id' => $evaluation->id,
        'student_id' => $fixture['student']->id,
        'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
        'tajwid' => 8,
        'note' => 'Unified evaluation entry',
    ]);
    $this->assertDatabaseHas('homework_student_points', [
        'homework_id' => $homework->id,
        'homework_student_id' => $homeworkStudent->id,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $fixture['point']->id,
        'is_done' => false,
        'is_next_homework' => true,
    ]);
});

test('unified daily follow-up updates both records without creating duplicates', function () {
    $fixture = dailyFollowUpFixture();

    $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/save', dailyFollowUpUnifiedPayload($fixture))
        ->assertSessionHasNoErrors();

    $evaluationId = Evaluation::query()->sole()->id;
    $homeworkId = Homework::query()->sole()->id;
    $payload = dailyFollowUpUnifiedPayload($fixture, [
        'evaluation' => [
            'evaluation_type' => Evaluation::TYPE_ALHIFZ,
            'items' => [[
                'student_id' => $fixture['student']->id,
                'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
                'alhifz' => 7,
                'warud' => 8,
                'akhlaqi' => 9,
                'tajwid' => null,
                'note' => 'Unified update',
            ]],
        ],
        'homework' => [
            'items' => [[
                'student_id' => $fixture['student']->id,
                'points_adjustment' => 3,
                'points' => [[
                    'plan_point_id' => $fixture['point']->id,
                    'is_done' => true,
                    'is_next_homework' => false,
                ]],
            ]],
        ],
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/save', $payload)
        ->assertSessionHasNoErrors();

    expect(Evaluation::query()->count())->toBe(1)
        ->and(Homework::query()->count())->toBe(1)
        ->and(Evaluation::query()->sole()->id)->toBe($evaluationId)
        ->and(Homework::query()->sole()->id)->toBe($homeworkId)
        ->and(Evaluation::query()->sole()->evaluation_type)->toBe(Evaluation::TYPE_ALHIFZ)
        ->and(HomeworkStudent::query()->sole()->points_adjustment)->toBe(3)
        ->and($fixture['student']->fresh()->points_balance)->toBe(8);

    $this->assertDatabaseHas('evaluations_users', [
        'evaluation_id' => $evaluationId,
        'student_id' => $fixture['student']->id,
        'alhifz' => 7,
        'tajwid' => null,
        'note' => 'Unified update',
    ]);
    $this->assertDatabaseHas('homework_student_points', [
        'homework_id' => $homeworkId,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $fixture['point']->id,
        'is_done' => true,
        'is_next_homework' => false,
        'awarded_points' => 5,
    ]);
});

test('unified daily follow-up rolls evaluation changes back when homework persistence fails', function () {
    $fixture = dailyFollowUpFixture();
    $homeworkService = Mockery::mock(HomeworkService::class);
    $homeworkService->shouldReceive('create')
        ->once()
        ->andThrow(new RuntimeException('Simulated homework failure'));
    $this->app->instance(HomeworkService::class, $homeworkService);
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/save', dailyFollowUpUnifiedPayload($fixture)))
        ->toThrow(RuntimeException::class, 'Simulated homework failure');

    expect(Evaluation::query()->count())->toBe(0)
        ->and(EvaluationStudent::query()->count())->toBe(0)
        ->and(Homework::query()->count())->toBe(0)
        ->and($fixture['student']->fresh()->points_balance)->toBe(0);
});

test('unified daily follow-up rejects a plan point that is done and next homework together', function () {
    $fixture = dailyFollowUpFixture();
    $payload = dailyFollowUpUnifiedPayload($fixture);
    $payload['homework']['items'][0]['points'][0]['is_done'] = true;
    $payload['homework']['items'][0]['points'][0]['is_next_homework'] = true;

    $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/save', $payload)
        ->assertSessionHasErrors('homework.items.0.points.0.is_next_homework');

    expect(Evaluation::query()->count())->toBe(0)
        ->and(Homework::query()->count())->toBe(0)
        ->and($fixture['student']->fresh()->points_balance)->toBe(0);
});

test('unified daily follow-up requires update permission for an existing record', function () {
    $fixture = dailyFollowUpFixture();

    $this->actingAs($fixture['user'], 'web')
        ->post('/admin/daily-follow-up/save', dailyFollowUpUnifiedPayload($fixture))
        ->assertSessionHasNoErrors();

    $fixture['user']->revokePermissionTo('evaluations.update');
    $evaluation = Evaluation::query()->sole();
    $payload = dailyFollowUpUnifiedPayload($fixture, [
        'homework' => null,
        'evaluation' => [
            'evaluation_type' => Evaluation::TYPE_ALHIFZ,
            'items' => [[
                'student_id' => $fixture['student']->id,
                'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
                'alhifz' => 2,
                'warud' => 2,
                'akhlaqi' => 2,
                'tajwid' => null,
                'note' => 'Must not be persisted',
            ]],
        ],
    ]);

    $this->actingAs($fixture['user']->fresh(), 'web')
        ->post('/admin/daily-follow-up/save', $payload)
        ->assertForbidden();

    expect(Evaluation::query()->count())->toBe(1)
        ->and($evaluation->fresh()->evaluation_type)->toBe(Evaluation::TYPE_TAJWID);
    $this->assertDatabaseMissing('evaluations_users', [
        'evaluation_id' => $evaluation->id,
        'note' => 'Must not be persisted',
    ]);
});

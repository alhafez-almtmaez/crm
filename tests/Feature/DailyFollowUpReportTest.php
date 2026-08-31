<?php

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkStudent;
use App\Models\HomeworkStudentPoint;
use App\Models\MonthlyPlan;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentMonthlyPlan;
use App\Models\StudentMonthlyPlanDay;
use App\Models\StudentMonthlyPlanItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 12:00:00', 'Asia/Amman'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/** @return array<string, mixed> */
function dailyReportFixture(): array
{
    $permissions = [
        'evaluations.view',
        'evaluations.create',
        'evaluations.update',
        'homeworks.view',
        'homeworks.create',
        'homeworks.update',
        'monthly_plans.view',
    ];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo($permissions);
    $center = Center::factory()->create(['working_days' => ['monday', 'thursday']]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => ['monday', 'thursday'],
    ]);
    $plan = Plan::factory()->create(['name' => 'Report curriculum']);
    $firstPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 1,
        'name' => 'First planned point',
        'weight' => 1,
    ]);
    $secondPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 2,
        'name' => 'Second planned point',
        'weight' => 1,
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'admin_id' => $user->id,
        'full_name' => 'Plan Tracking Student',
    ]);
    $monthlyPlan = MonthlyPlan::query()->create([
        'month' => 9,
        'year' => 2026,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $user->id,
        'generated_at' => now(),
    ]);
    $studentPlan = StudentMonthlyPlan::query()->create([
        'monthly_plan_id' => $monthlyPlan->id,
        'student_id' => $student->id,
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_id' => $plan->id,
        'month' => 9,
        'year' => 2026,
        'max_daily_weight' => 2,
        'generated_items_count' => 2,
        'status' => StudentMonthlyPlan::STATUS_GENERATED,
        'generated_at' => now(),
    ]);
    $firstDay = StudentMonthlyPlanDay::query()->create([
        'student_monthly_plan_id' => $studentPlan->id,
        'date' => '2026-09-03',
        'day_number' => 3,
        'total_weight' => 1,
        'daily_weight_limit' => 2,
    ]);
    $secondDay = StudentMonthlyPlanDay::query()->create([
        'student_monthly_plan_id' => $studentPlan->id,
        'date' => '2026-09-07',
        'day_number' => 7,
        'total_weight' => 1,
        'daily_weight_limit' => 2,
    ]);
    StudentMonthlyPlanItem::query()->create([
        'student_monthly_plan_id' => $studentPlan->id,
        'student_monthly_plan_day_id' => $firstDay->id,
        'student_id' => $student->id,
        'plan_point_id' => $firstPoint->id,
        'sort_order' => 1,
        'weight' => 1,
        'status' => StudentMonthlyPlanItem::STATUS_GENERATED,
    ]);
    StudentMonthlyPlanItem::query()->create([
        'student_monthly_plan_id' => $studentPlan->id,
        'student_monthly_plan_day_id' => $secondDay->id,
        'student_id' => $student->id,
        'plan_point_id' => $secondPoint->id,
        'sort_order' => 2,
        'weight' => 1,
        'status' => StudentMonthlyPlanItem::STATUS_GENERATED,
    ]);

    return compact(
        'user',
        'center',
        'group',
        'plan',
        'firstPoint',
        'secondPoint',
        'student',
        'monthlyPlan',
        'studentPlan',
    );
}

test('daily follow-up exposes plan adherence and next scheduled work for each student', function () {
    $fixture = dailyReportFixture();
    $homework = Homework::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-03',
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
    HomeworkStudentPoint::query()->create([
        'homework_student_id' => $homeworkStudent->id,
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $fixture['firstPoint']->id,
        'sort_order' => 1,
        'is_done' => true,
        'is_next_homework' => false,
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->get('/admin/daily-follow-up?'.http_build_query([
            'center_id' => $fixture['center']->id,
            'group_id' => $fixture['group']->id,
            'date' => '2026-09-03',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/DailyFollowUp', false)
            ->where('plan_context.available', true)
            ->where('plan_context.monthly_plan.id', $fixture['monthlyPlan']->id)
            ->where('plan_context.summary.on_track_count', 1)
            ->has('plan_context.students', 1)
            ->where('plan_context.students.0.student_id', $fixture['student']->id)
            ->where('plan_context.students.0.status', 'on_track')
            ->where('plan_context.students.0.adherence_percentage', 100)
            ->where('plan_context.students.0.progress_percentage', 50)
            ->where('plan_context.students.0.today.items.0.plan_point_id', $fixture['firstPoint']->id)
            ->where('plan_context.students.0.today.items.0.completed', true)
            ->where('plan_context.students.0.next.items.0.plan_point_id', $fixture['secondPoint']->id));
});

test('student follow-up report returns scoped attendance evaluation homework and progress charts', function () {
    $fixture = dailyReportFixture();
    $tajwidEvaluation = Evaluation::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-01',
        'admin_id' => $fixture['user']->id,
        'evaluation_type' => Evaluation::TYPE_TAJWID,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $tajwidEvaluation->id,
        'student_id' => $fixture['student']->id,
        'user_id' => $fixture['student']->id,
        'alhifz' => null,
        'tajwid' => 6,
        'warud' => 7,
        'akhlaqi' => 8,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-03',
        'admin_id' => $fixture['user']->id,
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $fixture['student']->id,
        'user_id' => $fixture['student']->id,
        'alhifz' => 8,
        'warud' => 9,
        'akhlaqi' => 10,
    ]);
    $homework = Homework::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-03',
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
    HomeworkStudentPoint::query()->create([
        'homework_student_id' => $homeworkStudent->id,
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $fixture['firstPoint']->id,
        'sort_order' => 1,
        'is_done' => true,
        'is_next_homework' => false,
    ]);
    HomeworkStudentPoint::query()->create([
        'homework_student_id' => $homeworkStudent->id,
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $fixture['secondPoint']->id,
        'sort_order' => 2,
        'is_done' => false,
        'is_next_homework' => true,
    ]);
    $outsidePlanPoint = PlanPoint::factory()->create([
        'plan_id' => $fixture['plan']->id,
        'sort_order' => 3,
        'name' => 'Outside monthly plan point',
        'weight' => 1,
    ]);
    HomeworkStudentPoint::query()->create([
        'homework_student_id' => $homeworkStudent->id,
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $outsidePlanPoint->id,
        'sort_order' => 3,
        'is_done' => false,
        'is_next_homework' => true,
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->getJson(route('admin.daily-follow-up.student-report', [
            'student' => $fixture['student'],
            'group_id' => $fixture['group']->id,
            'date' => '2026-09-03',
        ]))
        ->assertOk()
        ->assertJsonPath('student.id', $fixture['student']->id)
        ->assertJsonPath('summary.attendance_rate', 100)
        ->assertJsonPath('summary.evaluation_average', 80)
        ->assertJsonPath('summary.plan_progress', 50)
        ->assertJsonPath('summary.plan_adherence', 100)
        ->assertJsonPath('summary.assigned_items_count', 2)
        ->assertJsonPath('attendance.counts.present', 2)
        ->assertJsonPath('evaluation.alhifz.0', null)
        ->assertJsonPath('evaluation.alhifz.1', 8)
        ->assertJsonPath('evaluation.tajwid.0', 6)
        ->assertJsonPath('evaluation.tajwid.1', null)
        ->assertJsonPath('achievement.expected_cumulative.0', 0)
        ->assertJsonPath('achievement.expected_cumulative.1', 50)
        ->assertJsonPath('achievement.completed_cumulative.1', 50)
        ->assertJsonPath('achievement.assigned_daily.1', 1)
        ->assertJsonPath('achievement.outside_plan_daily.1', 1)
        ->assertJsonPath('insight', 'outside_plan_work');
});

test('student follow-up report does not expose a student outside the supervisor scope', function () {
    $fixture = dailyReportFixture();
    $otherUser = User::factory()->create();
    $otherStudent = Student::factory()->active()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'plan_type_id' => $fixture['plan']->id,
        'admin_id' => $otherUser->id,
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->getJson(route('admin.daily-follow-up.student-report', [
            'student' => $otherStudent,
            'group_id' => $fixture['group']->id,
            'date' => '2026-09-03',
        ]))
        ->assertNotFound();
});

test('combined student report requires access to both evaluation and homework domains', function () {
    $fixture = dailyReportFixture();
    $fixture['user']->revokePermissionTo([
        'homeworks.view',
        'homeworks.create',
        'homeworks.update',
        'monthly_plans.view',
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->getJson(route('admin.daily-follow-up.student-report', [
            'student' => $fixture['student'],
            'group_id' => $fixture['group']->id,
            'date' => '2026-09-03',
        ]))
        ->assertForbidden();

    $this->get('/admin/daily-follow-up?'.http_build_query([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-03',
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.can_view_reports', false)
            ->where('plan_context.available', true)
            ->where('plan_context.progress_available', false)
            ->where('plan_context.monthly_plan.edit_url', null)
            ->where('plan_context.monthly_plan.public_report_url', null)
            ->has('plan_context.students', 0));
});

test('workspace marks a current student missing from the monthly plan without offering a broken report', function () {
    $fixture = dailyReportFixture();
    $missingStudent = Student::factory()->active()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'plan_type_id' => $fixture['plan']->id,
        'admin_id' => $fixture['user']->id,
        'full_name' => 'Student without generated plan',
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->get('/admin/daily-follow-up?'.http_build_query([
            'center_id' => $fixture['center']->id,
            'group_id' => $fixture['group']->id,
            'date' => '2026-09-03',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan_context.summary.missing_count', 1)
            ->has('plan_context.students', 2)
            ->where('plan_context.students.1.student_id', $missingStudent->id)
            ->where('plan_context.students.1.status', 'missing_student_plan')
            ->where('plan_context.students.1.report_url', null));
});

test('future workspace shows the selected plan day without counting future completion', function () {
    $fixture = dailyReportFixture();
    $homework = Homework::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-03',
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
    HomeworkStudentPoint::query()->create([
        'homework_student_id' => $homeworkStudent->id,
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $fixture['firstPoint']->id,
        'sort_order' => 1,
        'is_done' => true,
        'is_next_homework' => false,
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->get('/admin/daily-follow-up?'.http_build_query([
            'center_id' => $fixture['center']->id,
            'group_id' => $fixture['group']->id,
            'date' => '2026-09-07',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan_context.students.0.progress_percentage', 50)
            ->where('plan_context.students.0.today.items.0.plan_point_id', $fixture['secondPoint']->id)
            ->where('plan_context.students.0.today.items.0.completed', false));
});

test('student report excludes activity after its effective as of date', function () {
    $fixture = dailyReportFixture();
    $futureEvaluation = Evaluation::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-07',
        'admin_id' => $fixture['user']->id,
        'evaluation_type' => Evaluation::TYPE_TAJWID,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $futureEvaluation->id,
        'student_id' => $fixture['student']->id,
        'user_id' => $fixture['student']->id,
        'tajwid' => 7,
        'warud' => 7,
        'akhlaqi' => 7,
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->getJson(route('admin.daily-follow-up.student-report', [
            'student' => $fixture['student'],
            'group_id' => $fixture['group']->id,
            'date' => '2026-09-03',
        ]))
        ->assertOk()
        ->assertJsonPath('period.as_of_date', '2026-09-03')
        ->assertJsonCount(0, 'evaluation.labels')
        ->assertJsonCount(1, 'achievement.labels')
        ->assertJsonPath('achievement.labels.0', '2026-09-03');
});

test('zero weight attachment does not mark an otherwise completed plan as behind', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 12:00:00', 'Asia/Amman'));
    $fixture = dailyReportFixture();
    StudentMonthlyPlanItem::query()
        ->where('student_monthly_plan_id', $fixture['studentPlan']->id)
        ->where('plan_point_id', $fixture['secondPoint']->id)
        ->update(['weight' => 0]);
    $homework = Homework::factory()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'date' => '2026-09-03',
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
    HomeworkStudentPoint::query()->create([
        'homework_student_id' => $homeworkStudent->id,
        'homework_id' => $homework->id,
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $fixture['firstPoint']->id,
        'sort_order' => 1,
        'is_done' => true,
        'is_next_homework' => false,
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->get('/admin/daily-follow-up?'.http_build_query([
            'center_id' => $fixture['center']->id,
            'group_id' => $fixture['group']->id,
            'date' => '2026-09-07',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan_context.students.0.status', 'completed')
            ->where('plan_context.students.0.adherence_percentage', 100)
            ->where('plan_context.students.0.completed_items_count', 1)
            ->where('plan_context.students.0.total_items_count', 1));
});

<?php

use App\Models\Center;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkStudent;
use App\Models\HomeworkStudentPoint;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('admin can export a homework pdf report', function () {
    Pdf::fake();

    Permission::create([
        'name' => 'homeworks.view',
        'guard_name' => 'web',
    ]);

    $admin = User::factory()->create();
    $admin->givePermissionTo('homeworks.view');

    $center = Center::factory()->create([
        'name' => 'Main Center',
        'working_days' => ['tuesday'],
    ]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => 'Group A',
    ]);
    $plan = Plan::factory()->create(['name' => 'Plan A']);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'full_name' => 'Student One Three',
            'points_balance' => 7,
        ]);
    $frozenStudent = Student::factory()
        ->frozen()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'full_name' => 'Frozen Student Name',
        ]);
    $studentWithoutNextHomework = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'full_name' => 'No Next Homework',
        ]);
    $planPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 1,
        'name' => 'Assignment Point',
        'points' => 5,
    ]);
    $homework = Homework::query()->create([
        'date' => '2026-06-22',
        'center_id' => $center->id,
        'admin_id' => $admin->id,
    ]);
    $homeworkStudent = HomeworkStudent::query()->create([
        'homework_id' => $homework->id,
        'student_id' => $student->id,
        'plan_id' => $plan->id,
        'current_plan_point_id' => null,
        'points_balance_before' => 2,
        'points_adjustment' => 0,
        'points_balance_after' => 7,
    ]);

    HomeworkStudentPoint::query()->create([
        'homework_student_id' => $homeworkStudent->id,
        'homework_id' => $homework->id,
        'student_id' => $student->id,
        'plan_point_id' => $planPoint->id,
        'sort_order' => 1,
        'is_done' => false,
        'is_next_homework' => true,
        'awarded_points' => 0,
        'awarded_at' => null,
    ]);

    $frozenHomeworkStudent = HomeworkStudent::query()->create([
        'homework_id' => $homework->id,
        'student_id' => $frozenStudent->id,
        'plan_id' => $plan->id,
        'current_plan_point_id' => null,
        'points_balance_before' => 0,
        'points_adjustment' => 0,
        'points_balance_after' => 0,
    ]);
    HomeworkStudentPoint::query()->create([
        'homework_student_id' => $frozenHomeworkStudent->id,
        'homework_id' => $homework->id,
        'student_id' => $frozenStudent->id,
        'plan_point_id' => $planPoint->id,
        'sort_order' => 1,
        'is_done' => false,
        'is_next_homework' => true,
        'awarded_points' => 0,
        'awarded_at' => null,
    ]);
    HomeworkStudent::query()->create([
        'homework_id' => $homework->id,
        'student_id' => $studentWithoutNextHomework->id,
        'plan_id' => $plan->id,
        'current_plan_point_id' => null,
        'points_balance_before' => 0,
        'points_adjustment' => 0,
        'points_balance_after' => 0,
    ]);

    $this->actingAs($admin, 'web')
        ->get(route('admin.homeworks.pdf', $homework))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($homework): bool {
        return $pdf->viewName === 'pdf.homework'
            && $pdf->downloadName === "homework-{$homework->id}-2026-06-22.pdf"
            && ($pdf->viewData['homework']['id'] ?? null) === $homework->id
            && ($pdf->viewData['homework']['next_homework_date'] ?? null) === '2026-06-23'
            && ($pdf->viewData['students'][0]['pdf_name'] ?? null) === 'Student One Three'
            && ($pdf->viewData['students'][0]['pdf_homework_cells'][0] ?? null) === 'Assignment Point'
            && count($pdf->viewData['students'] ?? []) === 1
            && ($pdf->viewData['totals']['next_homework_count'] ?? null) === 1;
    });
});

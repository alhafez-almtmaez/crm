<?php

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\MonthlyPlan;
use App\Models\Student;
use App\Services\Admin\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('evaluation creation stores exempt attendance as its own row without scores', function () {
    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => evaluationExemptAllWorkingDays(),
    ]);
    createEvaluationExemptMonthlyPlan($group);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
        ]);

    app(EvaluationService::class)->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-06-16',
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [
            [
                'student_id' => $student->id,
                'attendances' => EvaluationStudent::ATTENDANCE_EXEMPT,
                'alhifz' => 10,
                'warud' => 10,
                'akhlaqi' => 10,
                'tajwid' => 10,
            ],
        ],
    ]);

    $row = EvaluationStudent::query()->firstOrFail();

    expect($row->attendances)->toBe(EvaluationStudent::ATTENDANCE_EXEMPT)
        ->and($row->alhifz)->toBeNull()
        ->and($row->warud)->toBeNull()
        ->and($row->akhlaqi)->toBeNull()
        ->and($row->tajwid)->toBeNull();
});

test('evaluation update can change an existing row to exempt attendance', function () {
    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => evaluationExemptAllWorkingDays(),
    ]);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
        ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-06-16',
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
    ]);
    EvaluationStudent::factory()
        ->present()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    app(EvaluationService::class)->update($evaluation, [
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [
            [
                'student_id' => $student->id,
                'attendances' => EvaluationStudent::ATTENDANCE_EXEMPT,
                'alhifz' => 10,
                'warud' => 10,
                'akhlaqi' => 10,
                'tajwid' => 10,
            ],
        ],
    ]);

    $row = EvaluationStudent::query()
        ->where('evaluation_id', $evaluation->id)
        ->where('student_id', $student->id)
        ->firstOrFail();

    expect($row->attendances)->toBe(EvaluationStudent::ATTENDANCE_EXEMPT)
        ->and($row->alhifz)->toBeNull()
        ->and($row->warud)->toBeNull()
        ->and($row->akhlaqi)->toBeNull()
        ->and($row->tajwid)->toBeNull();
});

test('evaluation creation stores late attendance with its evaluation scores', function () {
    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => evaluationExemptAllWorkingDays(),
    ]);
    createEvaluationExemptMonthlyPlan($group);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
        ]);

    app(EvaluationService::class)->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-06-16',
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [
            [
                'student_id' => $student->id,
                'attendances' => EvaluationStudent::ATTENDANCE_LATE,
                'alhifz' => 8,
                'warud' => 7,
                'akhlaqi' => 9,
                'tajwid' => 6,
            ],
        ],
    ]);

    $row = EvaluationStudent::query()->firstOrFail();

    expect($row->attendances)->toBe(EvaluationStudent::ATTENDANCE_LATE)
        ->and($row->alhifz)->toBe(8)
        ->and($row->warud)->toBe(7)
        ->and($row->akhlaqi)->toBe(9)
        ->and($row->tajwid)->toBeNull();
});

/** @return array<int, string> */
function evaluationExemptAllWorkingDays(): array
{
    return ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
}

function createEvaluationExemptMonthlyPlan(Group $group): MonthlyPlan
{
    return MonthlyPlan::query()->create([
        'month' => 6,
        'year' => 2026,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'center_id' => $group->center_id,
        'group_id' => $group->id,
    ]);
}

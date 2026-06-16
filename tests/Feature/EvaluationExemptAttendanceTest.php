<?php

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Student;
use App\Services\Admin\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('evaluation creation stores exempt attendance as its own row without scores', function () {
    $center = Center::factory()->create();
    $student = Student::factory()
        ->active()
        ->create(['center_id' => $center->id]);

    app(EvaluationService::class)->create([
        'center_id' => $center->id,
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
    $student = Student::factory()
        ->active()
        ->create(['center_id' => $center->id]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
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

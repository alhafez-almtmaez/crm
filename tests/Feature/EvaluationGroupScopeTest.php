<?php

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\Student;
use App\Services\Admin\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('evaluation students and uniqueness are scoped to the selected group', function () {
    $center = Center::factory()->create();
    $firstGroup = Group::factory()->create(['center_id' => $center->id]);
    $secondGroup = Group::factory()->create(['center_id' => $center->id]);
    $firstStudent = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $firstGroup->id,
    ]);
    $secondStudent = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $secondGroup->id,
    ]);
    $service = app(EvaluationService::class);

    $payload = $service->createFormPayload(
        centerId: $center->id,
        groupId: $firstGroup->id,
        date: '2026-08-30',
    );

    expect(collect($payload['students'])->pluck('student_id')->all())
        ->toBe([$firstStudent->id])
        ->not->toContain($secondStudent->id);

    $firstEvaluation = $service->create(evaluationPayload($firstGroup, $firstStudent));
    $secondEvaluation = $service->create(evaluationPayload($secondGroup, $secondStudent));

    expect($firstEvaluation->group_id)->toBe($firstGroup->id)
        ->and($firstEvaluation->center_id)->toBe($center->id)
        ->and($secondEvaluation->group_id)->toBe($secondGroup->id)
        ->and(Evaluation::query()->whereDate('date', '2026-08-30')->count())->toBe(2);

    expect(fn () => $service->create(evaluationPayload($firstGroup, $firstStudent)))
        ->toThrow(ValidationException::class);
});

test('public evaluation report identifies both center and group', function () {
    $center = Center::factory()->create(['name' => 'المركز الأصلي']);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => 'المجموعة الأولى',
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
    ]);

    $evaluation = app(EvaluationService::class)->create(evaluationPayload($group, $student));
    $report = app(EvaluationService::class)->reportPayload((string) $evaluation->ulid);

    expect($report['center_name'])->toBe('المركز الأصلي')
        ->and($report['group_name'])->toBe('المجموعة الأولى')
        ->and($report['rows'])->toHaveCount(1);
});

/**
 * @return array<string, mixed>
 */
function evaluationPayload(Group $group, Student $student): array
{
    return [
        'center_id' => $group->center_id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [[
            'student_id' => $student->id,
            'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
            'alhifz' => 10,
            'warud' => 10,
            'akhlaqi' => 10,
            'tajwid' => null,
            'note' => null,
        ]],
    ];
}

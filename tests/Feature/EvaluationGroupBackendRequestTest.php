<?php

use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\MonthlyPlan;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\AbsenceRules\AbsenceAlertExecutionLock;
use App\Services\Admin\EvaluationService;
use App\Services\Auth\PermissionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionSyncService::class)->sync();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin, 'web');
});

test('evaluation store derives the center from the selected group and rejects non members', function () {
    $actualCenter = Center::factory()->create();
    $submittedCenter = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $actualCenter->id,
        'working_days' => evaluationRequestAllWorkingDays(),
    ]);
    $otherGroup = Group::factory()->create([
        'center_id' => $actualCenter->id,
        'working_days' => evaluationRequestAllWorkingDays(),
    ]);
    createEvaluationRequestMonthlyPlan($group);
    $member = Student::factory()->active()->create([
        'center_id' => $actualCenter->id,
        'group_id' => $group->id,
    ]);
    $nonMember = Student::factory()->active()->create([
        'center_id' => $actualCenter->id,
        'group_id' => $otherGroup->id,
    ]);

    $this->post('/admin/evaluations', [
        'center_id' => $submittedCenter->id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [evaluationGroupRequestItem($member)],
    ])->assertSessionHasNoErrors();

    $evaluation = Evaluation::query()->sole();

    expect($evaluation->group_id)->toBe($group->id)
        ->and($evaluation->center_id)->toBe($actualCenter->id);

    $this->post('/admin/evaluations', [
        'center_id' => $actualCenter->id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [evaluationGroupRequestItem($member)],
    ])->assertSessionHasErrors('date');

    $this->post('/admin/evaluations', [
        'center_id' => $actualCenter->id,
        'group_id' => $group->id,
        'date' => '2026-08-31',
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [evaluationGroupRequestItem($nonMember)],
    ])->assertSessionHasErrors('items.0.student_id');
});

test('evaluation update accepts historical rows after group membership is removed', function () {
    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => evaluationRequestAllWorkingDays(),
    ]);
    $otherGroup = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => evaluationRequestAllWorkingDays(),
    ]);
    $historicalStudent = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
    ]);
    $nonMember = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $otherGroup->id,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
    ]);
    EvaluationStudent::factory()->present()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $historicalStudent->id,
        'user_id' => $historicalStudent->id,
    ]);

    $historicalStudent->groups()->detach($group->id);
    $historicalStudent->update(['group_id' => null]);

    $this->put("/admin/evaluations/{$evaluation->id}", [
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [evaluationGroupRequestItem($historicalStudent, 8)],
    ])->assertSessionHasNoErrors();

    expect(EvaluationStudent::query()
        ->where('evaluation_id', $evaluation->id)
        ->where('student_id', $historicalStudent->id)
        ->value('alhifz'))->toBe(8);

    $this->put("/admin/evaluations/{$evaluation->id}", [
        'evaluation_type' => Evaluation::TYPE_ALHIFZ,
        'items' => [evaluationGroupRequestItem($nonMember)],
    ])->assertSessionHasErrors('items.0.student_id');
});

test('evaluation records can filter and sort by group while returning both hierarchy names', function () {
    $center = Center::factory()->create(['name' => 'Main Center']);
    $firstGroup = Group::factory()->create(['center_id' => $center->id, 'name' => 'Alpha Group']);
    $secondGroup = Group::factory()->create(['center_id' => $center->id, 'name' => 'Beta Group']);

    Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $firstGroup->id,
        'date' => '2026-08-29',
    ]);
    Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $secondGroup->id,
        'date' => '2026-08-30',
    ]);

    $response = $this->getJson('/admin/evaluations/records?group_id='.$secondGroup->id.'&sort_by=group_name&sort_dir=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.center_name', 'Main Center')
        ->assertJsonPath('data.0.group_name', 'Beta Group')
        ->assertJsonPath('data.0.group_id', $secondGroup->id);

    expect((string) $response->json('data.0.date_formatted'))
        ->toMatch('/^.+ ، 30\/08\/2026$/u');
});

test('an evaluation cannot be updated or deleted while its absence alerts are processing', function () {
    $evaluation = Evaluation::factory()->create();
    $lock = app(AbsenceAlertExecutionLock::class)->acquire($evaluation->id);
    $service = app(EvaluationService::class);

    expect($lock)->not->toBeNull();

    try {
        expect(fn () => $service->update($evaluation, [
            'evaluation_type' => Evaluation::TYPE_ALHIFZ,
            'items' => [],
        ]))->toThrow(ValidationException::class)
            ->and(fn () => $service->delete($evaluation))->toThrow(ValidationException::class);
    } finally {
        $lock?->release();
    }

    expect($evaluation->fresh())->not->toBeNull();
});

/**
 * @return array<string, mixed>
 */
function evaluationGroupRequestItem(Student $student, int $score = 10): array
{
    return [
        'student_id' => $student->id,
        'attendances' => EvaluationStudent::ATTENDANCE_PRESENT,
        'alhifz' => $score,
        'warud' => 10,
        'akhlaqi' => 10,
        'tajwid' => null,
        'note' => null,
    ];
}

/** @return array<int, string> */
function evaluationRequestAllWorkingDays(): array
{
    return ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
}

function createEvaluationRequestMonthlyPlan(Group $group): MonthlyPlan
{
    return MonthlyPlan::query()->create([
        'month' => 8,
        'year' => 2026,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'center_id' => $group->center_id,
        'group_id' => $group->id,
    ]);
}

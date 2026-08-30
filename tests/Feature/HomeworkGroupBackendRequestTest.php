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
use App\Services\Admin\GroupService;
use App\Services\Admin\HomeworkService;
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

test('homework creation derives center and scopes students and uniqueness to group', function () {
    $actualCenter = Center::factory()->create(['name' => 'Main Center']);
    $submittedCenter = Center::factory()->create();
    $firstGroup = Group::factory()->create([
        'center_id' => $actualCenter->id,
        'name' => 'Alpha Group',
        'working_days' => allHomeworkWorkingDays(),
    ]);
    $secondGroup = Group::factory()->create([
        'center_id' => $actualCenter->id,
        'name' => 'Beta Group',
        'working_days' => allHomeworkWorkingDays(),
    ]);
    $firstStudent = Student::factory()->active()->create([
        'center_id' => $actualCenter->id,
        'group_id' => $firstGroup->id,
    ]);
    $secondStudent = Student::factory()->active()->create([
        'center_id' => $actualCenter->id,
        'group_id' => $secondGroup->id,
    ]);
    $service = app(HomeworkService::class);

    $payload = $service->createFormPayload($actualCenter->id, $firstGroup->id, '2026-08-30');

    expect(collect($payload['students'])->pluck('student_id')->all())
        ->toBe([$firstStudent->id])
        ->not->toContain($secondStudent->id);

    $this->post('/admin/homeworks', homeworkGroupPayload($submittedCenter, $firstGroup, $firstStudent))
        ->assertSessionHasNoErrors();
    $this->post('/admin/homeworks', homeworkGroupPayload($actualCenter, $secondGroup, $secondStudent))
        ->assertSessionHasNoErrors();

    $firstHomework = Homework::query()->where('group_id', $firstGroup->id)->sole();

    expect($firstHomework->center_id)->toBe($actualCenter->id)
        ->and(Homework::query()->whereDate('date', '2026-08-30')->count())->toBe(2);

    $this->post('/admin/homeworks', homeworkGroupPayload($actualCenter, $firstGroup, $firstStudent))
        ->assertSessionHasErrors('date');

    $this->post('/admin/homeworks', [
        ...homeworkGroupPayload($actualCenter, $firstGroup, $secondStudent),
        'date' => '2026-08-31',
    ])->assertSessionHasErrors('items.0.student_id');

    $this->getJson('/admin/homeworks/records?group_id='.$secondGroup->id.'&sort_by=group_name&sort_dir=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.center_name', 'Main Center')
        ->assertJsonPath('data.0.group_name', 'Beta Group')
        ->assertJsonPath('data.0.group_id', $secondGroup->id);
});

test('homework update accepts historical students but rejects unrelated students', function () {
    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => allHomeworkWorkingDays(),
    ]);
    $otherGroup = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => allHomeworkWorkingDays(),
    ]);
    $historicalStudent = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
    ]);
    $unrelatedStudent = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $otherGroup->id,
    ]);

    $homework = app(HomeworkService::class)->create(homeworkGroupPayload($center, $group, $historicalStudent));
    $historicalStudent->groups()->detach($group->id);
    $historicalStudent->update(['group_id' => null]);

    $this->put("/admin/homeworks/{$homework->id}", [
        'items' => [homeworkGroupItem($historicalStudent, 3)],
    ])->assertSessionHasNoErrors();

    expect($homework->students()->where('student_id', $historicalStudent->id)->value('points_adjustment'))
        ->toBe(3);

    $this->put("/admin/homeworks/{$homework->id}", [
        'items' => [homeworkGroupItem($unrelatedStudent)],
    ])->assertSessionHasErrors('items.0.student_id');
});

test('homework service rejects duplicate group dates', function () {
    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => allHomeworkWorkingDays(),
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
    ]);
    $service = app(HomeworkService::class);
    $payload = homeworkGroupPayload($center, $group, $student);

    $service->create($payload);

    expect(fn () => $service->create($payload))->toThrow(ValidationException::class);
});

test('a group with homework history cannot be moved or deleted', function () {
    $center = Center::factory()->create();
    $otherCenter = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'working_days' => allHomeworkWorkingDays(),
    ]);
    Homework::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
    ]);
    $service = app(GroupService::class);

    expect(fn () => $service->update($group, [
        'name' => $group->name,
        'center_id' => $otherCenter->id,
        'group_serialized' => null,
        'working_days' => allHomeworkWorkingDays(),
    ]))->toThrow(ValidationException::class)
        ->and(fn () => $service->delete($group))->toThrow(ValidationException::class);
});

test('public group homework report never leaks a newer assignment from another group', function () {
    $center = Center::factory()->create();
    $reportGroup = Group::factory()->create([
        'center_id' => $center->id,
        'ulid' => '01K4HOMEWORKREPORTGROUP000',
    ]);
    $otherGroup = Group::factory()->create(['center_id' => $center->id]);
    $plan = Plan::factory()->create();
    $reportPoint = PlanPoint::factory()->create(['plan_id' => $plan->id, 'name' => 'Report group point']);
    $otherPoint = PlanPoint::factory()->create(['plan_id' => $plan->id, 'name' => 'Other group point']);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $reportGroup->id,
        'plan_type_id' => $plan->id,
    ]);
    $student->groups()->sync([$reportGroup->id]);

    $reportHomework = Homework::factory()->create([
        'center_id' => $center->id,
        'group_id' => $reportGroup->id,
        'date' => '2026-08-01',
    ]);
    $otherHomework = Homework::factory()->create([
        'center_id' => $center->id,
        'group_id' => $otherGroup->id,
        'date' => '2026-08-02',
    ]);

    foreach ([[$reportHomework, $reportPoint], [$otherHomework, $otherPoint]] as [$homework, $point]) {
        $homeworkStudent = HomeworkStudent::query()->create([
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'points_balance_before' => 0,
            'points_adjustment' => 0,
            'points_balance_after' => 0,
        ]);
        HomeworkStudentPoint::query()->create([
            'homework_student_id' => $homeworkStudent->id,
            'homework_id' => $homework->id,
            'student_id' => $student->id,
            'plan_point_id' => $point->id,
            'sort_order' => 1,
            'is_done' => false,
            'is_next_homework' => true,
            'awarded_points' => 0,
        ]);
    }

    $row = app(GroupService::class)->homeworkReportPayload($reportGroup->ulid)['rows'][0];

    expect(collect($row['tasks'])->pluck('name')->all())
        ->toBe(['Report group point'])
        ->not->toContain('Other group point');
});

/** @return array<string, mixed> */
function homeworkGroupPayload(Center $center, Group $group, Student $student): array
{
    return [
        'center_id' => $center->id,
        'group_id' => $group->id,
        'date' => '2026-08-30',
        'items' => [homeworkGroupItem($student)],
    ];
}

/** @return array<string, mixed> */
function homeworkGroupItem(Student $student, int $adjustment = 0): array
{
    return [
        'student_id' => $student->id,
        'points_adjustment' => $adjustment,
        'points' => [],
    ];
}

/** @return array<int, string> */
function allHomeworkWorkingDays(): array
{
    return ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
}

<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Models\User;
use App\Services\Admin\StudentCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     student: Student,
 *     checkpoint: PlanPoint,
 *     first_later_point: PlanPoint,
 *     furthest_point: PlanPoint
 * }
 */
function certificateAchievementDateRegressionFixture(): array
{
    $user = User::factory()->create();
    $center = Center::factory()->create([
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $group = Group::factory()->create(['center_id' => $center->id]);
    $plan = Plan::factory()->quran()->create();
    $checkpoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'name' => 'نقطة شهادة أقدم',
        'requires_certificate' => true,
        'surah_name' => 'مريم',
    ]);
    $firstLaterPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 20,
        'name' => 'نقطة لاحقة أولى',
    ]);
    $furthestPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 30,
        'name' => 'نقطة لاحقة أبعد',
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $furthestPoint->id,
        'admin_id' => $user->id,
    ]);

    return [
        'student' => $student,
        'checkpoint' => $checkpoint,
        'first_later_point' => $firstLaterPoint,
        'furthest_point' => $furthestPoint,
    ];
}

function recordCertificateAchievementDateRegressionCompletion(
    Student $student,
    PlanPoint $point,
    string $completedAt,
): StudentPointTransaction {
    $transaction = new StudentPointTransaction([
        'student_id' => $student->id,
        'plan_point_id' => $point->id,
        'type' => StudentPointTransaction::TYPE_HOMEWORK_COMPLETED,
        'points' => 1,
        'balance_before' => 0,
        'balance_after' => 1,
        'created_by' => $student->admin_id,
    ]);
    $transaction->created_at = Carbon::parse($completedAt, 'UTC');
    $transaction->updated_at = Carbon::parse($completedAt, 'UTC');
    $transaction->save();

    return $transaction->refresh();
}

test('issuing an older checkpoint uses the first qualifying later completion date', function () {
    $fixture = certificateAchievementDateRegressionFixture();

    recordCertificateAchievementDateRegressionCompletion(
        $fixture['student'],
        $fixture['furthest_point'],
        '2026-08-15 09:00:00',
    );
    $firstEvidence = recordCertificateAchievementDateRegressionCompletion(
        $fixture['student'],
        $fixture['first_later_point'],
        '2026-08-10 09:00:00',
    );

    $certificate = app(StudentCertificateService::class)->issue(
        $fixture['student'],
        (int) $fixture['checkpoint']->id,
    );

    expect($certificate->achieved_at?->toDateTimeString())
        ->toBe($firstEvidence->created_at?->toDateTimeString())
        ->and($certificate->gregorian_date)
        ->toBe('٢٠٢٦/٠٨/١٠');
});

test('a progressed pointer without a completion transaction cannot issue a certificate', function () {
    $fixture = certificateAchievementDateRegressionFixture();

    try {
        app(StudentCertificateService::class)->issue(
            $fixture['student'],
            (int) $fixture['checkpoint']->id,
        );

        $this->fail('Certificate issuance unexpectedly succeeded without completion evidence.');
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('plan_point_id')
            ->and($exception->errors()['plan_point_id'])
            ->toContain(__('certificates.achievement_date_not_documented'));
    }

    expect(Certificate::query()->count())->toBe(0);
});

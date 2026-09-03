<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\Device;
use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Models\User;
use App\Services\Admin\BulkCertificateDeliveryService;
use App\Services\Admin\CertificatePdfRenderer;
use App\Services\Admin\StudentCertificateService;
use App\Services\System\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $permissions
 */
function bulkCertificateActor(array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $actor = User::factory()->create();
    $actor->givePermissionTo($permissions);

    return $actor;
}

/** @return array{center: Center, group: Group} */
function bulkCertificateLocation(): array
{
    $center = Center::factory()->create();
    $group = Group::factory()->create(['center_id' => $center->id]);

    return compact('center', 'group');
}

function bulkCertificatePoint(
    Plan $plan,
    int $sortOrder = 10,
    bool $requiresCertificate = true,
    ?string $name = null,
): PlanPoint {
    return PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => $sortOrder,
        'name' => $name ?? "Checkpoint {$plan->id}-{$sortOrder}",
        'requires_certificate' => $requiresCertificate,
        'surah_name' => $requiresCertificate ? "Surah {$plan->id}-{$sortOrder}" : null,
    ]);
}

function bulkCertificateStudent(
    Center $center,
    Group $group,
    Plan $plan,
    ?PlanPoint $currentPoint,
    int $status = Student::STATUS_ACTIVE,
    ?string $parentPhone = '0790000000',
    ?string $studentPhone = null,
    ?string $name = null,
): Student {
    return Student::factory()->create([
        'full_name' => $name ?? "Bulk student {$status}",
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $currentPoint?->id,
        'is_active' => $status,
        'parent_phone_number' => $parentPhone,
        'phone_number' => $studentPhone,
    ]);
}

function bulkCertificateTransaction(
    Student $student,
    PlanPoint $point,
    string $createdAtUtc,
    string $type = StudentPointTransaction::TYPE_HOMEWORK_COMPLETED,
): StudentPointTransaction {
    $transaction = new StudentPointTransaction([
        'student_id' => $student->id,
        'plan_point_id' => $point->id,
        'type' => $type,
        'points' => 1,
        'balance_before' => 0,
        'balance_after' => 1,
        'created_by' => $student->admin_id,
    ]);
    $transaction->created_at = Carbon::parse($createdAtUtc, 'UTC');
    $transaction->updated_at = Carbon::parse($createdAtUtc, 'UTC');
    $transaction->save();

    return $transaction->refresh();
}

/** @return array<string, mixed> */
function bulkCertificateExecutionOptions(User $actor, string $stage): array
{
    return [
        '--before' => '2026-09-01',
        '--stage' => $stage,
        '--execute' => true,
        '--actor' => $actor->id,
        '--min-delay' => 0,
        '--max-delay' => 0,
        '--yes' => true,
    ];
}

beforeEach(function (): void {
    app(SystemSettingsService::class)->update(['timezone' => 'Asia/Amman']);
});

test('bulk certificate dry run is read only and never prepares or sends WhatsApp media', function () {
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $point = bulkCertificatePoint($plan);
    $student = bulkCertificateStudent($center, $group, $plan, $point);
    bulkCertificateTransaction($student, $point, '2026-08-20 08:00:00');

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('render');
    });
    Http::fake();

    $this->artisan('certificates:bulk-deliver', ['--before' => '2026-09-01'])
        ->expectsOutputToContain('Missing certificates to issue: 1')
        ->expectsOutputToContain('Students in the selected cohort: 1')
        ->expectsOutputToContain('DRY RUN: no certificates were issued and no WhatsApp messages were sent.')
        ->assertSuccessful();

    expect(Certificate::query()->count())->toBe(0)
        ->and(StudentPointTransaction::query()->count())->toBe(1);
    Http::assertNothingSent();
});

test('bulk cutoff is exclusive midnight in the configured Amman timezone', function () {
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $point = bulkCertificatePoint($plan);

    $beforeBoundary = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0790000001',
        name: 'Before boundary',
    );
    $atBoundary = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0790000002',
        name: 'At boundary',
    );
    $afterBoundary = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0790000003',
        name: 'After boundary',
    );

    bulkCertificateTransaction($beforeBoundary, $point, '2026-08-31 20:59:59');
    bulkCertificateTransaction($atBoundary, $point, '2026-08-31 21:00:00');
    bulkCertificateTransaction($afterBoundary, $point, '2026-08-31 23:30:00');

    $this->artisan('certificates:bulk-deliver', ['--before' => '2026-09-01'])
        ->expectsOutputToContain('Cutoff UTC: 2026-08-31 21:00:00')
        ->expectsOutputToContain('Missing certificates to issue: 1')
        ->assertSuccessful();

    expect(Certificate::query()->count())->toBe(0);
});

test('bulk candidates infer earlier certificate checkpoints from later progress for active students only', function () {
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $certificatePoint = bulkCertificatePoint($plan, 10, true);
    $ordinaryPoint = bulkCertificatePoint($plan, 20, false);

    $eligible = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $certificatePoint,
        parentPhone: '0790000010',
        name: 'Eligible active student',
    );
    $inactive = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $certificatePoint,
        Student::STATUS_INACTIVE,
        '0790000011',
        name: 'Inactive student',
    );
    $frozen = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $certificatePoint,
        Student::STATUS_FROZEN,
        '0790000012',
        name: 'Frozen student',
    );
    $manualOnly = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $certificatePoint,
        parentPhone: '0790000013',
        name: 'Manual adjustment only',
    );
    bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $certificatePoint,
        parentPhone: '0790000014',
        name: 'Pointer only without transaction',
    );
    $ordinaryCompletion = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $ordinaryPoint,
        parentPhone: '0790000015',
        name: 'Ordinary completion',
    );

    $eligibleTransaction = bulkCertificateTransaction($eligible, $certificatePoint, '2026-08-15 10:00:00');
    bulkCertificateTransaction($inactive, $certificatePoint, '2026-08-15 10:00:00');
    bulkCertificateTransaction($frozen, $certificatePoint, '2026-08-15 10:00:00');
    bulkCertificateTransaction(
        $manualOnly,
        $certificatePoint,
        '2026-08-15 10:00:00',
        StudentPointTransaction::TYPE_HOMEWORK_MANUAL_ADJUSTMENT,
    );
    bulkCertificateTransaction($ordinaryCompletion, $ordinaryPoint, '2026-08-15 10:00:00');

    $candidates = app(BulkCertificateDeliveryService::class)
        ->missingIssueCandidates(Carbon::parse('2026-08-31 21:00:00', 'UTC'));

    expect($candidates->map(static fn (StudentPointTransaction $transaction): array => [
        (int) $transaction->student_id,
        (int) $transaction->getAttribute('bulk_certificate_plan_point_id'),
    ])->all())->toBe([
        [$eligible->id, $certificatePoint->id],
        [$ordinaryCompletion->id, $certificatePoint->id],
    ])
        ->and($candidates->firstWhere('student_id', $eligible->id)?->id)
        ->toBe($eligibleTransaction->id);

    $this->artisan('certificates:bulk-deliver', ['--before' => '2026-09-01'])
        ->expectsOutputToContain('Active students in the system: 4')
        ->expectsOutputToContain('Missing certificates to issue: 2')
        ->assertSuccessful();
});

test('historical issuance uses the completed old plan and is idempotent under the command actor', function () {
    $actor = bulkCertificateActor(['students.update']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $oldPlan = Plan::factory()->create(['name' => 'Old historical plan']);
    $oldPoint = bulkCertificatePoint($oldPlan, 90, true, 'Old plan checkpoint');
    $currentPlan = Plan::factory()->create(['name' => 'Current transferred plan']);
    $currentPoint = bulkCertificatePoint($currentPlan, 1, false, 'Current plan beginning');
    $student = bulkCertificateStudent(
        $center,
        $group,
        $currentPlan,
        $currentPoint,
        parentPhone: '0790000020',
        name: 'Transferred historical student',
    );
    $transaction = bulkCertificateTransaction($student, $oldPoint, '2026-08-10 10:15:00');

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'issue'),
    )
        ->expectsOutputToContain('Issue summary: checked=1 issued=1 already=0 failed=0')
        ->assertSuccessful();

    $certificate = Certificate::query()->sole();
    expect($certificate->student_id)->toBe($student->id)
        ->and($certificate->plan_point_id)->toBe($oldPoint->id)
        ->and($certificate->plan_name)->toBe($oldPlan->name)
        ->and($certificate->achieved_at?->toDateTimeString())->toBe($transaction->created_at?->toDateTimeString())
        ->and($certificate->issued_by)->toBe($actor->id)
        ->and(Auth::guard('web')->guest())->toBeTrue();

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'issue'),
    )
        ->expectsOutputToContain('Issue summary: checked=0 issued=0 already=0 failed=0')
        ->assertSuccessful();

    expect(Certificate::query()->count())->toBe(1);
});

test('cumulative issuance stays inside each historical plan and uses the earliest proving completion', function () {
    $actor = bulkCertificateActor(['students.update']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $historicalPlan = Plan::factory()->create(['name' => 'Historical cumulative plan']);
    $firstCheckpoint = bulkCertificatePoint($historicalPlan, 10, true, 'First historical checkpoint');
    $secondCheckpoint = bulkCertificatePoint($historicalPlan, 20, true, 'Second historical checkpoint');
    $laterOrdinaryPoint = bulkCertificatePoint($historicalPlan, 30, false, 'Later ordinary point');
    $currentPlan = Plan::factory()->create(['name' => 'Current unrelated plan']);
    $currentCheckpoint = bulkCertificatePoint($currentPlan, 5, true, 'Unreached current checkpoint');
    $student = bulkCertificateStudent(
        $center,
        $group,
        $currentPlan,
        $currentCheckpoint,
        parentPhone: '0790000021',
        name: 'Historical cumulative student',
    );

    $provingTransaction = bulkCertificateTransaction(
        $student,
        $laterOrdinaryPoint,
        '2026-08-10 09:00:00',
    );
    bulkCertificateTransaction($student, $secondCheckpoint, '2026-08-15 09:00:00');

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'issue'),
    )
        ->expectsOutputToContain('Issue summary: checked=2 issued=2 already=0 failed=0')
        ->assertSuccessful();

    $certificates = Certificate::query()->orderBy('plan_point_id')->get();
    expect($certificates)->toHaveCount(2)
        ->and($certificates->pluck('plan_point_id')->all())->toBe([
            $firstCheckpoint->id,
            $secondCheckpoint->id,
        ])
        ->and($certificates->pluck('plan_name')->unique()->all())->toBe([$historicalPlan->name])
        ->and($certificates->every(
            static fn (Certificate $certificate): bool => $certificate->achieved_at?->toDateTimeString()
                    === $provingTransaction->created_at?->toDateTimeString(),
        ))->toBeTrue()
        ->and($certificates->contains('plan_point_id', $currentCheckpoint->id))->toBeFalse();
});

test('bulk issuance order is plan then point sort and id then student id', function () {
    $actor = bulkCertificateActor(['students.update']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $firstPlan = Plan::factory()->create(['name' => 'First plan']);
    $firstPoint = bulkCertificatePoint($firstPlan, 10, true, 'First equal point');
    $secondEqualPoint = bulkCertificatePoint($firstPlan, 10, true, 'Second equal point');
    $laterPoint = bulkCertificatePoint($firstPlan, 20, true, 'Later point');
    $secondPlan = Plan::factory()->create(['name' => 'Second plan']);
    $secondPlanEarlyPoint = bulkCertificatePoint($secondPlan, 1, true, 'Second plan early point');

    $firstStudent = bulkCertificateStudent(
        $center,
        $group,
        $secondPlan,
        $secondPlanEarlyPoint,
        parentPhone: '0790000030',
        name: 'First student',
    );
    $secondStudent = bulkCertificateStudent(
        $center,
        $group,
        $secondPlan,
        $secondPlanEarlyPoint,
        parentPhone: '0790000031',
        name: 'Second student',
    );

    // Insert deliberately in the opposite order to prove query ordering.
    bulkCertificateTransaction($firstStudent, $secondPlanEarlyPoint, '2026-08-20 10:00:00');
    bulkCertificateTransaction($firstStudent, $laterPoint, '2026-08-20 10:00:00');
    bulkCertificateTransaction($firstStudent, $secondEqualPoint, '2026-08-20 10:00:00');
    bulkCertificateTransaction($secondStudent, $firstPoint, '2026-08-20 10:00:00');
    bulkCertificateTransaction($firstStudent, $firstPoint, '2026-08-20 10:00:00');

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'issue'),
    )->assertSuccessful();

    $actualOrder = Certificate::query()
        ->orderBy('id')
        ->get(['plan_point_id', 'student_id'])
        ->map(static fn (Certificate $certificate): array => [
            (int) $certificate->plan_point_id,
            (int) $certificate->student_id,
        ])
        ->all();

    expect($actualOrder)->toBe([
        [$firstPoint->id, $firstStudent->id],
        [$firstPoint->id, $secondStudent->id],
        [$secondEqualPoint->id, $firstStudent->id],
        [$laterPoint->id, $firstStudent->id],
        [$secondPlanEarlyPoint->id, $firstStudent->id],
    ]);
});

test('bulk send skips an unregistered number and continues with registered recipients without delay in tests', function () {
    $actor = bulkCertificateActor(['students.update', 'certificates.send']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $point = bulkCertificatePoint($plan);

    $unregistered = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0791111111',
        name: 'Unregistered recipient',
    );
    $mixed = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0792222222',
        studentPhone: '0782222222',
        name: 'Mixed recipients',
    );
    $registered = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0793333333',
        name: 'Registered recipient',
    );

    foreach ([$unregistered, $mixed, $registered] as $student) {
        bulkCertificateTransaction($student, $point, '2026-08-20 10:00:00');
    }

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'issue'),
    )->assertSuccessful();

    Device::factory()->connected()->create(['session_id' => 'bulk-certificate-session']);
    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->twice()
            ->andReturn("%PDF-1.7\nbulk-certificate");
    });

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response([
                'success' => true,
                'result' => in_array($request['number'], [
                    '962782222222',
                    '962793333333',
                ], true),
            ]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            return Http::response(['success' => true]);
        }

        return Http::response([], 404);
    });

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'send'),
    )
        ->expectsOutputToContain('Send summary: checked=3 sent=2 partial=0 review=0 inactive=0 unregistered=1 no_recipient=0 validation_failed=0 failed=0')
        ->expectsOutputToContain('Completed successfully. Inactive students and unavailable WhatsApp recipients were skipped.')
        ->assertSuccessful();

    $certificates = Certificate::query()->orderBy('student_id')->get()->keyBy('student_id');
    expect($certificates[$unregistered->id]->whatsapp_delivery_status)->toBeNull()
        ->and($certificates[$unregistered->id]->whatsapp_sent_at)->toBeNull()
        ->and($certificates[$mixed->id]->whatsapp_delivery_status)->toBe(Certificate::WHATSAPP_DELIVERY_SENT)
        ->and($certificates[$mixed->id]->whatsapp_sent_at)->not->toBeNull()
        ->and($certificates[$registered->id]->whatsapp_delivery_status)->toBe(Certificate::WHATSAPP_DELIVERY_SENT)
        ->and($certificates[$registered->id]->whatsapp_sent_at)->not->toBeNull()
        ->and($certificates[$mixed->id]->whatsapp_sent_by)->toBe($actor->id)
        ->and($certificates[$registered->id]->whatsapp_sent_by)->toBe($actor->id);

    $sentChatIds = collect(Http::recorded())
        ->map(static fn (array $record): Request => $record[0])
        ->filter(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/'))
        ->map(static fn (Request $request): string => (string) $request['chatId'])
        ->values()
        ->all();

    expect($sentChatIds)->toBe([
        '962782222222@s.whatsapp.net',
        '962793333333@s.whatsapp.net',
    ]);
});

test('bulk execution requires an actor with every permission for the selected stage', function () {
    $insufficientActor = bulkCertificateActor(['students.update']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $point = bulkCertificatePoint($plan);
    $student = bulkCertificateStudent($center, $group, $plan, $point);
    bulkCertificateTransaction($student, $point, '2026-08-20 08:00:00');

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('render');
    });
    Http::fake();

    $this->artisan('certificates:bulk-deliver', [
        '--before' => '2026-09-01',
        '--stage' => 'all',
        '--execute' => true,
        '--min-delay' => 0,
        '--max-delay' => 0,
        '--yes' => true,
    ])
        ->expectsOutputToContain('The --actor=USER_ID option is required with --execute.')
        ->assertFailed();

    $this->artisan('certificates:bulk-deliver', [
        ...bulkCertificateExecutionOptions($insufficientActor, 'all'),
    ])
        ->expectsOutputToContain('The selected actor is missing permissions: certificates.send')
        ->assertFailed();

    expect(Certificate::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('bulk execution respects the global lock and resumes after its owner releases it', function () {
    $actor = bulkCertificateActor(['students.update']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $point = bulkCertificatePoint($plan);
    $student = bulkCertificateStudent($center, $group, $plan, $point);
    bulkCertificateTransaction($student, $point, '2026-08-20 08:00:00');

    $heldLock = Cache::lock('certificates:bulk-deliver', 300);
    expect($heldLock->get())->toBeTrue();

    try {
        $this->artisan(
            'certificates:bulk-deliver',
            bulkCertificateExecutionOptions($actor, 'issue'),
        )
            ->expectsOutputToContain('Another bulk certificate command is already running.')
            ->assertFailed();

        expect(Certificate::query()->count())->toBe(0);
    } finally {
        $heldLock->release();
    }

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'issue'),
    )
        ->expectsOutputToContain('Issue summary: checked=1 issued=1 already=0 failed=0')
        ->assertSuccessful();

    expect(Certificate::query()->count())->toBe(1);
});

test('bulk send reruns never resend sent certificates or certificates awaiting review', function () {
    $actor = bulkCertificateActor(['students.update', 'certificates.send']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $point = bulkCertificatePoint($plan);
    $sendableStudent = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0794444444',
        name: 'Send only once',
    );
    $reviewStudent = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0795555555',
        name: 'Needs review',
    );
    bulkCertificateTransaction($sendableStudent, $point, '2026-08-20 08:00:00');
    bulkCertificateTransaction($reviewStudent, $point, '2026-08-20 08:00:00');

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'issue'),
    )->assertSuccessful();

    $sendableCertificate = Certificate::query()
        ->where('student_id', $sendableStudent->id)
        ->firstOrFail();
    $reviewCertificate = Certificate::query()
        ->where('student_id', $reviewStudent->id)
        ->firstOrFail();
    $reviewCertificate->forceFill([
        'whatsapp_delivery_status' => Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED,
        'whatsapp_image_filename' => 'review-required.pdf',
    ])->save();

    Device::factory()->connected()->create(['session_id' => 'bulk-rerun-session']);
    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->andReturn("%PDF-1.7\nbulk-rerun");
    });
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            return Http::response(['success' => true]);
        }

        return Http::response([], 404);
    });

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'send'),
    )
        ->expectsOutputToContain('Send summary: checked=1 sent=1 partial=0 review=0 inactive=0 unregistered=0 no_recipient=0 validation_failed=0 failed=0')
        ->assertSuccessful();

    $requestCountAfterFirstRun = count(Http::recorded());
    expect($requestCountAfterFirstRun)->toBeGreaterThan(0)
        ->and($sendableCertificate->refresh()->whatsapp_delivery_status)->toBe(Certificate::WHATSAPP_DELIVERY_SENT)
        ->and($reviewCertificate->refresh()->whatsapp_delivery_status)->toBe(Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED);

    $pendingIds = app(BulkCertificateDeliveryService::class)
        ->pendingSendQuery(Carbon::parse('2026-08-31 21:00:00', 'UTC'))
        ->pluck('certificates.id')
        ->all();
    expect($pendingIds)->toBe([]);

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'send'),
    )
        ->expectsOutputToContain('Send summary: checked=0 sent=0 partial=0 review=0 inactive=0 unregistered=0 no_recipient=0 validation_failed=0 failed=0')
        ->assertSuccessful();

    expect(Http::recorded())->toHaveCount($requestCountAfterFirstRun);
});

test('bulk send refreshes student status and phone data immediately before each long-running delivery', function () {
    $actor = bulkCertificateActor(['students.update', 'certificates.send']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $point = bulkCertificatePoint($plan);
    $firstStudent = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0796666666',
        name: 'First current recipient',
    );
    $laterInactiveStudent = bulkCertificateStudent(
        $center,
        $group,
        $plan,
        $point,
        parentPhone: '0797777777',
        name: 'Later inactive recipient',
    );
    bulkCertificateTransaction($firstStudent, $point, '2026-08-20 08:00:00');
    bulkCertificateTransaction($laterInactiveStudent, $point, '2026-08-20 08:00:00');

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'issue'),
    )->assertSuccessful();

    Device::factory()->connected()->create(['session_id' => 'bulk-current-student-session']);
    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->andReturn("%PDF-1.7\nbulk-current-student");
    });
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            return Http::response(['success' => true]);
        }

        return Http::response([], 404);
    });
    config([
        'services.whatsapp_api.message_delay_seconds' => 0,
        'services.whatsapp_api.message_delay_max_seconds' => 0,
    ]);

    $this->actingAs($actor, 'web');
    $summary = app(BulkCertificateDeliveryService::class)->sendPending(
        Carbon::parse('2026-08-31 21:00:00', 'UTC'),
        onProgress: function (array $event) use ($laterInactiveStudent): void {
            if ($event['index'] === 1) {
                $laterInactiveStudent->update(['is_active' => Student::STATUS_INACTIVE]);
            }
        },
    );

    expect($summary['sent'])->toBe(1)
        ->and($summary['inactive'])->toBe(1)
        ->and($summary['checked'])->toBe(2);
    Http::assertSentCount(2); // one registration check and one PDF message
});

test('bulk rerun safely reconciles a stale processing claim to review without resending', function () {
    $actor = bulkCertificateActor(['students.update', 'certificates.send']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $point = bulkCertificatePoint($plan);
    $student = bulkCertificateStudent($center, $group, $plan, $point);
    bulkCertificateTransaction($student, $point, '2026-08-20 08:00:00');

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'issue'),
    )->assertSuccessful();

    $certificate = Certificate::query()->sole();
    DB::table('certificates')->where('id', $certificate->id)->update([
        'whatsapp_delivery_status' => Certificate::WHATSAPP_DELIVERY_PROCESSING,
        'updated_at' => now()->subMinutes(Certificate::WHATSAPP_PROCESSING_STALE_AFTER_MINUTES + 1),
    ]);
    $student->update([
        'parent_phone_number' => null,
        'phone_number' => null,
    ]);

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('render');
    });
    Http::fake();

    $this->artisan(
        'certificates:bulk-deliver',
        bulkCertificateExecutionOptions($actor, 'send'),
    )
        ->expectsOutputToContain('Send summary: checked=1 sent=0 partial=0 review=1 inactive=0 unregistered=0 no_recipient=0 validation_failed=0 failed=0')
        ->assertFailed();

    expect($certificate->refresh()->whatsapp_delivery_status)
        ->toBe(Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED);
    Http::assertNothingSent();
});

test('bulk stale lock can be explicitly recovered only through the dedicated option', function () {
    $heldLock = Cache::lock('certificates:bulk-deliver', 300);
    expect($heldLock->get())->toBeTrue();

    $this->artisan('certificates:bulk-deliver', [
        '--recover-lock' => true,
        '--yes' => true,
    ])
        ->expectsOutputToContain('The bulk certificate lock was force-released.')
        ->assertSuccessful();

    $replacementLock = Cache::lock('certificates:bulk-deliver', 300);
    expect($replacementLock->get())->toBeTrue();
    $replacementLock->release();
});

test('manual issuance refuses pointer only progress without completion evidence', function () {
    $actor = bulkCertificateActor(['students.update']);
    ['center' => $center, 'group' => $group] = bulkCertificateLocation();
    $plan = Plan::factory()->create();
    $point = bulkCertificatePoint($plan);
    $student = bulkCertificateStudent($center, $group, $plan, $point);

    Auth::guard('web')->login($actor);
    try {
        expect(fn () => app(StudentCertificateService::class)->issue($student, (int) $point->id))
            ->toThrow(ValidationException::class);
    } finally {
        Auth::guard('web')->logout();
    }

    expect(StudentPointTransaction::query()->count())->toBe(0)
        ->and(Certificate::query()->count())->toBe(0);
});

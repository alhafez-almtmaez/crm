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
use App\Services\System\StudentCertificatePortalService;
use App\Services\System\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @return array{actor: User, student: Student, plan: Plan, point: PlanPoint}
 */
function studentCertificatePortalWhatsAppFixture(
    ?string $parentPhone = '0791111111',
    ?string $studentPhone = '0782222222',
): array {
    foreach (['students.update', 'certificates.send'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['students.update', 'certificates.send']);
    $center = Center::factory()->create();
    $group = Group::factory()->create(['center_id' => $center->id]);
    $plan = Plan::factory()->quran()->create();
    $point = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'requires_certificate' => true,
        'surah_name' => 'مريم',
    ]);
    $student = Student::factory()->active()->create([
        'full_name' => 'معتصم إبراهيم شوقي جادالله',
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $point->id,
        'parent_phone_number' => $parentPhone,
        'phone_number' => $studentPhone,
        'admin_id' => $actor->id,
    ]);

    $transaction = new StudentPointTransaction([
        'student_id' => $student->id,
        'plan_point_id' => $point->id,
        'type' => StudentPointTransaction::TYPE_HOMEWORK_COMPLETED,
        'points' => 1,
        'balance_before' => 0,
        'balance_after' => 1,
        'created_by' => $actor->id,
    ]);
    $transaction->created_at = Carbon::parse('2026-08-15 10:00:00', 'UTC');
    $transaction->updated_at = Carbon::parse('2026-08-15 10:00:00', 'UTC');
    $transaction->save();

    return compact('actor', 'student', 'plan', 'point');
}

/** @return array<string, mixed> */
function studentCertificatePortalWhatsAppCommandOptions(User $actor, int $studentId): array
{
    return [
        '--before' => '2026-09-01',
        '--stage' => 'all',
        '--student-id' => $studentId,
        '--execute' => true,
        '--actor' => $actor->id,
        '--min-delay' => 0,
        '--max-delay' => 0,
        '--yes' => true,
    ];
}

beforeEach(function (): void {
    app(SystemSettingsService::class)->update([
        'timezone' => 'Asia/Amman',
        'brandName' => 'مشروع الحافظ المتميز',
    ]);
});

test('one command issues missing certificates and sends one portal link to student and guardian', function () {
    $fixture = studentCertificatePortalWhatsAppFixture();
    Device::factory()->connected()->create(['session_id' => 'portal-bulk-session']);
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            return Http::response(['success' => true, 'message' => ['id' => 'sent-message']]);
        }

        return Http::response([], 404);
    });

    $this->artisan('certificates:bulk-portal-deliver', studentCertificatePortalWhatsAppCommandOptions(
        $fixture['actor'],
        (int) $fixture['student']->id,
    ))
        ->expectsOutputToContain('Issue summary: checked=1 issued=1 already=0 failed=0')
        ->expectsOutputToContain('sent_students=1 sent_messages=2')
        ->assertSuccessful();

    $student = $fixture['student']->refresh();
    $portalUrl = app(StudentCertificatePortalService::class)->url($student);
    expect(Certificate::query()->count())->toBe(1)
        ->and($student->certificate_portal_delivery_status)->toBe(Student::CERTIFICATE_PORTAL_DELIVERY_SENT)
        ->and($student->certificate_portal_sent_at)->not->toBeNull()
        ->and($student->certificate_portal_sent_by)->toBe($fixture['actor']->id);

    $messages = collect(Http::recorded())
        ->map(static fn (array $record): Request => $record[0])
        ->filter(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/'))
        ->values();
    expect($messages)->toHaveCount(2)
        ->and($messages->map(static fn (Request $request): string => (string) $request['chatId'])->all())->toBe([
            '962791111111@s.whatsapp.net',
            '962782222222@s.whatsapp.net',
        ]);
    foreach ($messages as $message) {
        expect($message['contentType'])->toBe('string')
            ->and((string) $message['content'])->toContain($fixture['student']->full_name)
            ->and((string) $message['content'])->toContain($portalUrl)
            ->and((string) $message['content'])->not->toContain('HMT-');
    }
});

test('portal delivery is idempotent and a newly issued certificate creates a new sendable version', function () {
    $fixture = studentCertificatePortalWhatsAppFixture(studentPhone: null);
    Device::factory()->connected()->create(['session_id' => 'portal-version-session']);
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        return Http::response(['success' => true, 'message' => ['id' => 'sent-message']]);
    });
    $options = studentCertificatePortalWhatsAppCommandOptions(
        $fixture['actor'],
        (int) $fixture['student']->id,
    );

    $this->artisan('certificates:bulk-portal-deliver', $options)->assertSuccessful();
    $requestsAfterFirstSend = count(Http::recorded());

    $this->artisan('certificates:bulk-portal-deliver', $options)
        ->expectsOutputToContain('sent_students=0 sent_messages=0 already=1')
        ->assertSuccessful();
    expect(Http::recorded())->toHaveCount($requestsAfterFirstSend);

    $secondPoint = PlanPoint::factory()->create([
        'plan_id' => $fixture['plan']->id,
        'sort_order' => 20,
        'requires_certificate' => true,
        'part_name' => 'عمّ',
    ]);
    $fixture['student']->update(['current_plan_point_id' => $secondPoint->id]);
    $secondTransaction = new StudentPointTransaction([
        'student_id' => $fixture['student']->id,
        'plan_point_id' => $secondPoint->id,
        'type' => StudentPointTransaction::TYPE_HOMEWORK_COMPLETED,
        'points' => 1,
        'balance_before' => 1,
        'balance_after' => 2,
        'created_by' => $fixture['actor']->id,
    ]);
    $secondTransaction->created_at = Carbon::parse('2026-08-20 10:00:00', 'UTC');
    $secondTransaction->updated_at = Carbon::parse('2026-08-20 10:00:00', 'UTC');
    $secondTransaction->save();

    $this->artisan('certificates:bulk-portal-deliver', $options)
        ->expectsOutputToContain('Issue summary: checked=1 issued=1 already=0 failed=0')
        ->expectsOutputToContain('sent_students=1 sent_messages=1')
        ->assertSuccessful();

    expect(Certificate::query()->count())->toBe(2)
        ->and(Http::recorded())->toHaveCount($requestsAfterFirstSend + 2);
});

test('confirmed unregistered numbers are skipped without blocking the command', function () {
    $fixture = studentCertificatePortalWhatsAppFixture(studentPhone: null);
    Device::factory()->connected()->create(['session_id' => 'portal-unregistered-session']);
    Http::fake(fn (Request $request) => str_contains($request->url(), '/client/isRegisteredUser/')
        ? Http::response(['success' => true, 'result' => false])
        : Http::response(['success' => true, 'message' => ['id' => 'unexpected-send']]));

    $this->artisan('certificates:bulk-portal-deliver', studentCertificatePortalWhatsAppCommandOptions(
        $fixture['actor'],
        (int) $fixture['student']->id,
    ))
        ->expectsOutputToContain('unregistered=1')
        ->assertSuccessful();

    expect($fixture['student']->refresh()->certificate_portal_delivery_status)
        ->toBe(Student::CERTIFICATE_PORTAL_DELIVERY_UNREGISTERED);
    Http::assertSentCount(1);
});

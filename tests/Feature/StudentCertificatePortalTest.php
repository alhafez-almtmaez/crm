<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\StudentCertificateService;
use App\Services\System\StudentCertificatePortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     student: Student,
 *     valid: Certificate,
 *     revoked: Certificate,
 *     replaced: Certificate,
 *     private_phone: string,
 *     private_parent_phone: string,
 *     private_id_number: string,
 *     revoked_reason: string
 * }
 */
function studentCertificatePortalFixture(): array
{
    static $sequence = 0;

    $sequence++;
    $suffix = str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    $user = User::factory()->create();
    $center = Center::factory()->create([
        'name' => "مركز بوابة الشهادات {$suffix}",
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => "مجموعة بوابة الشهادات {$suffix}",
    ]);
    $plan = Plan::factory()->quran()->create([
        'name' => "خطة بوابة الشهادات {$suffix}",
    ]);
    $validPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'name' => "إتمام سورة مريم {$suffix}",
        'requires_certificate' => true,
        'surah_name' => 'مريم',
    ]);
    $revokedPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 20,
        'name' => "إتمام الجزء الرابع {$suffix}",
        'requires_certificate' => true,
        'part_name' => 'الجزء الرابع',
    ]);
    $replacedPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 30,
        'name' => "إتمام ثلاثة أجزاء {$suffix}",
        'requires_certificate' => true,
        'three_parts' => 'الأجزاء ١، ٢، ٣',
    ]);
    $privatePhone = '962790000'.$suffix;
    $privateParentPhone = '962791000'.$suffix;
    $privateIdNumber = "PORTAL-PRIVATE-{$suffix}";
    $student = Student::factory()->active()->create([
        'first_name' => 'أحمد',
        'second_name' => 'محمد',
        'middle_name' => 'طالب',
        'last_name' => "البوابة {$suffix}",
        'full_name' => "أحمد محمد طالب البوابة {$suffix}",
        'id_number' => $privateIdNumber,
        'phone_number' => $privatePhone,
        'parent_phone_number' => $privateParentPhone,
        'email' => "private.portal.{$suffix}@example.test",
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $replacedPoint->id,
        'admin_id' => $user->id,
    ]);
    recordStudentPlanCompletion($student, $replacedPoint);

    Auth::guard('web')->login($user);

    try {
        $certificates = app(StudentCertificateService::class);
        $valid = $certificates->issue($student, (int) $validPoint->id);
        $revoked = $certificates->issue($student, (int) $revokedPoint->id);
        $replaced = $certificates->issue($student, (int) $replacedPoint->id);
    } finally {
        Auth::guard('web')->logout();
    }

    $revokedReason = "سبب إلغاء داخلي سري {$suffix}";
    $revoked->forceFill([
        'status' => Certificate::STATUS_REVOKED,
        'revoked_at' => now(),
        'revoked_reason' => $revokedReason,
    ])->save();
    $replaced->forceFill([
        'status' => Certificate::STATUS_REPLACED,
    ])->save();

    return [
        'student' => $student->refresh(),
        'valid' => $valid->refresh(),
        'revoked' => $revoked->refresh(),
        'replaced' => $replaced->refresh(),
        'private_phone' => $privatePhone,
        'private_parent_phone' => $privateParentPhone,
        'private_id_number' => $privateIdNumber,
        'revoked_reason' => $revokedReason,
    ];
}

function studentCertificatePortalSlug(Student $student): string
{
    return app(StudentCertificatePortalService::class)->slug($student);
}

function studentCertificatePortalUrl(Student $student): string
{
    return route('certificate-portals.show', [
        studentCertificatePortalSlug($student),
        $student->certificate_portal_id,
    ]);
}

function assertStudentCertificatePortalPrivacyHeaders(TestResponse $response): void
{
    expect(strtolower((string) $response->headers->get('Cache-Control')))
        ->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))
        ->toBe('noindex, nofollow, noarchive')
        ->and($response->headers->get('Referrer-Policy'))
        ->toBe('no-referrer')
        ->and($response->headers->get('X-Content-Type-Options'))
        ->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))
        ->toBe('DENY')
        ->and((string) $response->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'");
}

test('students receive unique UUID v4 certificate portal ids and the admin payload exposes the canonical link', function () {
    $first = studentCertificatePortalFixture()['student'];
    $second = studentCertificatePortalFixture()['student'];

    expect(Str::isUuid((string) $first->certificate_portal_id, version: 4))->toBeTrue()
        ->and(Str::isUuid((string) $second->certificate_portal_id, version: 4))->toBeTrue()
        ->and($first->certificate_portal_id)->not->toBe($second->certificate_portal_id);

    $payload = app(StudentCertificateService::class)->indexPayload($first);

    expect(data_get($payload, 'student.certificate_portal_url'))
        ->toBe(studentCertificatePortalUrl($first));
});

test('the public portal is available without authentication and returns only valid certificates without private data', function () {
    $fixture = studentCertificatePortalFixture();
    $student = $fixture['student'];

    $response = $this->get(studentCertificatePortalUrl($student));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('portal.student_name', (string) $student->full_name)
            ->has('portal.certificates', 1));

    $portalJson = json_encode(
        $response->inertiaProps('portal'),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );

    expect($portalJson)
        ->toContain((string) $fixture['valid']->public_id)
        ->not->toContain((string) $fixture['revoked']->public_id)
        ->not->toContain((string) $fixture['replaced']->public_id)
        ->not->toContain($fixture['private_phone'])
        ->not->toContain($fixture['private_parent_phone'])
        ->not->toContain($fixture['private_id_number'])
        ->not->toContain($fixture['revoked_reason']);

    assertStudentCertificatePortalPrivacyHeaders($response);
});

test('a public certificate preview uses portal URLs and never renders an admin link', function () {
    $fixture = studentCertificatePortalFixture();
    $student = $fixture['student'];
    $slug = studentCertificatePortalSlug($student);
    $previewUrl = route('certificate-portals.certificates.show', [
        $slug,
        $student->certificate_portal_id,
        $fixture['valid']->public_id,
    ]);
    $pdfUrl = route('certificate-portals.certificates.pdf', [
        $slug,
        $student->certificate_portal_id,
        $fixture['valid']->public_id,
    ]);

    $response = $this->get($previewUrl);

    $response->assertOk()
        ->assertSee((string) $student->full_name)
        ->assertSee('href="'.studentCertificatePortalUrl($student).'"', false)
        ->assertSee('href="'.$pdfUrl.'"', false)
        ->assertDontSee('/admin/', false);

    assertStudentCertificatePortalPrivacyHeaders($response);
});

test('portal and certificate UUIDs are nested and invalid certificate states cannot be previewed or downloaded', function () {
    $first = studentCertificatePortalFixture();
    $second = studentCertificatePortalFixture();
    $student = $first['student'];
    $parameters = [
        studentCertificatePortalSlug($student),
        $student->certificate_portal_id,
    ];

    Pdf::fake();

    foreach ([$second['valid'], $first['revoked'], $first['replaced']] as $certificate) {
        $this->get(route('certificate-portals.certificates.show', [
            ...$parameters,
            $certificate->public_id,
        ]))->assertNotFound();

        $this->get(route('certificate-portals.certificates.pdf', [
            ...$parameters,
            $certificate->public_id,
        ]))->assertNotFound();
    }
});

test('a valid public certificate downloads as a named PDF containing the student and achievement names', function () {
    $fixture = studentCertificatePortalFixture();
    $student = $fixture['student'];
    $slug = studentCertificatePortalSlug($student);
    $portalUrl = studentCertificatePortalUrl($student);
    $pdfUrl = route('certificate-portals.certificates.pdf', [
        $slug,
        $student->certificate_portal_id,
        $fixture['valid']->public_id,
    ]);

    Pdf::fake();

    $response = $this->get($pdfUrl)->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($fixture, $portalUrl, $student): bool {
        $certificate = $pdf->viewData['certificate'] ?? [];
        $studentFilenamePart = preg_replace('/[^\p{L}\p{M}\p{N}_-]+/u', '-', (string) $student->full_name);

        return $pdf->viewName === 'certificates.show'
            && $pdf->isDownload()
            && is_string($studentFilenamePart)
            && str_contains($pdf->downloadName, trim($studentFilenamePart, '-'))
            && str_contains($pdf->downloadName, (string) $fixture['valid']->achievement_name)
            && ($certificate['student_name'] ?? null) === $student->full_name
            && ($certificate['back_url'] ?? null) === $portalUrl
            && ($certificate['pdf_mode'] ?? null) === true;
    });

    assertStudentCertificatePortalPrivacyHeaders($response);
});

test('an outdated student slug redirects to the canonical portal URL', function () {
    $fixture = studentCertificatePortalFixture();
    $student = $fixture['student'];

    $this->get(route('certificate-portals.show', [
        'old-student-name',
        $student->certificate_portal_id,
    ]))->assertRedirect(studentCertificatePortalUrl($student));
});

test('unknown and malformed portal ids return the same private generic page', function () {
    foreach ([(string) Str::uuid(), 'not-a-portal-id'] as $portalId) {
        $response = $this->get(route('certificate-portals.show', [
            'student-name',
            $portalId,
        ]));

        $response->assertNotFound()
            ->assertSee('رابط الشهادات غير متاح')
            ->assertDontSee($portalId);
        assertStudentCertificatePortalPrivacyHeaders($response);
    }
});

test('the public portal is rate limited without coupling the test to the configured threshold', function () {
    $fixture = studentCertificatePortalFixture();
    $url = studentCertificatePortalUrl($fixture['student']);
    $client = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.73']);
    $successfulRequests = 0;
    $lastResponse = null;

    for ($attempt = 1; $attempt <= 100; $attempt++) {
        $lastResponse = $client->get($url);

        if ($lastResponse->status() === 429) {
            break;
        }

        $lastResponse->assertOk();
        $successfulRequests++;
    }

    expect($successfulRequests)->toBeGreaterThan(0)
        ->and($lastResponse)->not->toBeNull()
        ->and($lastResponse?->status())->toBe(429)
        ->and($lastResponse?->headers->has('Retry-After'))->toBeTrue();
});

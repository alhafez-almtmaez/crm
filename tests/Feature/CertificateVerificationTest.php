<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\StudentCertificateService;
use App\Services\System\CertificateDesignSettingsService;
use App\Services\System\CertificateQrCodeService;
use App\Services\System\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, student: Student, point: PlanPoint}
 */
function certificateVerificationFixture(string $type = Certificate::ACHIEVEMENT_PART): array
{
    foreach (['students.view', 'students.update', 'certificates.revoke'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $suffix = (string) Str::ulid();
    $user = User::factory()->create();
    $user->givePermissionTo(['students.view', 'students.update']);
    $center = Center::factory()->create([
        'name' => "مركز التحقق {$suffix}",
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => "مجموعة التحقق {$suffix}",
    ]);
    $plan = Plan::factory()->create(['name' => "خطة التحقق {$suffix}"]);
    $achievement = match ($type) {
        Certificate::ACHIEVEMENT_SURAH => [
            'surah_name' => 'الكهف',
            'part_name' => null,
            'three_parts' => null,
        ],
        Certificate::ACHIEVEMENT_THREE_PARTS => [
            'surah_name' => null,
            'part_name' => null,
            'three_parts' => 'الأجزاء ١، ٢، ٣',
        ],
        default => [
            'surah_name' => null,
            'part_name' => 'الجزء الخامس',
            'three_parts' => null,
        ],
    };
    $point = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'name' => "نقطة التحقق {$suffix}",
        'requires_certificate' => true,
        ...$achievement,
    ]);
    $student = Student::factory()->active()->create([
        'full_name' => 'اسم الطالب وقت الإصدار',
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $point->id,
        'admin_id' => $user->id,
    ]);

    return compact('user', 'student', 'point');
}

function issueCertificateForVerification(User $user, Student $student, PlanPoint $point): Certificate
{
    Auth::guard('web')->login($user);

    try {
        return app(StudentCertificateService::class)->issue($student, (int) $point->id);
    } finally {
        Auth::guard('web')->logout();
    }
}

test('issuance creates a unique UUID v4 public id and keeps the sequential id out of the public link', function () {
    $first = certificateVerificationFixture();
    $firstCertificate = issueCertificateForVerification($first['user'], $first['student'], $first['point']);
    $second = certificateVerificationFixture(Certificate::ACHIEVEMENT_SURAH);
    $secondCertificate = issueCertificateForVerification($second['user'], $second['student'], $second['point']);

    expect(Str::isUuid($firstCertificate->public_id, version: 4))->toBeTrue()
        ->and(Str::isUuid($secondCertificate->public_id, version: 4))->toBeTrue()
        ->and($firstCertificate->public_id)->not->toBe($secondCertificate->public_id);

    $url = route('certificates.verify', ['public_id' => $firstCertificate->public_id]);
    $path = parse_url($url, PHP_URL_PATH);

    expect($path)->toBe('/verify/'.$firstCertificate->public_id)
        ->and($path)->not->toBe('/verify/'.$firstCertificate->id);
});

test('public id cannot be changed after issuance', function () {
    $fixture = certificateVerificationFixture();
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);
    $original = $certificate->public_id;

    $certificate->update(['public_id' => (string) Str::uuid()]);
    expect($certificate->refresh()->public_id)->toBe($original);

    expect(fn () => $certificate->forceFill(['public_id' => (string) Str::uuid()]))
        ->toThrow(LogicException::class);
    expect($certificate->refresh()->public_id)->toBe($original);
});

test('a valid public page uses immutable snapshots and privacy headers', function () {
    $fixture = certificateVerificationFixture();
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);

    $fixture['student']->update(['full_name' => 'اسم الطالب المعدل لاحقًا']);
    $fixture['point']->update(['part_name' => 'الجزء المعدل لاحقًا']);
    app(SystemSettingsService::class)->update(['language' => 'en']);

    $response = $this->get(route('certificates.verify', ['public_id' => $certificate->public_id]));

    $response->assertOk()
        ->assertSee('الشهادة صحيحة ومعتمدة')
        ->assertSee('اسم الطالب وقت الإصدار')
        ->assertSee('جزء')
        ->assertSee('الخامس')
        ->assertSee($certificate->certificate_number)
        ->assertSee('name="robots" content="noindex, nofollow"', false)
        ->assertDontSee('اسم الطالب المعدل لاحقًا')
        ->assertDontSee('الجزء المعدل لاحقًا');

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow, noarchive');
});

test('verification renders a surah certificate snapshot', function () {
    $fixture = certificateVerificationFixture(Certificate::ACHIEVEMENT_SURAH);
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);

    $this->get(route('certificates.verify', ['public_id' => $certificate->public_id]))
        ->assertOk()
        ->assertSee('سورة')
        ->assertSee('الكهف');
});

test('verification renders a juz certificate snapshot', function () {
    $fixture = certificateVerificationFixture(Certificate::ACHIEVEMENT_PART);
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);

    $this->get(route('certificates.verify', ['public_id' => $certificate->public_id]))
        ->assertOk()
        ->assertSee('جزء')
        ->assertSee('الخامس');
});

test('verification renders a three juz certificate snapshot', function () {
    $fixture = certificateVerificationFixture(Certificate::ACHIEVEMENT_THREE_PARTS);
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);

    $this->get(route('certificates.verify', ['public_id' => $certificate->public_id]))
        ->assertOk()
        ->assertSee('ثلاثة أجزاء')
        ->assertSee('الأجزاء ١، ٢، ٣');
});

test('revoked certificates stay public without exposing the internal reason', function () {
    $fixture = certificateVerificationFixture();
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);
    $certificate->forceFill([
        'status' => Certificate::STATUS_REVOKED,
        'revoked_at' => now(),
        'revoked_reason' => 'معلومة داخلية حساسة',
    ])->save();

    $this->get(route('certificates.verify', ['public_id' => $certificate->public_id]))
        ->assertOk()
        ->assertSee('هذه الشهادة ملغاة')
        ->assertSee('ملغاة')
        ->assertDontSee('معلومة داخلية حساسة');
});

test('replaced certificates show the replacement state', function () {
    $fixture = certificateVerificationFixture();
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);
    $certificate->update(['status' => Certificate::STATUS_REPLACED]);

    $this->get(route('certificates.verify', ['public_id' => $certificate->public_id]))
        ->assertOk()
        ->assertSee('تم استبدال هذه الشهادة')
        ->assertSee('مستبدلة');
});

test('unknown and malformed public ids return the same generic 404 page', function () {
    foreach ([(string) Str::uuid(), 'not-a-uuid'] as $publicId) {
        $this->get('/verify/'.$publicId)
            ->assertNotFound()
            ->assertSee('تعذر التحقق من الشهادة')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('QueryException');
    }
});

test('the QR foreground keeps an already dark certificate color', function () {
    $url = 'https://official.example/verify/'.Str::uuid();
    $service = app(CertificateQrCodeService::class);
    $payload = $service->payload($url, '#123456');
    $svg = base64_decode(Str::after($payload['qr_code_data_uri'], ','), true);

    expect($service->foregroundHex('#123456'))->toBe('#123456')
        ->and($payload['qr_foreground_color'])->toBe('#123456')
        ->and($svg)->toBeString()
        ->and($svg)->toContain('<path fill="#123456"');
});

test('the QR foreground darkens a light certificate color in deterministic five percent steps', function () {
    $url = 'https://official.example/verify/'.Str::uuid();
    $service = app(CertificateQrCodeService::class);
    $payload = $service->payload($url, '#F4E8A0');
    $foreground = $payload['qr_foreground_color'];
    $channels = array_map('hexdec', str_split(substr($foreground, 1), 2));
    $linear = array_map(static function (int $channel): float {
        $normalized = $channel / 255;

        return $normalized <= 0.04045
            ? $normalized / 12.92
            : (($normalized + 0.055) / 1.055) ** 2.4;
    }, $channels);
    $luminance = (0.2126 * $linear[0]) + (0.7152 * $linear[1]) + (0.0722 * $linear[2]);
    $contrast = 1.05 / ($luminance + 0.05);
    $svg = base64_decode(Str::after($payload['qr_code_data_uri'], ','), true);

    expect($service->foregroundHex('#F4E8A0'))->toBe('#555138')
        ->and($foreground)->toBe('#555138')
        ->and($contrast)->toBeGreaterThanOrEqual(7.0)
        ->and($svg)->toBeString()
        ->and($svg)->toContain('<path fill="#555138"');
});

test('the QR payload falls back safely and keeps a white background with no embedded logo', function () {
    $url = 'https://official.example/verify/'.Str::uuid();
    $service = app(CertificateQrCodeService::class);
    $payload = $service->payload($url, 'not-a-hex-color');
    $encodedSvg = Str::after($payload['qr_code_data_uri'], ',');
    $svg = base64_decode($encodedSvg, true);

    expect($service->foregroundHex(null))->toBe('#09232A')
        ->and($service->foregroundHex('not-a-hex-color'))->toBe('#09232A')
        ->and($service->foregroundHex('#FFF'))->toBe('#09232A')
        ->and($payload['verification_url'])->toBe($url)
        ->and($payload['qr_foreground_color'])->toBe('#09232A')
        ->and($payload['qr_code_data_uri'])->toStartWith('data:image/svg+xml;base64,')
        ->and($svg)->toBeString()
        ->and($svg)->toContain('<svg')
        ->and($svg)->toContain('fill="#ffffff"')
        ->and($svg)->toContain('<path fill="#09232a"')
        ->and($svg)->not->toContain('<image');
});

test('certificate HTML and PDF use the APP URL verification link and SVG data URI', function () {
    config(['app.url' => 'https://official.example']);
    $fixture = certificateVerificationFixture();
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);
    $expectedUrl = 'https://official.example/verify/'.$certificate->public_id;
    $savedAccentColor = (string) data_get($certificate->design_snapshot, 'accent_color');
    $expectedQr = app(CertificateQrCodeService::class)->payload($expectedUrl, $savedAccentColor);
    $changedDesigns = app(CertificateDesignSettingsService::class)->get();
    $changedDesigns[Center::STUDENT_GENDER_MALE][Certificate::ACHIEVEMENT_PART]['accent_color'] = '#123456';
    app(CertificateDesignSettingsService::class)->update($changedDesigns);
    $adminPath = route(
        'admin.students.certificates.show',
        [$fixture['student'], $certificate],
        absolute: false,
    );

    $this->actingAs($fixture['user'], 'web')
        ->withServerVariables(['HTTP_HOST' => 'attacker.example'])
        ->get($adminPath)
        ->assertOk()
        ->assertSee($expectedUrl, false)
        ->assertDontSee('attacker.example', false)
        ->assertSee('data:image/svg+xml;base64,', false)
        ->assertSee($expectedQr['qr_code_data_uri'], false)
        ->assertSee('--certificate-qr-color: '.$expectedQr['qr_foreground_color'], false)
        ->assertSee($certificate->certificate_number)
        ->assertDontSee('/verify/'.$certificate->id.'"', false);

    Pdf::fake();
    $this->actingAs($fixture['user'], 'web')
        ->get(route('admin.students.certificates.pdf', [$fixture['student'], $certificate]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($expectedQr, $expectedUrl): bool {
        $payload = $pdf->viewData['certificate'] ?? [];

        return ($payload['verification_url'] ?? null) === $expectedUrl
            && ($payload['qr_foreground_color'] ?? null) === $expectedQr['qr_foreground_color']
            && ($payload['qr_code_data_uri'] ?? null) === $expectedQr['qr_code_data_uri'];
    });
});

test('the verification route applies its certificate-specific rate limit', function () {
    $fixture = certificateVerificationFixture();
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);
    $url = route('certificates.verify', ['public_id' => $certificate->public_id]);
    $client = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.27']);

    for ($request = 1; $request <= 20; $request++) {
        $client->get($url)->assertOk();
    }

    $client->get($url)->assertTooManyRequests();
});

test('revocation requires its dedicated permission and writes an audit record', function () {
    $fixture = certificateVerificationFixture();
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);
    $url = route('admin.students.certificates.revoke', [$fixture['student'], $certificate]);

    $this->patchJson($url, ['revoked_reason' => 'سبب إداري داخلي'])
        ->assertUnauthorized();

    $this->actingAs($fixture['user'], 'web')
        ->patchJson($url, ['revoked_reason' => 'سبب إداري داخلي'])
        ->assertForbidden();

    $otherSupervisor = User::factory()->create();
    $otherSupervisor->givePermissionTo('certificates.revoke');

    $this->actingAs($otherSupervisor, 'web')
        ->patchJson($url, ['revoked_reason' => 'سبب إداري داخلي'])
        ->assertNotFound();

    $fixture['user']->givePermissionTo('certificates.revoke');

    $this->actingAs($fixture['user']->fresh(), 'web')
        ->patchJson($url, ['revoked_reason' => 'سبب إداري داخلي'])
        ->assertOk()
        ->assertJsonPath('certificate.status', Certificate::STATUS_REVOKED);

    $certificate->refresh();
    expect($certificate->status)->toBe(Certificate::STATUS_REVOKED)
        ->and($certificate->revoked_at)->not->toBeNull()
        ->and($certificate->revoked_reason)->toBe('سبب إداري داخلي')
        ->and(Activity::query()
            ->where('log_name', 'certificates')
            ->where('subject_type', Certificate::class)
            ->where('subject_id', $certificate->id)
            ->where('event', 'updated')
            ->where('causer_id', $fixture['user']->id)
            ->exists())->toBeTrue();
});

test('deleting a student preserves the issued certificate and public verification page', function () {
    $fixture = certificateVerificationFixture();
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);
    $publicId = $certificate->public_id;

    $fixture['student']->delete();

    expect($certificate->refresh()->student_id)->toBeNull()
        ->and($certificate->public_id)->toBe($publicId);

    $this->get(route('certificates.verify', ['public_id' => $publicId]))
        ->assertOk()
        ->assertSee('اسم الطالب وقت الإصدار');
});

test('the migration safely backfills certificates that existed before public verification', function () {
    $fixture = certificateVerificationFixture();
    $certificate = issueCertificateForVerification($fixture['user'], $fixture['student'], $fixture['point']);
    $certificateId = $certificate->id;
    $migration = require database_path('migrations/2026_08_29_020000_add_public_verification_to_certificates_table.php');

    $migration->down();

    expect(Schema::hasColumn('certificates', 'public_id'))->toBeFalse()
        ->and(DB::table('certificates')->where('id', $certificateId)->exists())->toBeTrue();

    $migration->up();
    $legacyCertificate = DB::table('certificates')->where('id', $certificateId)->first();

    expect($legacyCertificate)->not->toBeNull()
        ->and(Str::isUuid($legacyCertificate->public_id, version: 4))->toBeTrue()
        ->and($legacyCertificate->status)->toBe(Certificate::STATUS_VALID);
});

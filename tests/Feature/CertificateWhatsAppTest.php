<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\Device;
use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\CertificatePdfRenderer;
use App\Services\Admin\StudentCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, student: Student, certificate: Certificate}
 */
function certificateWhatsAppFixture(): array
{
    foreach (['students.view', 'students.update', 'certificates.send'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $suffix = (string) Str::ulid();
    $user = User::factory()->create();
    $user->givePermissionTo(['students.view', 'students.update', 'certificates.send']);
    $center = Center::factory()->create(['name' => "مركز الشهادة {$suffix}"]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => "مجموعة الشهادة {$suffix}",
    ]);
    $plan = Plan::factory()->create(['name' => "خطة الشهادة {$suffix}"]);
    $point = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'name' => "إتمام سورة مريم {$suffix}",
        'requires_certificate' => true,
        'surah_name' => 'مريم',
    ]);
    $student = Student::factory()->active()->create([
        'full_name' => 'أحمد محمد عبدالله',
        'parent_phone_number' => '0791234567',
        'phone_number' => null,
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $point->id,
        'admin_id' => $user->id,
    ]);

    Auth::guard('web')->login($user);
    try {
        $certificate = app(StudentCertificateService::class)->issue($student, (int) $point->id);
    } finally {
        Auth::guard('web')->logout();
    }

    Device::factory()->connected()->create(['session_id' => 'certificate-session']);

    return compact('user', 'student', 'certificate');
}

test('an issued certificate is sent as a named PDF containing the achievement after WhatsApp registration verification', function () {
    config([
        'app.url' => 'https://official.example',
        'services.whatsapp_api.url' => 'https://whatsapp.test',
        'services.whatsapp_api.key' => 'test-key',
    ]);
    $fixture = certificateWhatsAppFixture();
    $pdf = "%PDF-1.7\ncertificate-document";
    $expectedFilename = 'شهادة-سورة-مريم.pdf';

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock) use ($fixture, $pdf): void {
        $mock->shouldReceive('render')
            ->once()
            ->withArgs(fn (Student $student, Certificate $certificate): bool => $student->is($fixture['student']) && $certificate->is($fixture['certificate']))
            ->andReturn($pdf);
    });

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            // wwebjs-api can accept and deliver a PDF while omitting the
            // serialized message object from its successful response.
            return Http::response(['success' => true]);
        }

        return Http::response([], 404);
    });

    $url = route('admin.students.certificates.whatsapp', [
        $fixture['student'],
        $fixture['certificate'],
    ]);

    $response = $this->actingAs($fixture['user'], 'web')->postJson($url);

    $response->assertOk()
        ->assertJsonPath('partial', false)
        ->assertJsonPath('already_sent', false)
        ->assertJsonPath('certificate.was_sent_via_whatsapp', true)
        ->assertJsonPath('certificate.can_send_whatsapp', false)
        ->assertJsonPath(
            'certificate.whatsapp_image_filename',
            $expectedFilename,
        );

    $sentRequest = collect(Http::recorded())
        ->map(static fn (array $record): Request => $record[0])
        ->first(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/'));

    expect($sentRequest)->toBeInstanceOf(Request::class)
        ->and(data_get($sentRequest?->data(), 'contentType'))->toBe('MessageMedia')
        ->and(data_get($sentRequest?->data(), 'content.mimetype'))->toBe('application/pdf')
        ->and(data_get($sentRequest?->data(), 'content.data'))->toBe(base64_encode($pdf))
        ->and(data_get($sentRequest?->data(), 'content.filename'))
        ->toBe($expectedFilename)
        ->and(data_get($sentRequest?->data(), 'options.sendMediaAsDocument'))->toBeTrue()
        ->and(data_get($sentRequest?->data(), 'options.caption'))->toContain('أحمد محمد عبدالله')
        ->and(data_get($sentRequest?->data(), 'options.caption'))
        ->toContain('إتمام حفظ سورة *مريم*')
        ->and(data_get($sentRequest?->data(), 'options.caption'))
        ->not->toContain($fixture['certificate']->certificate_number)
        ->and(data_get($sentRequest?->data(), 'options.caption'))
        ->not->toContain('/verify/');

    $fixture['certificate']->refresh();
    expect($fixture['certificate']->whatsapp_sent_at)->not->toBeNull()
        ->and($fixture['certificate']->whatsapp_sent_by)->toBe($fixture['user']->id)
        ->and($fixture['certificate']->whatsapp_delivery_status)->toBe(Certificate::WHATSAPP_DELIVERY_SENT)
        ->and($fixture['certificate']->whatsapp_image_filename)
        ->toBe($expectedFilename);

    $requestsBeforeRetry = count(Http::recorded());

    $this->actingAs($fixture['user'], 'web')
        ->postJson($url)
        ->assertOk()
        ->assertJsonPath('already_sent', true);

    expect(Http::recorded())->toHaveCount($requestsBeforeRetry);

    $this->actingAs($fixture['user'], 'web')
        ->putJson(route('admin.students.certificates.redesign', [
            $fixture['student'],
            $fixture['certificate'],
        ]))
        ->assertOk()
        ->assertJsonPath('certificate.was_sent_via_whatsapp', false)
        ->assertJsonPath('certificate.can_send_whatsapp', true);

    $fixture['certificate']->refresh();
    expect($fixture['certificate']->whatsapp_delivery_status)->toBeNull()
        ->and($fixture['certificate']->whatsapp_sent_at)->toBeNull()
        ->and($fixture['certificate']->whatsapp_sent_by)->toBeNull()
        ->and($fixture['certificate']->whatsapp_image_filename)->toBeNull();
});

test('certificate WhatsApp copy and filename use the clean achievement name', function (
    string $achievementType,
    string $achievementName,
    string $expectedFilename,
    string $expectedPhrase,
) {
    config([
        'services.whatsapp_api.url' => 'https://whatsapp.test',
        'services.whatsapp_api.key' => 'test-key',
    ]);
    $fixture = certificateWhatsAppFixture();
    $fixture['certificate']->forceFill([
        'achievement_type' => $achievementType,
        'achievement_name' => $achievementName,
        'center_name' => 'دار القرآن مسجد الصالحين',
    ])->save();
    $pdf = "%PDF-1.7\npart-certificate";

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock) use ($pdf): void {
        $mock->shouldReceive('render')->once()->andReturn($pdf);
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

    $this->actingAs($fixture['user'], 'web')->postJson(route(
        'admin.students.certificates.whatsapp',
        [$fixture['student'], $fixture['certificate']],
    ))->assertOk();

    $sentRequest = collect(Http::recorded())
        ->map(static fn (array $record): Request => $record[0])
        ->first(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/'));
    $caption = (string) data_get($sentRequest?->data(), 'options.caption');

    expect(data_get($sentRequest?->data(), 'content.filename'))
        ->toBe($expectedFilename)
        ->and($caption)->toContain('تتقدّم إدارة *دار القرآن مسجد الصالحين* بأحرّ التهاني')
        ->and($caption)->toContain('بمناسبة '.$expectedPhrase)
        ->and($caption)->toContain('📎 الشهادة مرفقة بصيغة PDF.')
        ->and($caption)->not->toContain($fixture['certificate']->certificate_number)
        ->and($caption)->not->toContain('/verify/');
})->with([
    'Quran surah' => [
        Certificate::ACHIEVEMENT_SURAH,
        'سورة مريم',
        'شهادة-سورة-مريم.pdf',
        'إتمام حفظ سورة *مريم*',
    ],
    'Quran part' => [
        Certificate::ACHIEVEMENT_PART,
        'الجزء عمَّ',
        'شهادة-جزء-من-القرآن-عمَّ.pdf',
        'إتمام حفظ جزء *عمَّ* من القرآن الكريم',
    ],
    'three Quran parts' => [
        Certificate::ACHIEVEMENT_THREE_PARTS,
        'الأجزاء الثلاثة الأولى',
        'شهادة-ثلاثة-أجزاء-الأولى.pdf',
        'إتمام حفظ *الأجزاء الثلاثة الأولى* من القرآن الكريم',
    ],
    'Sunnah book' => [
        Certificate::ACHIEVEMENT_SUNNAH_BOOK,
        'كتاب الأربعين النووية',
        'شهادة-كتاب-من-السُنّة-الأربعين-النووية.pdf',
        'إتمام حفظ كتاب *الأربعين النووية* من السُّنَّة النبوية',
    ],
    'Sunnah part' => [
        Certificate::ACHIEVEMENT_SUNNAH_PART,
        'الجزء الأول',
        'شهادة-جزء-من-السُنّة-الأول.pdf',
        'إتمام حفظ جزء *الأول* من السُّنَّة النبوية',
    ],
]);

test('an unregistered WhatsApp number is rejected before rendering or sending the certificate', function () {
    config(['services.whatsapp_api.url' => 'https://whatsapp.test']);
    $fixture = certificateWhatsAppFixture();

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('render');
    });

    Http::fake([
        'https://whatsapp.test/client/isRegisteredUser/*' => Http::response([
            'success' => true,
            'result' => false,
        ]),
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->postJson(route('admin.students.certificates.whatsapp', [
            $fixture['student'],
            $fixture['certificate'],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('certificate');

    Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/'));
    expect($fixture['certificate']->refresh()->whatsapp_sent_at)->toBeNull();
});

test('an unregistered number is skipped while the certificate is sent to another registered number', function () {
    config([
        'services.whatsapp_api.url' => 'https://whatsapp.test',
        'services.whatsapp_api.message_delay_seconds' => 0,
    ]);
    $fixture = certificateWhatsAppFixture();
    $fixture['student']->update(['phone_number' => '0787654321']);
    $pdf = "%PDF-1.7\nmixed-recipients";

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock) use ($pdf): void {
        $mock->shouldReceive('render')->once()->andReturn($pdf);
    });

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response([
                'success' => true,
                'result' => $request['number'] === '962787654321',
            ]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            return Http::response(['success' => true, 'message' => ['id' => 'wa-message-mixed']]);
        }

        return Http::response([], 404);
    });

    $this->actingAs($fixture['user'], 'web')
        ->postJson(route('admin.students.certificates.whatsapp', [
            $fixture['student'],
            $fixture['certificate'],
        ]))
        ->assertOk()
        ->assertJsonPath('partial', false)
        ->assertJsonPath('certificate.was_sent_via_whatsapp', true);

    Http::assertSentCount(3);
    Http::assertSent(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/')
        && $request['chatId'] === '962787654321@s.whatsapp.net');
    Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/')
        && $request['chatId'] === '962791234567@s.whatsapp.net');
});

test('a successful PDF envelope without a message object continues to every registered recipient', function () {
    config([
        'services.whatsapp_api.url' => 'https://whatsapp.test',
        'services.whatsapp_api.message_delay_seconds' => 0,
    ]);
    $fixture = certificateWhatsAppFixture();
    $fixture['student']->update(['phone_number' => '0787654321']);

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')->once()->andReturn("%PDF-1.7\ntwo-recipients");
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

    $this->actingAs($fixture['user'], 'web')
        ->postJson(route('admin.students.certificates.whatsapp', [
            $fixture['student'],
            $fixture['certificate'],
        ]))
        ->assertOk()
        ->assertJsonPath('certificate.whatsapp_delivery_status', Certificate::WHATSAPP_DELIVERY_SENT);

    $sendRequests = collect(Http::recorded())
        ->map(static fn (array $record): Request => $record[0])
        ->filter(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/'));

    expect($sendRequests)->toHaveCount(2)
        ->and($sendRequests->map(
            static fn (Request $request): mixed => $request['chatId'],
        )->values()->all())->toBe([
            '962791234567@s.whatsapp.net',
            '962787654321@s.whatsapp.net',
        ]);
});

test('a confirmed partial delivery is marked as sent to prevent duplicate certificates', function () {
    config([
        'services.whatsapp_api.url' => 'https://whatsapp.test',
        'services.whatsapp_api.message_delay_seconds' => 0,
    ]);
    $fixture = certificateWhatsAppFixture();
    $fixture['student']->update(['phone_number' => '0787654321']);
    $pdf = "%PDF-1.7\npartial-delivery";
    $sendAttempts = 0;

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock) use ($pdf): void {
        $mock->shouldReceive('render')->once()->andReturn($pdf);
    });

    Http::fake(function (Request $request) use (&$sendAttempts) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            $sendAttempts++;

            return $sendAttempts === 1
                ? Http::response(['success' => true, 'message' => ['id' => 'wa-message-first']])
                : Http::response(['success' => false, 'error' => 'delivery_failed'], 500);
        }

        return Http::response([], 404);
    });

    $this->actingAs($fixture['user'], 'web')
        ->postJson(route('admin.students.certificates.whatsapp', [
            $fixture['student'],
            $fixture['certificate'],
        ]))
        ->assertOk()
        ->assertJsonPath('partial', true)
        ->assertJsonPath('certificate.was_sent_via_whatsapp', true);

    expect($sendAttempts)->toBe(2)
        ->and($fixture['certificate']->refresh()->whatsapp_sent_at)->not->toBeNull()
        ->and($fixture['certificate']->whatsapp_delivery_status)
        ->toBe(Certificate::WHATSAPP_DELIVERY_PARTIAL);
});

test('an ambiguous transport failure is blocked from automatic resend until the certificate is reviewed', function () {
    config(['services.whatsapp_api.url' => 'https://whatsapp.test']);
    $fixture = certificateWhatsAppFixture();
    $pdf = "%PDF-1.7\nunknown-delivery";

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock) use ($pdf): void {
        $mock->shouldReceive('render')->once()->andReturn($pdf);
    });

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            throw new ConnectionException('The delivery response timed out.');
        }

        return Http::response([], 404);
    });

    $url = route('admin.students.certificates.whatsapp', [
        $fixture['student'],
        $fixture['certificate'],
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->postJson($url)
        ->assertOk()
        ->assertJsonPath('uncertain', true)
        ->assertJsonPath('certificate.was_sent_via_whatsapp', false)
        ->assertJsonPath('certificate.whatsapp_delivery_status', Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED)
        ->assertJsonPath('certificate.whatsapp_delivery_requires_review', true)
        ->assertJsonPath('certificate.can_send_whatsapp', false);

    $requestsBeforeRetry = count(Http::recorded());

    $this->actingAs($fixture['user'], 'web')
        ->postJson($url)
        ->assertOk()
        ->assertJsonPath('uncertain', true);

    expect(Http::recorded())->toHaveCount($requestsBeforeRetry)
        ->and($fixture['certificate']->refresh()->whatsapp_sent_at)->toBeNull()
        ->and($fixture['certificate']->whatsapp_delivery_status)
        ->toBe(Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED);

    $this->actingAs($fixture['user'], 'web')
        ->putJson(route('admin.students.certificates.redesign', [
            $fixture['student'],
            $fixture['certificate'],
        ]))
        ->assertOk()
        ->assertJsonPath('certificate.whatsapp_delivery_status', null)
        ->assertJsonPath('certificate.can_send_whatsapp', true);
});

test('a transport failure after one recipient received the certificate cannot resend the batch', function () {
    config([
        'services.whatsapp_api.url' => 'https://whatsapp.test',
        'services.whatsapp_api.message_delay_seconds' => 0,
    ]);
    $fixture = certificateWhatsAppFixture();
    $fixture['student']->update(['phone_number' => '0787654321']);
    $pdf = "%PDF-1.7\nunknown-after-first-recipient";
    $sendAttempts = 0;

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock) use ($pdf): void {
        $mock->shouldReceive('render')->once()->andReturn($pdf);
    });

    Http::fake(function (Request $request) use (&$sendAttempts) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            $sendAttempts++;

            if ($sendAttempts === 1) {
                return Http::response(['success' => true, 'message' => ['id' => 'wa-message-first']]);
            }

            throw new ConnectionException('The second delivery response timed out.');
        }

        return Http::response([], 404);
    });

    $url = route('admin.students.certificates.whatsapp', [
        $fixture['student'],
        $fixture['certificate'],
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->postJson($url)
        ->assertOk()
        ->assertJsonPath('uncertain', true)
        ->assertJsonPath('certificate.was_sent_via_whatsapp', false)
        ->assertJsonPath('certificate.whatsapp_delivery_status', Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED)
        ->assertJsonPath('certificate.can_send_whatsapp', false);

    expect($sendAttempts)->toBe(2);

    $requestsBeforeRetry = count(Http::recorded());

    $this->actingAs($fixture['user'], 'web')
        ->postJson($url)
        ->assertOk()
        ->assertJsonPath('uncertain', true);

    expect(Http::recorded())->toHaveCount($requestsBeforeRetry);
});

test('an HTTP failure after starting the WhatsApp send is treated as uncertain and is not retried', function () {
    config(['services.whatsapp_api.url' => 'https://whatsapp.test']);
    $fixture = certificateWhatsAppFixture();
    $pdf = "%PDF-1.7\nunknown-http-delivery";

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock) use ($pdf): void {
        $mock->shouldReceive('render')->once()->andReturn($pdf);
    });

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            return Http::response(['success' => false, 'error' => 'upstream_response_failed'], 500);
        }

        return Http::response([], 404);
    });

    $url = route('admin.students.certificates.whatsapp', [
        $fixture['student'],
        $fixture['certificate'],
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->postJson($url)
        ->assertOk()
        ->assertJsonPath('uncertain', true)
        ->assertJsonPath('certificate.was_sent_via_whatsapp', false)
        ->assertJsonPath('certificate.whatsapp_delivery_status', Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED)
        ->assertJsonPath('certificate.can_send_whatsapp', false);

    $requestsBeforeRetry = count(Http::recorded());

    $this->actingAs($fixture['user'], 'web')
        ->postJson($url)
        ->assertOk()
        ->assertJsonPath('uncertain', true);

    expect(Http::recorded())->toHaveCount($requestsBeforeRetry);
});

test('a known device failure before the first send releases the certificate for a later retry', function () {
    config(['services.whatsapp_api.url' => 'https://whatsapp.test']);
    $fixture = certificateWhatsAppFixture();
    $pdf = "%PDF-1.7\nknown-zero-delivery";

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock) use ($pdf): void {
        $mock->shouldReceive('render')->once()->andReturn($pdf);
    });

    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            Device::query()->update(['status' => 'DISCONNECTED']);

            return Http::response(['success' => true, 'result' => true]);
        }

        return Http::response([], 404);
    });

    $this->actingAs($fixture['user'], 'web')
        ->postJson(route('admin.students.certificates.whatsapp', [
            $fixture['student'],
            $fixture['certificate'],
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('certificate');

    $fixture['certificate']->refresh();
    expect($fixture['certificate']->whatsapp_delivery_status)->toBeNull()
        ->and($fixture['certificate']->whatsapp_sent_at)->toBeNull();

    Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/'));
});

test('certificate WhatsApp delivery requires permission ownership a valid certificate and a phone', function () {
    config(['services.whatsapp_api.url' => 'https://whatsapp.test']);
    $fixture = certificateWhatsAppFixture();
    $url = route('admin.students.certificates.whatsapp', [$fixture['student'], $fixture['certificate']]);
    $otherUser = User::factory()->create();
    $otherUser->givePermissionTo('certificates.send');

    $this->actingAs(User::factory()->create(), 'web')
        ->postJson($url)
        ->assertForbidden();

    $this->actingAs($otherUser, 'web')
        ->postJson($url)
        ->assertNotFound();

    $fixture['certificate']->update(['status' => Certificate::STATUS_REVOKED]);
    $this->actingAs($fixture['user'], 'web')
        ->postJson($url)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('certificate');

    $fixture['certificate']->update(['status' => Certificate::STATUS_VALID]);
    $fixture['student']->update([
        'parent_phone_number' => null,
        'phone_number' => null,
    ]);

    $this->actingAs($fixture['user'], 'web')
        ->postJson($url)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('certificate');

    Http::assertNothingSent();
    expect($fixture['certificate']->refresh()->whatsapp_sent_at)->toBeNull();
});

test('certificate page exposes delivery capability and sent state without exposing phone numbers', function () {
    $fixture = certificateWhatsAppFixture();

    $this->actingAs($fixture['user'], 'web')
        ->get(route('admin.students.certificates.index', $fixture['student']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Students/Certificates', false)
            ->where('canSendWhatsApp', true)
            ->where('student.has_whatsapp_recipient', true)
            ->where('certificates.0.was_sent_via_whatsapp', false)
            ->where('certificates.0.can_send_whatsapp', true)
            ->where('certificates.0.whatsapp_send_url', route(
                'admin.students.certificates.whatsapp',
                [$fixture['student'], $fixture['certificate']],
            ))
            ->missing('student.parent_phone_number')
            ->missing('student.phone_number'));
});

test('a stale processing claim is shown for review and can be cleared by explicitly redesigning', function () {
    $fixture = certificateWhatsAppFixture();

    DB::table('certificates')
        ->where('id', $fixture['certificate']->id)
        ->update([
            'whatsapp_delivery_status' => Certificate::WHATSAPP_DELIVERY_PROCESSING,
            'whatsapp_image_filename' => 'شهادة-سورة-مريم.pdf',
            'updated_at' => now()->subMinutes(Certificate::WHATSAPP_PROCESSING_STALE_AFTER_MINUTES + 1),
        ]);

    $this->actingAs($fixture['user'], 'web')
        ->get(route('admin.students.certificates.index', $fixture['student']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('certificates.0.whatsapp_delivery_status', Certificate::WHATSAPP_DELIVERY_REVIEW_REQUIRED)
            ->where('certificates.0.whatsapp_delivery_requires_review', true)
            ->where('certificates.0.can_send_whatsapp', false));

    $this->actingAs($fixture['user'], 'web')
        ->putJson(route('admin.students.certificates.redesign', [
            $fixture['student'],
            $fixture['certificate'],
        ]))
        ->assertOk()
        ->assertJsonPath('certificate.whatsapp_delivery_status', null)
        ->assertJsonPath('certificate.can_send_whatsapp', true);
});

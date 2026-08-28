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
use App\Services\System\CertificateDesignSettingsService;
use App\Services\System\CertificateWordingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{0: User, 1: Student, 2: PlanPoint, 3: PlanPoint, 4: PlanPoint}
 */
function certificateFixture(string $studentName = 'طالب الاختبار'): array
{
    Permission::findOrCreate('students.view', 'web');
    Permission::findOrCreate('students.update', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo(['students.view', 'students.update']);
    $center = Center::factory()->create([
        'name' => 'مركز السلام القرآني',
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => 'المجموعة الأولى',
    ]);
    $plan = Plan::factory()->create(['name' => 'خطة الحافظ المتميز']);
    $surahPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'name' => 'إتمام سورة البقرة',
        'requires_certificate' => true,
        'surah_name' => 'البقرة',
    ]);
    $partPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 20,
        'name' => 'إتمام الجزء الأول',
        'requires_certificate' => true,
        'surah_name' => 'البقرة',
        'part_name' => 'الجزء الأول',
    ]);
    $futurePoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 30,
        'name' => 'إتمام ثلاثة أجزاء',
        'requires_certificate' => true,
        'three_parts' => 'الأجزاء الثلاثة الأولى',
    ]);
    $student = Student::factory()->active()->create([
        'full_name' => $studentName,
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $partPoint->id,
        'admin_id' => $user->id,
    ]);

    $transaction = new StudentPointTransaction([
        'student_id' => $student->id,
        'plan_point_id' => $partPoint->id,
        'type' => StudentPointTransaction::TYPE_HOMEWORK_COMPLETED,
        'points' => 5,
        'balance_before' => 0,
        'balance_after' => 5,
        'created_by' => $user->id,
    ]);
    $transaction->created_at = '2026-08-20 09:00:00';
    $transaction->updated_at = '2026-08-20 09:00:00';
    $transaction->save();

    return [$user, $student, $surahPoint, $partPoint, $futurePoint];
}

test('student certificate page lists reached checkpoints and issued certificates', function () {
    [$user, $student, $surahPoint, $partPoint, $futurePoint] = certificateFixture();

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.index', $student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Students/Certificates', false)
            ->where('student.full_name', $student->full_name)
            ->where('student.current_plan_point_name', $partPoint->name)
            ->has('availableCertificates', 2)
            ->where('availableCertificates.0.id', $surahPoint->id)
            ->where('availableCertificates.1.id', $partPoint->id)
            ->where('availableCertificates.1.achievement_type', Certificate::ACHIEVEMENT_PART)
            ->where('availableCertificates.1.achievement_name', 'الجزء الأول')
            ->missing('availableCertificates.2')
            ->has('certificates', 0));

    expect($futurePoint->sort_order)->toBeGreaterThan($partPoint->sort_order);
});

test('certificate issuance snapshots the reached plan achievement and prevents duplicates or future points', function () {
    Carbon::setTestNow('2026-08-28 12:00:00');
    [$user, $student, , $partPoint, $futurePoint] = certificateFixture();

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated()
        ->assertJsonPath('certificate.achievement_type', Certificate::ACHIEVEMENT_PART)
        ->assertJsonPath('certificate.achievement_name', 'الجزء الأول');

    $certificate = Certificate::query()->sole();

    expect($certificate->student_name)->toBe($student->full_name)
        ->and($certificate->center_name)->toBe('مركز السلام القرآني')
        ->and($certificate->plan_point_name)->toBe($partPoint->name)
        ->and($certificate->achievement_type)->toBe(Certificate::ACHIEVEMENT_PART)
        ->and($certificate->achievement_name)->toBe('الجزء الأول')
        ->and($certificate->gregorian_date)->toBe('٢٠٢٦/٠٨/٢٠')
        ->and($certificate->certificate_number)->toStartWith('HMT-2026-');

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan_point_id');

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $futurePoint->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan_point_id');

    expect(Certificate::query()->count())->toBe(1);
});

test('certificate issuance snapshots hidden center identity and moves the complete project identity into its place', function () {
    [$user, $student, , $partPoint] = certificateFixture();
    $center = Center::query()->findOrFail($student->center_id);
    $center->update([
        'certificate_name' => 'المركز الرسمي للشهادات',
        'show_center_manager_signature' => false,
    ]);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();

    expect($certificate->center_name)->toBe('المركز الرسمي للشهادات')
        ->and($certificate->show_center_manager_signature)->toBeFalse();

    $service = app(StudentCertificateService::class);
    $webPayload = $service->viewPayload($student, $certificate);

    expect($webPayload['show_center_manager_signature'])->toBeFalse()
        ->and($webPayload['center_manager_title'])->toBe('')
        ->and($webPayload['images']['left_logo'])->toBe('')
        ->and($webPayload['images']['center_stamp'])->toBe('')
        ->and($webPayload['images']['center_signature'])->toBe('')
        ->and($webPayload['images']['right_logo'])->not->toBe('')
        ->and($webPayload['images']['project_stamp'])->not->toBe('')
        ->and($webPayload['images']['project_signature'])->not->toBe('');

    $center->update([
        'certificate_name' => 'اسم جديد لا يجب أن يظهر',
        'show_center_manager_signature' => true,
    ]);

    $certificate->refresh();

    expect($certificate->center_name)->toBe('المركز الرسمي للشهادات')
        ->and($certificate->show_center_manager_signature)->toBeFalse();

    $response = $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]));

    $response->assertOk()
        ->assertSee('المركز الرسمي للشهادات')
        ->assertDontSee('اسم جديد لا يجب أن يظهر')
        ->assertDontSee('left-logo.png', false)
        ->assertDontSee($certificate->center_manager_title)
        ->assertDontSee('center-stamp.png', false)
        ->assertDontSee('center-signature.png', false)
        ->assertDontSee('certificate__signing--center', false)
        ->assertSee('right-logo.png', false)
        ->assertSee($certificate->project_manager_title)
        ->assertSee('project-stamp.png', false)
        ->assertSee('project-signature.png', false)
        ->assertSee('certificate--project-only', false)
        ->assertSee('class="certificate__logo certificate__logo--right"', false)
        ->assertDontSee('certificate__logo--project-solo', false)
        ->assertSee('certificate__signing--project-solo', false);

    expect(substr_count($response->getContent(), 'right-logo.png'))->toBe(1);

    Pdf::fake();

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.pdf', [$student, $certificate]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        $payload = $pdf->viewData['certificate'] ?? [];
        $images = $payload['images'] ?? [];

        return ($payload['show_center_manager_signature'] ?? true) === false
            && ($payload['center_manager_title'] ?? null) === ''
            && ($images['left_logo'] ?? null) === ''
            && ($images['center_stamp'] ?? null) === ''
            && ($images['center_signature'] ?? null) === ''
            && str_starts_with((string) ($images['right_logo'] ?? ''), 'data:image/png;base64,')
            && str_starts_with((string) ($images['project_stamp'] ?? ''), 'data:image/png;base64,')
            && str_starts_with((string) ($images['project_signature'] ?? ''), 'data:image/png;base64,');
    });
});

test('certificate issuance falls back to the center name and shows its manager signature', function () {
    [$user, $student, , $partPoint] = certificateFixture();
    $center = Center::query()->findOrFail($student->center_id);
    $center->update([
        'certificate_name' => null,
        'show_center_manager_signature' => true,
    ]);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();

    expect($certificate->center_name)->toBe($center->name)
        ->and($certificate->show_center_manager_signature)->toBeTrue();

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]))
        ->assertOk()
        ->assertSee($center->name)
        ->assertSee('left-logo.png', false)
        ->assertSee('right-logo.png', false)
        ->assertSee($certificate->center_manager_title)
        ->assertSee('center-stamp.png', false)
        ->assertSee('center-signature.png', false)
        ->assertSee($certificate->project_manager_title)
        ->assertSee('project-stamp.png', false)
        ->assertSee('project-signature.png', false)
        ->assertDontSee('certificate--project-only', false)
        ->assertDontSee('certificate__logo--project-solo', false)
        ->assertDontSee('certificate__signing--project-solo', false);
});

test('certificate issuance snapshots the design selected by center gender and achievement type', function () {
    [$user, $student, , $partPoint] = certificateFixture();
    Center::query()->findOrFail($student->center_id)->update([
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
    ]);

    $designSettings = app(CertificateDesignSettingsService::class);
    $designs = $designSettings->defaults();
    $designs[Center::STUDENT_GENDER_FEMALE][Certificate::ACHIEVEMENT_PART] = [
        'theme' => 'purple',
        'font' => 'naskh',
        'heading_color' => '#123456',
        'student_name_color' => '#234567',
        'content_color' => '#345678',
        'accent_color' => '#456789',
    ];
    $designSettings->update($designs);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $issuedSnapshot = $certificate->design_snapshot;
    $issuedWording = $certificate->wording_snapshot;

    expect($issuedSnapshot)->toMatchArray([
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
        'achievement_type' => Certificate::ACHIEVEMENT_PART,
        'theme' => 'purple',
        'font' => 'naskh',
        'frame_path' => 'images/certificate/certificate-frame-purple-gold.svg',
        'body_font_family' => 'Certificate Naskh',
        'display_font_family' => 'Certificate Naskh',
        'heading_color' => '#123456',
        'student_name_color' => '#234567',
        'content_color' => '#345678',
        'accent_color' => '#456789',
    ])->and($issuedWording)->toMatchArray([
        'schema_version' => 2,
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
        'achievement_type' => Certificate::ACHIEVEMENT_PART,
        'project_name' => config('certificates.wording.female.project_name'),
        'intro_before_project' => config('certificates.wording.female.intro_before_project'),
        'intro_after_center' => config('certificates.wording.female.intro_after_center'),
        'achievement_intro' => config('certificates.wording.female.achievement_intro'),
        'achievement_label' => config('certificates.achievement_labels.part'),
        'achievement_suffix' => config('certificates.wording.female.achievement_suffix'),
        'closing_text' => config('certificates.wording.female.closing_text'),
    ]);

    $designs[Center::STUDENT_GENDER_FEMALE][Certificate::ACHIEVEMENT_PART] = [
        'theme' => 'navy',
        'font' => 'amiri',
        'heading_color' => '#654321',
        'student_name_color' => '#765432',
        'content_color' => '#876543',
        'accent_color' => '#987654',
    ];
    $designSettings->update($designs);

    $certificate->refresh();
    $viewPayload = app(StudentCertificateService::class)->viewPayload($student, $certificate);

    expect($certificate->design_snapshot)->toBe($issuedSnapshot)
        ->and($certificate->wording_snapshot)->toBe($issuedWording)
        ->and($viewPayload['design'])->toMatchArray($issuedSnapshot)
        ->and($viewPayload['intro_after_center'])->toBe(config('certificates.wording.female.intro_after_center'))
        ->and($viewPayload['achievement_intro'])->toBe(config('certificates.wording.female.achievement_intro'))
        ->and($viewPayload['closing_text'])->toBe(config('certificates.wording.female.closing_text'));

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]))
        ->assertOk()
        ->assertSee('certificate-frame-purple-gold.svg', false)
        ->assertSee('--certificate-heading-color: #123456', false)
        ->assertSee('--certificate-body-font: Certificate Naskh', false);
});

test('certificates without design or wording snapshots retain masculine wording with the corrected center identity', function () {
    [$user, $student, , $partPoint] = certificateFixture();

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $certificate->update([
        'design_snapshot' => null,
        'wording_snapshot' => null,
        'project_name' => 'اسم المشروع التاريخي',
        'closing_text' => 'النص الختامي التاريخي',
    ]);
    $payload = app(StudentCertificateService::class)->viewPayload($student, $certificate->refresh());

    expect($payload['design'])->toMatchArray([
        'theme' => 'blue',
        'font' => 'classic',
        'frame_path' => 'images/certificate/certificate-frame.svg',
        'body_font_family' => 'Certificate Naskh',
        'display_font_family' => 'Certificate Amiri',
        'heading_color' => '#E8A84E',
        'student_name_color' => '#006F89',
        'content_color' => '#111111',
        'accent_color' => '#006F89',
    ])->and($payload['project_name'])->toBe('')
        ->and($payload['intro_before_project'])->toBe('تَتَقَدَّمُ إِدَارَةُ')
        ->and($payload['closing_text'])->toBe('النص الختامي التاريخي')
        ->and($payload['intro_after_center'])->toBe(config('certificates.wording.male.intro_after_center'))
        ->and($payload['achievement_intro'])->toBe(config('certificates.wording.male.achievement_intro'));
});

test('a legacy certificate without wording snapshot infers feminine wording from its saved design for html and pdf', function () {
    [$user, $student, , $partPoint] = certificateFixture();
    Center::query()->findOrFail($student->center_id)->update([
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
    ]);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    expect($certificate->design_snapshot['student_gender'])->toBe(Center::STUDENT_GENDER_FEMALE);

    $certificate->forceFill([
        'wording_snapshot' => null,
        'project_name' => 'اسم المشروع الأنثوي التاريخي',
        'closing_text' => (string) config('certificates.wording.male.closing_text'),
    ])->save();

    $payload = app(StudentCertificateService::class)->viewPayload($student, $certificate->refresh());

    expect($payload['project_name'])->toBe('')
        ->and($payload['intro_before_project'])->toBe('تَتَقَدَّمُ إِدَارَةُ')
        ->and($payload['intro_after_center'])->toBe(config('certificates.wording.female.intro_after_center'))
        ->and($payload['achievement_intro'])->toBe(config('certificates.wording.female.achievement_intro'))
        ->and($payload['closing_text'])->toBe(config('certificates.wording.female.closing_text'));

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]))
        ->assertOk()
        ->assertSee((string) config('certificates.wording.female.intro_after_center'))
        ->assertSee((string) config('certificates.wording.female.achievement_intro'))
        ->assertSee((string) config('certificates.wording.female.closing_text'));

    Pdf::fake();

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.pdf', [$student, $certificate]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        $payload = $pdf->viewData['certificate'] ?? [];

        return ($payload['intro_after_center'] ?? null) === config('certificates.wording.female.intro_after_center')
            && ($payload['achievement_intro'] ?? null) === config('certificates.wording.female.achievement_intro')
            && ($payload['closing_text'] ?? null) === config('certificates.wording.female.closing_text');
    });
});

dataset('legacy certificate wording genders', [
    'male' => [Center::STUDENT_GENDER_MALE],
    'female' => [Center::STUDENT_GENDER_FEMALE],
]);

test('v1 certificate wording is upgraded at runtime to use only the saved center identity in html and pdf', function (string $gender) {
    [$user, $student, , $partPoint] = certificateFixture();
    Center::query()->findOrFail($student->center_id)->update([
        'student_gender' => $gender,
        'certificate_name' => 'اسم المركز الرسمي المحفوظ',
    ]);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $legacyClosing = 'خاتمة تاريخية لا يجوز تغييرها';
    $legacyWording = $certificate->wording_snapshot;
    $legacyWording['schema_version'] = 1;
    $legacyWording['project_name'] = 'الحَافِظِ المُتَمَيِّزِ';
    $legacyWording['intro_before_project'] = 'تَتَقَدَّمُ إِدَارَةُ مَشْرُوعِ';
    $legacyWording['closing_text'] = $legacyClosing;

    $certificate->forceFill([
        'wording_snapshot' => $legacyWording,
        'project_name' => 'الحَافِظِ المُتَمَيِّزِ',
        'closing_text' => $legacyClosing,
    ])->save();

    $wording = app(CertificateWordingService::class)->snapshot(
        $legacyWording,
        Certificate::ACHIEVEMENT_PART,
        [],
        $gender,
    );
    $staleV2Wording = $legacyWording;
    $staleV2Wording['schema_version'] = 2;
    $normalizedStaleV2Wording = app(CertificateWordingService::class)->snapshot(
        $staleV2Wording,
        Certificate::ACHIEVEMENT_PART,
        [],
        $gender,
    );
    $payload = app(StudentCertificateService::class)->viewPayload($student, $certificate->refresh());

    expect($wording['schema_version'])->toBe(2)
        ->and($wording['student_gender'])->toBe($gender)
        ->and($wording['project_name'])->toBe('')
        ->and($wording['intro_before_project'])->toBe('تَتَقَدَّمُ إِدَارَةُ')
        ->and($wording['intro_after_center'])->toBe(config("certificates.wording.{$gender}.intro_after_center"))
        ->and($wording['closing_text'])->toBe($legacyClosing)
        ->and($normalizedStaleV2Wording['schema_version'])->toBe(2)
        ->and($normalizedStaleV2Wording['project_name'])->toBe('')
        ->and($normalizedStaleV2Wording['intro_before_project'])->toBe('تَتَقَدَّمُ إِدَارَةُ')
        ->and($payload['project_name'])->toBe('')
        ->and($payload['center_name'])->toBe('اسم المركز الرسمي المحفوظ')
        ->and($payload['intro_before_project'])->toBe('تَتَقَدَّمُ إِدَارَةُ')
        ->and($payload['intro_after_center'])->toBe(config("certificates.wording.{$gender}.intro_after_center"))
        ->and($payload['closing_text'])->toBe($legacyClosing);

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]))
        ->assertOk()
        ->assertSee('تَتَقَدَّمُ إِدَارَةُ')
        ->assertSee('اسم المركز الرسمي المحفوظ')
        ->assertDontSee('تَتَقَدَّمُ إِدَارَةُ مَشْرُوعِ')
        ->assertDontSee('الحَافِظِ المُتَمَيِّزِ');

    Pdf::fake();

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.pdf', [$student, $certificate]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($gender, $legacyClosing): bool {
        $payload = $pdf->viewData['certificate'] ?? [];

        return ($payload['project_name'] ?? null) === ''
            && ($payload['center_name'] ?? null) === 'اسم المركز الرسمي المحفوظ'
            && ($payload['intro_before_project'] ?? null) === 'تَتَقَدَّمُ إِدَارَةُ'
            && ($payload['intro_after_center'] ?? null) === config("certificates.wording.{$gender}.intro_after_center")
            && ($payload['closing_text'] ?? null) === $legacyClosing;
    });
})->with('legacy certificate wording genders');

test('issued wording remains historically stable until an explicit redesign', function () {
    [$user, $student, , $partPoint] = certificateFixture();
    Center::query()->findOrFail($student->center_id)->update([
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
    ]);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $issuedWording = $certificate->wording_snapshot;
    $preservedKeys = [
        'certificate_number',
        'achievement_type',
        'achievement_name',
        'achieved_at',
        'issued_at',
        'hijri_date',
        'gregorian_date',
    ];
    $preserved = Arr::only($certificate->getAttributes(), $preservedKeys);
    $originalWording = config('certificates.wording');
    $updatedPrefix = 'صياغة افتتاحية أنثوية جديدة للاختبار';
    $updatedIntro = 'صياغة أنثوية جديدة للاختبار';
    $updatedClosing = 'خاتمة أنثوية جديدة للاختبار';

    try {
        config()->set('certificates.wording.female.intro_before_project', $updatedPrefix);
        config()->set('certificates.wording.female.intro_after_center', $updatedIntro);
        config()->set('certificates.wording.female.closing_text', $updatedClosing);

        $unchangedPayload = app(StudentCertificateService::class)->viewPayload($student, $certificate);

        expect($certificate->wording_snapshot)->toBe($issuedWording)
            ->and($unchangedPayload['intro_before_project'])->toBe($issuedWording['intro_before_project'])
            ->and($unchangedPayload['intro_after_center'])->toBe($issuedWording['intro_after_center'])
            ->and($unchangedPayload['closing_text'])->toBe($issuedWording['closing_text'])
            ->and($unchangedPayload['intro_before_project'])->not->toBe($updatedPrefix)
            ->and($unchangedPayload['intro_after_center'])->not->toBe($updatedIntro)
            ->and($unchangedPayload['closing_text'])->not->toBe($updatedClosing);

        $this->actingAs($user, 'web')
            ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
            ->assertOk();

        $certificate->refresh();
        $redesignedPayload = app(StudentCertificateService::class)->viewPayload($student, $certificate);

        expect($certificate->wording_snapshot['student_gender'])->toBe(Center::STUDENT_GENDER_FEMALE)
            ->and($certificate->wording_snapshot['intro_before_project'])->toBe($updatedPrefix)
            ->and($certificate->wording_snapshot['intro_after_center'])->toBe($updatedIntro)
            ->and($certificate->wording_snapshot['closing_text'])->toBe($updatedClosing)
            ->and($redesignedPayload['intro_before_project'])->toBe($updatedPrefix)
            ->and($redesignedPayload['intro_after_center'])->toBe($updatedIntro)
            ->and($redesignedPayload['closing_text'])->toBe($updatedClosing)
            ->and(Arr::only($certificate->getAttributes(), $preservedKeys))->toBe($preserved);
    } finally {
        config()->set('certificates.wording', $originalWording);
    }
});

dataset('female certificate achievement types', [
    'surah' => [Certificate::ACHIEVEMENT_SURAH, 2],
    'part' => [Certificate::ACHIEVEMENT_PART, 3],
    'three parts' => [Certificate::ACHIEVEMENT_THREE_PARTS, 4],
]);

test('female wording renders in html and pdf for every achievement type', function (string $achievementType, int $pointIndex) {
    $fixture = certificateFixture();
    /** @var User $user */
    $user = $fixture[0];
    /** @var Student $student */
    $student = $fixture[1];
    /** @var PlanPoint $point */
    $point = $fixture[$pointIndex];

    Center::query()->findOrFail($student->center_id)->update([
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
    ]);

    if ($achievementType === Certificate::ACHIEVEMENT_THREE_PARTS) {
        $student->update(['current_plan_point_id' => $point->id]);
    }

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $point->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();

    expect($certificate->achievement_type)->toBe($achievementType)
        ->and($certificate->wording_snapshot['student_gender'])->toBe(Center::STUDENT_GENDER_FEMALE)
        ->and($certificate->wording_snapshot['achievement_type'])->toBe($achievementType)
        ->and($certificate->wording_snapshot['achievement_label'])
        ->toBe(config("certificates.achievement_labels.{$achievementType}"));

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]))
        ->assertOk()
        ->assertSee((string) config('certificates.wording.female.intro_after_center'))
        ->assertSee((string) config('certificates.wording.female.achievement_intro'))
        ->assertSee((string) config('certificates.wording.female.closing_text'))
        ->assertSee((string) config("certificates.achievement_labels.{$achievementType}"));

    Pdf::fake();

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.pdf', [$student, $certificate]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($achievementType): bool {
        $payload = $pdf->viewData['certificate'] ?? [];

        return ($payload['intro_after_center'] ?? null) === config('certificates.wording.female.intro_after_center')
            && ($payload['achievement_intro'] ?? null) === config('certificates.wording.female.achievement_intro')
            && ($payload['closing_text'] ?? null) === config('certificates.wording.female.closing_text')
            && ($payload['achievement_label'] ?? null) === config("certificates.achievement_labels.{$achievementType}");
    });
})->with('female certificate achievement types');

test('certificate preview and pdf load only the assets used by the selected font preset', function () {
    [$user, $student, , $partPoint] = certificateFixture();
    $designSettings = app(CertificateDesignSettingsService::class);
    $designs = $designSettings->defaults();
    $designs[Center::STUDENT_GENDER_MALE][Certificate::ACHIEVEMENT_PART]['font'] = 'naskh_nastaliq';
    $designSettings->update($designs);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $service = app(StudentCertificateService::class);
    $webPayload = $service->viewPayload($student, $certificate);
    $preloads = implode('\n', $webPayload['font_preload_urls']);

    expect($webPayload['font_preload_urls'])->toHaveCount(4)
        ->and($preloads)->toContain('NotoNaskhArabic-Regular.ttf')
        ->toContain('NotoNaskhArabic-Bold.ttf')
        ->toContain('NotoNastaliqUrdu-Regular.ttf')
        ->toContain('NotoNastaliqUrdu-Bold.ttf')
        ->not->toContain('Amiri-Regular.ttf')
        ->not->toContain('NotoKufiArabic-Regular.ttf')
        ->not->toContain('NotoSansArabic-Regular.ttf');

    $pdfPayload = $service->viewPayload($student, $certificate, true);
    $stylesheet = base64_decode(
        substr($pdfPayload['stylesheet_url'], strlen('data:text/css;base64,')),
        true,
    );

    expect($stylesheet)->toBeString()
        ->and(substr_count($stylesheet, 'data:font/ttf;base64,'))->toBe(4)
        ->and($stylesheet)->not->toContain('../fonts/certificate/NotoNaskhArabic-Regular.ttf')
        ->not->toContain('../fonts/certificate/NotoNastaliqUrdu-Regular.ttf')
        ->toContain('../fonts/certificate/NotoKufiArabic-Regular.ttf');
});

test('certificate display avoids repeating the achievement type inside its saved name', function () {
    [$user, $student, , $partPoint, $threePartsPoint] = certificateFixture();
    $service = app(StudentCertificateService::class);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $partCertificate = Certificate::query()->sole();
    expect($partCertificate->achievement_name)->toBe('الجزء الأول')
        ->and($service->viewPayload($student, $partCertificate)['achievement_name'])->toBe('الأول');

    $student->update(['current_plan_point_id' => $threePartsPoint->id]);
    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $threePartsPoint->id,
        ])
        ->assertCreated();

    $threePartsCertificate = Certificate::query()
        ->where('plan_point_id', $threePartsPoint->id)
        ->sole();

    expect($threePartsCertificate->achievement_name)->toBe('الأجزاء الثلاثة الأولى')
        ->and($service->viewPayload($student, $threePartsCertificate)['achievement_name'])->toBe('الأولى');
});

test('an issued certificate can be redesigned with the current center design without changing its issuance data', function () {
    Carbon::setTestNow('2026-08-28 12:00:00');
    [$user, $student, , $partPoint] = certificateFixture();

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $issuedSnapshot = $certificate->design_snapshot;
    $immutableAttributes = Arr::except($certificate->getAttributes(), [
        'design_snapshot',
        'wording_snapshot',
        'project_name',
        'closing_text',
        'updated_at',
    ]);

    Center::query()->findOrFail($student->center_id)->update([
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
    ]);

    $designSettings = app(CertificateDesignSettingsService::class);
    $designs = $designSettings->defaults();
    $designs[Center::STUDENT_GENDER_FEMALE][Certificate::ACHIEVEMENT_PART] = [
        'theme' => 'purple',
        'font' => 'naskh',
        'heading_color' => '#112233',
        'student_name_color' => '#223344',
        'content_color' => '#334455',
        'accent_color' => '#445566',
    ];
    $designSettings->update($designs);
    $expectedSnapshot = $designSettings->resolve(
        Center::STUDENT_GENDER_FEMALE,
        Certificate::ACHIEVEMENT_PART,
    );
    $expectedWording = app(CertificateWordingService::class)->resolve(
        Center::STUDENT_GENDER_FEMALE,
        Certificate::ACHIEVEMENT_PART,
    );

    expect($issuedSnapshot)->not->toBe($expectedSnapshot);

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.index', $student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canRedesign', true)
            ->has('certificates', 1)
            ->where(
                'certificates.0.redesign_url',
                route('admin.students.certificates.redesign', [$student, $certificate]),
            ));

    $this->actingAs($user, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertOk()
        ->assertJsonPath('certificate.id', $certificate->ulid)
        ->assertJsonPath('certificate.redesign_url', route('admin.students.certificates.redesign', [$student, $certificate]));

    $certificate->refresh();
    $firstRedesignSnapshot = $certificate->design_snapshot;
    $firstRedesignWording = $certificate->wording_snapshot;

    expect($firstRedesignSnapshot)->toBe($expectedSnapshot)
        ->and($firstRedesignWording)->toBe($expectedWording)
        ->and($certificate->project_name)->toBe($expectedWording['project_name'])
        ->and($certificate->closing_text)->toBe($expectedWording['closing_text'])
        ->and(Arr::except($certificate->getAttributes(), [
            'design_snapshot',
            'wording_snapshot',
            'project_name',
            'closing_text',
            'updated_at',
        ]))
        ->toBe($immutableAttributes);

    $this->actingAs($user, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertOk();

    expect($certificate->refresh()->design_snapshot)->toBe($firstRedesignSnapshot)
        ->and($certificate->wording_snapshot)->toBe($firstRedesignWording)
        ->and(Arr::except($certificate->getAttributes(), [
            'design_snapshot',
            'wording_snapshot',
            'project_name',
            'closing_text',
            'updated_at',
        ]))
        ->toBe($immutableAttributes);
});

test('certificate redesign snapshots the current center identity visibility without changing issuance data', function () {
    [$user, $student, , $partPoint] = certificateFixture();
    $center = Center::query()->findOrFail($student->center_id);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $preservedAttributes = Arr::except($certificate->getAttributes(), [
        'design_snapshot',
        'wording_snapshot',
        'project_name',
        'closing_text',
        'show_center_manager_signature',
        'updated_at',
    ]);

    expect($certificate->show_center_manager_signature)->toBeTrue();

    $center->update(['show_center_manager_signature' => false]);

    $this->actingAs($user, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertOk();

    $certificate->refresh();
    $hiddenPayload = app(StudentCertificateService::class)->viewPayload($student, $certificate);

    expect($certificate->show_center_manager_signature)->toBeFalse()
        ->and(Arr::except($certificate->getAttributes(), [
            'design_snapshot',
            'wording_snapshot',
            'project_name',
            'closing_text',
            'show_center_manager_signature',
            'updated_at',
        ]))->toBe($preservedAttributes)
        ->and($hiddenPayload['show_center_manager_signature'])->toBeFalse()
        ->and($hiddenPayload['images']['left_logo'])->toBe('')
        ->and($hiddenPayload['images']['center_stamp'])->toBe('')
        ->and($hiddenPayload['images']['center_signature'])->toBe('');

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]))
        ->assertOk()
        ->assertSee('certificate--project-only', false)
        ->assertSee('class="certificate__logo certificate__logo--right"', false)
        ->assertDontSee('certificate__logo--project-solo', false)
        ->assertSee('certificate__signing--project-solo', false)
        ->assertDontSee('left-logo.png', false)
        ->assertDontSee('center-stamp.png', false)
        ->assertDontSee('center-signature.png', false);

    Pdf::fake();

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.pdf', [$student, $certificate]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        $payload = $pdf->viewData['certificate'] ?? [];
        $images = $payload['images'] ?? [];

        return ($payload['show_center_manager_signature'] ?? true) === false
            && ($images['left_logo'] ?? null) === ''
            && ($images['center_stamp'] ?? null) === ''
            && ($images['center_signature'] ?? null) === '';
    });

    $center->update(['show_center_manager_signature' => true]);

    $this->actingAs($user, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertOk();

    $certificate->refresh();
    $visiblePayload = app(StudentCertificateService::class)->viewPayload($student, $certificate);

    expect($certificate->show_center_manager_signature)->toBeTrue()
        ->and(Arr::except($certificate->getAttributes(), [
            'design_snapshot',
            'wording_snapshot',
            'project_name',
            'closing_text',
            'show_center_manager_signature',
            'updated_at',
        ]))->toBe($preservedAttributes)
        ->and($visiblePayload['show_center_manager_signature'])->toBeTrue()
        ->and($visiblePayload['images']['left_logo'])->not->toBe('')
        ->and($visiblePayload['images']['center_stamp'])->not->toBe('')
        ->and($visiblePayload['images']['center_signature'])->not->toBe('');

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]))
        ->assertOk()
        ->assertDontSee('certificate--project-only', false)
        ->assertSee('left-logo.png', false)
        ->assertSee('center-stamp.png', false)
        ->assertSee('center-signature.png', false);
});

test('certificate redesign falls back to its saved gender and then to male when the student has no center', function () {
    [$user, $student, , $partPoint] = certificateFixture();
    Center::query()->findOrFail($student->center_id)->update([
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
        'show_center_manager_signature' => false,
    ]);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $student->update(['center_id' => null]);
    $designSettings = app(CertificateDesignSettingsService::class);
    $designs = $designSettings->defaults();
    $designs[Center::STUDENT_GENDER_FEMALE][Certificate::ACHIEVEMENT_PART]['theme'] = 'purple';
    $designs[Center::STUDENT_GENDER_MALE][Certificate::ACHIEVEMENT_PART]['theme'] = 'navy';
    $designSettings->update($designs);

    $this->actingAs($user, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertOk();

    expect($certificate->refresh()->design_snapshot['student_gender'])->toBe(Center::STUDENT_GENDER_FEMALE)
        ->and($certificate->design_snapshot['theme'])->toBe('purple')
        ->and($certificate->wording_snapshot['student_gender'])->toBe(Center::STUDENT_GENDER_FEMALE)
        ->and($certificate->show_center_manager_signature)->toBeFalse();

    $certificate->update(['design_snapshot' => null]);

    $this->actingAs($user, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertOk();

    expect($certificate->refresh()->design_snapshot['student_gender'])->toBe(Center::STUDENT_GENDER_FEMALE)
        ->and($certificate->design_snapshot['theme'])->toBe('purple')
        ->and($certificate->wording_snapshot['student_gender'])->toBe(Center::STUDENT_GENDER_FEMALE)
        ->and($certificate->show_center_manager_signature)->toBeFalse();

    $certificate->update([
        'design_snapshot' => null,
        'wording_snapshot' => null,
    ]);

    $this->actingAs($user, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertOk();

    expect($certificate->refresh()->design_snapshot['student_gender'])->toBe(Center::STUDENT_GENDER_MALE)
        ->and($certificate->design_snapshot['theme'])->toBe('navy')
        ->and($certificate->wording_snapshot['student_gender'])->toBe(Center::STUDENT_GENDER_MALE)
        ->and($certificate->show_center_manager_signature)->toBeFalse();
});

test('certificate redesign enforces update permission nested ownership and supervisor scope', function () {
    [$owner, $student, , $partPoint] = certificateFixture();

    $this->actingAs($owner, 'web')
        ->postJson(route('admin.students.certificates.store', $student), [
            'plan_point_id' => $partPoint->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('students.view');

    $this->actingAs($viewer, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertForbidden();

    $otherSupervisor = User::factory()->create();
    $otherSupervisor->givePermissionTo(['students.view', 'students.update']);

    $this->actingAs($otherSupervisor, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertNotFound();

    $wrongStudent = Student::factory()->create(['admin_id' => $owner->id]);

    $this->actingAs($owner, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$wrongStudent, $certificate]))
        ->assertNotFound();
});

test('certificate preview escapes long student names and its pdf is A4 landscape', function () {
    $longName = '<script>alert(1)</script> عبد الرحمن بن محمد بن عبد الله صاحب الاسم العربي الطويل جدًا للاختبار';
    [$user, $student, , $partPoint] = certificateFixture($longName);

    $this->actingAs($user, 'web')->postJson(
        route('admin.students.certificates.store', $student),
        ['plan_point_id' => $partPoint->id],
    )->assertCreated();

    $certificate = Certificate::query()->sole();
    $certificate->forceFill(['gregorian_date' => '٢٠/أغسطس/٢٠٢٦'])->save();

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]))
        ->assertOk()
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>', false)
        ->assertSee('certificate__student--extra-long', false)
        ->assertSee('٢٠٢٦/٠٨/٢٠')
        ->assertDontSee('٢٠/أغسطس/٢٠٢٦')
        ->assertDontSee($certificate->certificate_number);

    Pdf::fake();

    $this->actingAs($user, 'web')
        ->get(route('admin.students.certificates.pdf', [$student, $certificate]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($certificate): bool {
        return $pdf->viewName === 'certificates.show'
            && $pdf->downloadName === "certificate-{$certificate->certificate_number}.pdf"
            && ($pdf->viewData['certificate']['pdf_mode'] ?? false) === true
            && str_starts_with($pdf->viewData['certificate']['stylesheet_url'] ?? '', 'data:text/css;base64,')
            && str_starts_with($pdf->viewData['certificate']['images']['frame'] ?? '', 'data:image/svg+xml;base64,');
    });
});

test('a scoped supervisor cannot view another supervisors student certificates', function () {
    [$owner, $student, , $partPoint] = certificateFixture();

    $this->actingAs($owner, 'web')->postJson(
        route('admin.students.certificates.store', $student),
        ['plan_point_id' => $partPoint->id],
    )->assertCreated();

    $certificate = Certificate::query()->sole();
    $otherSupervisor = User::factory()->create();
    $otherSupervisor->givePermissionTo(['students.view', 'students.update']);

    $this->actingAs($otherSupervisor, 'web')
        ->get(route('admin.students.certificates.index', $student))
        ->assertNotFound();

    $this->actingAs($otherSupervisor, 'web')
        ->get(route('admin.students.certificates.show', [$student, $certificate]))
        ->assertNotFound();

    $this->actingAs($otherSupervisor, 'web')
        ->putJson(route('admin.students.certificates.redesign', [$student, $certificate]))
        ->assertNotFound();
});

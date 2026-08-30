<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\System\CertificateDesignSettingsService;
use App\Services\System\CertificateQrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * @param  list<string>  $permissions
 */
function certificateDesignSettingsUser(array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function certificateDesignPdfPreviewPayload(int $planPointId, int $centerId): array
{
    return [
        'center_id' => $centerId,
        'plan_point_id' => $planPointId,
        'design' => [
            'theme' => 'purple',
            'font' => 'naskh_nastaliq',
            'heading_color' => '#123456',
            'student_name_color' => '#234567',
            'content_color' => '#345678',
            'accent_color' => '#456789',
        ],
    ];
}

function certificateDesignPreviewCenter(array $attributes = []): Center
{
    return Center::factory()->create([
        'name' => 'مركز المعاينة',
        'certificate_name' => 'الاسم الرسمي للشهادة',
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
        'show_center_manager_signature' => false,
        ...$attributes,
    ]);
}

function certificateDesignPreviewPoint(array $attributes = []): PlanPoint
{
    $plan = Plan::factory()->create([
        'name' => $attributes['plan_name'] ?? 'خطة المعاينة الحقيقية',
    ]);
    unset($attributes['plan_name']);

    return PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'name' => 'نقطة إنجاز الجزء السابع',
        'requires_certificate' => true,
        'surah_name' => null,
        'part_name' => 'الجزء السابع',
        'three_parts' => null,
        ...$attributes,
    ]);
}

test('certificate design page exposes the complete defaults and catalog', function () {
    $user = certificateDesignSettingsUser([
        'certificate_designs.view',
        'certificate_designs.update',
    ]);
    $maleCenter = certificateDesignPreviewCenter([
        'name' => 'مركز افتراضي للذكور',
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $femaleCenter = certificateDesignPreviewCenter([
        'name' => 'مركز افتراضي للإناث',
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
    ]);

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CertificateDesigns', false)
            ->where('canUpdate', true)
            ->where("designs.{$maleCenter->id}.surah.theme", 'blue')
            ->where("designs.{$maleCenter->id}.part.theme", 'blue')
            ->where("designs.{$maleCenter->id}.three_parts.theme", 'blue')
            ->where("designs.{$femaleCenter->id}.surah.theme", 'rose')
            ->where("designs.{$femaleCenter->id}.part.theme", 'rose')
            ->where("designs.{$femaleCenter->id}.three_parts.theme", 'rose')
            ->where("designs.{$maleCenter->id}.surah.font", 'classic')
            ->where('catalog.genders.0.value', Center::STUDENT_GENDER_MALE)
            ->where('catalog.genders.1.value', Center::STUDENT_GENDER_FEMALE)
            ->where('catalog.achievementTypes.0.value', Certificate::ACHIEVEMENT_SURAH)
            ->where('catalog.achievementTypes.1.value', Certificate::ACHIEVEMENT_PART)
            ->where('catalog.achievementTypes.2.value', Certificate::ACHIEVEMENT_THREE_PARTS)
            ->has('catalog.themes', 20)
            ->has('catalog.fonts', 12)
            ->has('centers', 2)
            ->has('previewCenters.male', 1)
            ->has('previewCenters.female', 1));
});

test('certificate design page groups real centers and resolves their certificate names', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $maleCenter = certificateDesignPreviewCenter([
        'name' => 'مركز الذكور',
        'certificate_name' => '   ',
        'student_gender' => Center::STUDENT_GENDER_MALE,
        'show_center_manager_signature' => true,
    ]);
    $femaleCenter = certificateDesignPreviewCenter([
        'name' => 'مركز الإناث',
        'certificate_name' => '  الاسم المعتمد للشهادة  ',
    ]);

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('previewCenters.male', 1)
            ->where('previewCenters.male.0.id', $maleCenter->id)
            ->where('previewCenters.male.0.name', 'مركز الذكور')
            ->where('previewCenters.male.0.center_name', 'مركز الذكور')
            ->where('previewCenters.male.0.student_gender', Center::STUDENT_GENDER_MALE)
            ->where('previewCenters.male.0.show_center_manager_signature', true)
            ->has('previewCenters.female', 1)
            ->where('previewCenters.female.0.id', $femaleCenter->id)
            ->where('previewCenters.female.0.center_name', 'الاسم المعتمد للشهادة')
            ->where('previewCenters.female.0.student_gender', Center::STUDENT_GENDER_FEMALE)
            ->where('previewCenters.female.0.show_center_manager_signature', false));
});

test('every certificate theme has a readable frame asset', function () {
    $themes = config('certificates.themes', []);

    expect($themes)->toHaveCount(20);

    foreach ($themes as $key => $theme) {
        $path = public_path('images/certificate/'.($theme['frame'] ?? ''));

        expect(is_file($path))->toBeTrue("Missing frame for theme [{$key}].")
            ->and(filesize($path))->toBeGreaterThan(0);
    }
});

test('every certificate font family has readable regular and bold assets', function () {
    $families = config('certificates.font_families', []);
    $presets = config('certificates.fonts', []);

    expect($families)->toHaveCount(5)
        ->and($presets)->toHaveCount(12);

    foreach ($families as $key => $family) {
        foreach (['regular_path', 'bold_path'] as $pathKey) {
            $path = public_path((string) ($family[$pathKey] ?? ''));

            expect(is_file($path))->toBeTrue("Missing {$pathKey} for font family [{$key}].")
                ->and(filesize($path))->toBeGreaterThan(0);
        }
    }

    foreach ($presets as $key => $preset) {
        expect(($preset['families'] ?? []) !== [])->toBeTrue("Font preset [{$key}] has no families.");

        foreach ($preset['families'] as $familyKey) {
            expect(array_key_exists($familyKey, $families))
                ->toBeTrue("Unknown family [{$familyKey}] in preset [{$key}].");
        }
    }
});

test('certificate design page groups real certificate plan points by resolved achievement type', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $laterPlan = Plan::factory()->create(['name' => 'خطة باء']);
    $firstPlan = Plan::factory()->create(['name' => 'خطة ألف']);

    $earlyPart = PlanPoint::factory()->create([
        'plan_id' => $firstPlan->id,
        'sort_order' => 5,
        'name' => 'نقطة الجزء الأول',
        'requires_certificate' => true,
        'part_name' => 'الجزء الأول',
    ]);
    $firstPart = PlanPoint::factory()->create([
        'plan_id' => $firstPlan->id,
        'sort_order' => 20,
        'name' => 'نقطة الجزء الثاني',
        'requires_certificate' => true,
        'part_name' => 'الجزء الثاني',
    ]);
    $laterPart = PlanPoint::factory()->create([
        'plan_id' => $laterPlan->id,
        'sort_order' => 1,
        'name' => 'نقطة الجزء التاسع',
        'requires_certificate' => true,
        'part_name' => 'الجزء التاسع',
    ]);
    $broadest = PlanPoint::factory()->create([
        'plan_id' => $firstPlan->id,
        'sort_order' => 30,
        'name' => 'نقطة متعددة الحقول',
        'requires_certificate' => true,
        'surah_name' => 'سورة البقرة',
        'part_name' => 'الجزء الأول',
        'three_parts' => 'الأجزاء الثلاثة الأولى',
    ]);
    PlanPoint::factory()->create([
        'plan_id' => $firstPlan->id,
        'requires_certificate' => false,
        'surah_name' => 'سورة لا تظهر',
    ]);
    PlanPoint::factory()->create([
        'plan_id' => $firstPlan->id,
        'requires_certificate' => true,
        'surah_name' => '   ',
        'part_name' => null,
        'three_parts' => null,
    ]);

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('previewAchievements.surah', 0)
            ->has('previewAchievements.part', 3)
            ->where('previewAchievements.part.0.id', $earlyPart->id)
            ->where('previewAchievements.part.0.achievement_type', Certificate::ACHIEVEMENT_PART)
            ->where('previewAchievements.part.0.achievement_name', 'الأول')
            ->where('previewAchievements.part.0.plan_name', $firstPlan->name)
            ->where('previewAchievements.part.0.plan_point_name', $earlyPart->name)
            ->where('previewAchievements.part.1.id', $firstPart->id)
            ->where('previewAchievements.part.1.achievement_name', 'الثاني')
            ->where('previewAchievements.part.2.id', $laterPart->id)
            ->has('previewAchievements.three_parts', 1)
            ->where('previewAchievements.three_parts.0.id', $broadest->id)
            ->where('previewAchievements.three_parts.0.achievement_name', 'الأولى'));
});

test('certificate design preview uses the real certificate and its actual assets', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $center = certificateDesignPreviewCenter([
        'name' => 'مركز الذكور للمعاينة',
        'certificate_name' => 'المركز الرسمي للذكور',
        'student_gender' => Center::STUDENT_GENDER_MALE,
        'show_center_manager_signature' => true,
    ]);
    $point = certificateDesignPreviewPoint();

    $response = $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.preview'));

    $response->assertOk()
        ->assertSee('certificate-design-preview:update', false)
        ->assertSee('certificate__verification--preview', false)
        ->assertSee('fill="currentColor"', false)
        ->assertSee('--certificate-qr-color', false)
        ->assertSee('شكل QR تجريبي غير قابل للمسح')
        ->assertSee('left-logo.png', false)
        ->assertSee('right-logo.png', false)
        ->assertSee('center-stamp.png', false)
        ->assertSee('center-signature.png', false)
        ->assertSee('project-stamp.png', false)
        ->assertSee('project-signature.png', false)
        ->assertSee('المركز الرسمي للذكور')
        ->assertViewHas('certificate', function (array $certificate) use ($center, $point): bool {
            $wording = $certificate['preview_samples']['wording'] ?? [];

            return ($certificate['show_center_manager_signature'] ?? false) === true
                && ($certificate['verification_preview'] ?? false) === true
                && ($certificate['qr_foreground_color'] ?? null)
                    === app(CertificateQrCodeService::class)->foregroundHex(
                        (string) ($certificate['design']['accent_color'] ?? ''),
                    )
                && ! array_key_exists('qr_code_data_uri', $certificate)
                && ! array_key_exists('verification_url', $certificate)
                && ($certificate['center_name'] ?? null) === 'المركز الرسمي للذكور'
                && ($certificate['achievement_name'] ?? null) === 'السابع'
                && ($certificate['design']['student_gender'] ?? null) === Center::STUDENT_GENDER_MALE
                && ($certificate['design']['achievement_type'] ?? null) === Certificate::ACHIEVEMENT_PART
                && ($certificate['preview_catalog']['achievements'][(string) $point->id]['achievement_name'] ?? null) === 'السابع'
                && ($certificate['preview_catalog']['centers'][(string) $center->id]['student_gender'] ?? null)
                    === Center::STUDENT_GENDER_MALE
                && ($certificate['preview_catalog']['centers'][(string) $center->id]['center_name'] ?? null)
                    === 'المركز الرسمي للذكور'
                && ($certificate['preview_catalog']['centers'][(string) $center->id]['show_center_manager_signature'] ?? false)
                    === true
                && ($wording[Center::STUDENT_GENDER_MALE]['achievement_intro'] ?? null)
                    === config('certificates.wording.male.achievement_intro')
                && ($wording[Center::STUDENT_GENDER_FEMALE]['achievement_intro'] ?? null)
                    === config('certificates.wording.female.achievement_intro')
                && ($wording[Center::STUDENT_GENDER_FEMALE]['closing_text'] ?? null)
                    === config('certificates.wording.female.closing_text');
        });

    expect(preg_match(
        '/<p class="certificate__intro"[^>]*>(.*?)<\/p>/su',
        $response->getContent(),
        $introMatches,
    ))->toBe(1);

    $introText = preg_replace(
        '/\s+/u',
        ' ',
        trim(html_entity_decode(strip_tags($introMatches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
    );

    expect($introText)->toBe(implode(' ', [
        trim((string) config('certificates.wording.male.intro_before_project')),
        'المركز الرسمي للذكور',
        trim((string) config('certificates.wording.male.intro_after_center')),
    ]));
    expect($response->getContent())->not->toContain('data-certificate-preview-project-name');

    expect(preg_match(
        '/<div class="certificate__verification certificate__verification--preview".*?<\/div>/su',
        $response->getContent(),
        $verificationPreviewMatches,
    ))->toBe(1);

    expect($verificationPreviewMatches[0])
        ->toContain('<svg')
        ->not->toContain('href=')
        ->not->toContain('data:image')
        ->not->toContain('/verify/');

    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized, 'web')
        ->get(route('admin.certificate-designs.preview'))
        ->assertForbidden();
});

test('certificate design web preview starts from a real hidden female center but keeps switchable assets', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $center = certificateDesignPreviewCenter([
        'certificate_name' => 'مركز الإناث المعتمد',
    ]);
    certificateDesignPreviewPoint();

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.preview'))
        ->assertOk()
        ->assertSee('class="certificate__logo certificate__logo--right"', false)
        ->assertDontSee('certificate__logo--project-solo', false)
        ->assertViewHas('certificate', function (array $certificate) use ($center): bool {
            $images = $certificate['images'] ?? [];

            return ($certificate['center_name'] ?? null) === 'مركز الإناث المعتمد'
                && ($certificate['show_center_manager_signature'] ?? true) === false
                && ($certificate['design']['student_gender'] ?? null) === Center::STUDENT_GENDER_FEMALE
                && ($certificate['center_manager_title'] ?? '') !== ''
                && str_contains((string) ($images['left_logo'] ?? ''), 'left-logo.png')
                && str_contains((string) ($images['center_stamp'] ?? ''), 'center-stamp.png')
                && str_contains((string) ($images['center_signature'] ?? ''), 'center-signature.png')
                && ($certificate['preview_catalog']['centers'][(string) $center->id]['show_center_manager_signature'] ?? true)
                    === false;
        });
});

test('certificate design preview shows an explicit empty state when plans have no certificate achievements', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.preview'))
        ->assertOk()
        ->assertViewHas('certificate', fn (array $certificate): bool => ($certificate['achievement_name'] ?? null) === '—'
            && ($certificate['center_name'] ?? null) === '—'
            && ($certificate['show_center_manager_signature'] ?? true) === false
            && ($certificate['preview_catalog']['achievements'] ?? null) === []
            && ($certificate['preview_catalog']['centers'] ?? null) === []
        )
        ->assertDontSee('الجزء الأول')
        ->assertDontSee('الأجزاء الثلاثة الأولى');
});

test('a read only design user can download a real pdf preview of unsaved nested design values', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    certificateDesignPreviewCenter([
        'name' => 'أول مركز في القائمة لا يجب تطبيقه',
        'certificate_name' => 'هوية مركز غير مختار',
        'student_gender' => Center::STUDENT_GENDER_MALE,
        'show_center_manager_signature' => true,
    ]);
    $center = certificateDesignPreviewCenter([
        'name' => 'مركز الإناث المختار',
        'certificate_name' => 'مركز الإناث في ملف PDF',
    ]);
    $point = certificateDesignPreviewPoint();
    Pdf::fake();

    $this->actingAs($user, 'web')
        ->postJson(
            route('admin.certificate-designs.preview.pdf'),
            certificateDesignPdfPreviewPayload($point->id, $center->id),
        )
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        $certificate = $pdf->viewData['certificate'] ?? [];
        $design = $certificate['design'] ?? [];
        $stylesheet = base64_decode(
            substr((string) ($certificate['stylesheet_url'] ?? ''), strlen('data:text/css;base64,')),
            true,
        );

        return $pdf->viewName === 'certificates.show'
            && $pdf->downloadName === 'certificate-design-preview.pdf'
            && ($certificate['pdf_mode'] ?? false) === true
            && ($certificate['design_preview_mode'] ?? true) === false
            && ($certificate['verification_preview'] ?? false) === true
            && ($certificate['qr_foreground_color'] ?? null)
                === app(CertificateQrCodeService::class)->foregroundHex('#456789')
            && ! array_key_exists('qr_code_data_uri', $certificate)
            && ! array_key_exists('verification_url', $certificate)
            && ($certificate['show_center_manager_signature'] ?? true) === false
            && ($certificate['center_name'] ?? null) === 'مركز الإناث في ملف PDF'
            && ($certificate['intro_before_project'] ?? null) === config('certificates.wording.female.intro_before_project')
            && ($certificate['project_name'] ?? null) === config('certificates.wording.female.project_name')
            && ($certificate['center_manager_title'] ?? null) === ''
            && ($design['student_gender'] ?? null) === Center::STUDENT_GENDER_FEMALE
            && ($design['achievement_type'] ?? null) === Certificate::ACHIEVEMENT_PART
            && ($design['theme'] ?? null) === 'purple'
            && ($design['font'] ?? null) === 'naskh_nastaliq'
            && ($design['frame_path'] ?? null) === 'images/certificate/certificate-frame-purple-gold.svg'
            && ($design['heading_color'] ?? null) === '#123456'
            && ($design['student_name_color'] ?? null) === '#234567'
            && ($design['content_color'] ?? null) === '#345678'
            && ($design['accent_color'] ?? null) === '#456789'
            && ($certificate['achievement_intro'] ?? null) === config('certificates.wording.female.achievement_intro')
            && ($certificate['achievement_name'] ?? null) === 'السابع'
            && ($certificate['intro_after_center'] ?? null) === config('certificates.wording.female.intro_after_center')
            && ($certificate['closing_text'] ?? null) === config('certificates.wording.female.closing_text')
            && str_starts_with((string) ($certificate['stylesheet_url'] ?? ''), 'data:text/css;base64,')
            && is_string($stylesheet)
            && substr_count($stylesheet, 'data:font/ttf;base64,') === 4
            && str_starts_with((string) ($certificate['images']['frame'] ?? ''), 'data:image/svg+xml;base64,')
            && ($certificate['images']['left_logo'] ?? null) === ''
            && ($certificate['images']['center_stamp'] ?? null) === ''
            && ($certificate['images']['center_signature'] ?? null) === ''
            && str_starts_with((string) ($certificate['images']['right_logo'] ?? ''), 'data:image/png;base64,')
            && str_starts_with((string) ($certificate['images']['project_stamp'] ?? ''), 'data:image/png;base64,')
            && str_starts_with((string) ($certificate['images']['project_signature'] ?? ''), 'data:image/png;base64,');
    });

    expect(SystemSetting::query()
        ->where('key', CertificateDesignSettingsService::SETTING_KEY)
        ->exists())->toBeFalse();
});

test('certificate design pdf preview derives male wording and full identity from the selected center', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $center = certificateDesignPreviewCenter([
        'name' => 'مركز الذكور للشهادة',
        'certificate_name' => null,
        'student_gender' => Center::STUDENT_GENDER_MALE,
        'show_center_manager_signature' => true,
    ]);
    $point = certificateDesignPreviewPoint();
    Pdf::fake();

    $this->actingAs($user, 'web')
        ->postJson(
            route('admin.certificate-designs.preview.pdf'),
            certificateDesignPdfPreviewPayload($point->id, $center->id),
        )
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        $certificate = $pdf->viewData['certificate'] ?? [];
        $images = $certificate['images'] ?? [];

        return ($certificate['design']['student_gender'] ?? null) === Center::STUDENT_GENDER_MALE
            && ($certificate['show_center_manager_signature'] ?? false) === true
            && ($certificate['center_name'] ?? null) === 'مركز الذكور للشهادة'
            && ($certificate['intro_after_center'] ?? null) === config('certificates.wording.male.intro_after_center')
            && ($certificate['achievement_intro'] ?? null) === config('certificates.wording.male.achievement_intro')
            && ($certificate['closing_text'] ?? null) === config('certificates.wording.male.closing_text')
            && str_starts_with((string) ($images['left_logo'] ?? ''), 'data:image/png;base64,')
            && str_starts_with((string) ($images['center_stamp'] ?? ''), 'data:image/png;base64,')
            && str_starts_with((string) ($images['center_signature'] ?? ''), 'data:image/png;base64,');
    });
});

test('certificate design pdf preview strictly validates its whitelisted nested payload', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $center = certificateDesignPreviewCenter();
    $point = certificateDesignPreviewPoint(['requires_certificate' => false]);

    $payload = certificateDesignPdfPreviewPayload($point->id, $center->id);
    $payload['center_id'] = $center->id + 999999;
    $payload['gender'] = 'unknown';
    $payload['achievement_type'] = 'unknown';
    $payload['design']['theme'] = 'unknown-theme';
    $payload['design']['font'] = 'unknown-font';
    $payload['design']['heading_color'] = 'red';
    $payload['design']['student_name_color'] = '#12345G';
    $payload['design']['frame'] = '../../outside.svg';

    $this->actingAs($user, 'web')
        ->postJson(route('admin.certificate-designs.preview.pdf'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'center_id',
            'gender',
            'achievement_type',
            'plan_point_id',
            'design',
            'design.theme',
            'design.font',
            'design.heading_color',
            'design.student_name_color',
        ]);

    expect(SystemSetting::query()
        ->where('key', CertificateDesignSettingsService::SETTING_KEY)
        ->exists())->toBeFalse();
});

test('certificate design pdf preview rejects a client supplied gender instead of allowing center gender spoofing', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $center = certificateDesignPreviewCenter([
        'student_gender' => Center::STUDENT_GENDER_MALE,
        'show_center_manager_signature' => true,
    ]);
    $point = certificateDesignPreviewPoint();
    $payload = certificateDesignPdfPreviewPayload($point->id, $center->id);
    $payload['gender'] = Center::STUDENT_GENDER_FEMALE;

    $this->actingAs($user, 'web')
        ->postJson(route('admin.certificate-designs.preview.pdf'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('gender');
});

test('certificate design pdf preview rejects a certificate point without achievement data', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $center = certificateDesignPreviewCenter();
    $point = certificateDesignPreviewPoint([
        'surah_name' => null,
        'part_name' => '   ',
        'three_parts' => null,
    ]);

    $this->actingAs($user, 'web')
        ->postJson(
            route('admin.certificate-designs.preview.pdf'),
            certificateDesignPdfPreviewPayload($point->id, $center->id),
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan_point_id');
});

test('certificate design pdf preview requires the view permission', function () {
    $user = User::factory()->create();
    $center = certificateDesignPreviewCenter();
    $point = certificateDesignPreviewPoint();
    Pdf::fake();

    $this->actingAs($user, 'web')
        ->postJson(
            route('admin.certificate-designs.preview.pdf'),
            certificateDesignPdfPreviewPayload($point->id, $center->id),
        )
        ->assertForbidden();
});

test('a read only user can view certificate designs but cannot update them', function () {
    Permission::findOrCreate('certificate_designs.view', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('certificate_designs.view');
    $designs = app(CertificateDesignSettingsService::class)->defaults();

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canUpdate', false));

    $this->actingAs($user, 'web')
        ->put(route('admin.certificate-designs.update'), ['designs' => $designs])
        ->assertForbidden();
});

test('certificate designs can be updated and are cached under an independent setting key', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.update']);
    $center = certificateDesignPreviewCenter([
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $service = app(CertificateDesignSettingsService::class);
    $designs = $service->designsForCenter($center);
    $designs['part'] = [
        'theme' => 'purple',
        'font' => 'naskh',
        'heading_color' => '#123456',
        'student_name_color' => '#234567',
        'content_color' => '#345678',
        'accent_color' => '#456789',
    ];

    $this->actingAs($user, 'web')
        ->from(route('admin.certificate-designs.index'))
        ->put(route('admin.certificate-designs.update'), [
            'center_id' => $center->id,
            'designs' => $designs,
        ])
        ->assertRedirect(route('admin.certificate-designs.index'))
        ->assertSessionHas('success');

    $stored = SystemSetting::query()
        ->where('key', CertificateDesignSettingsService::SETTING_KEY)
        ->sole();

    expect($stored->value['version'])->toBe(2)
        ->and($stored->value['defaults']['male']['part']['theme'])->toBe('blue')
        ->and($stored->value['centers'][$center->id]['part']['theme'])->toBe('purple')
        ->and($service->designsForCenter($center)['part'])->toMatchArray([
            'theme' => 'purple',
            'font' => 'naskh',
            'heading_color' => '#123456',
            'student_name_color' => '#234567',
            'content_color' => '#345678',
            'accent_color' => '#456789',
        ]);
});

test('certificate design updates reject unknown catalog values colors and frame injection', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.update']);
    $center = certificateDesignPreviewCenter();
    $designs = app(CertificateDesignSettingsService::class)->designsForCenter($center);
    $designs['surah']['theme'] = 'unknown-theme';
    $designs['surah']['font'] = 'unknown-font';
    $designs['surah']['heading_color'] = 'red';
    $designs['surah']['frame'] = '../../outside.svg';

    $this->actingAs($user, 'web')
        ->put(route('admin.certificate-designs.update'), [
            'center_id' => $center->id,
            'designs' => $designs,
            'gender' => Center::STUDENT_GENDER_FEMALE,
        ])
        ->assertSessionHasErrors([
            'gender',
            'designs.surah',
            'designs.surah.theme',
            'designs.surah.font',
            'designs.surah.heading_color',
        ]);

    expect(SystemSetting::query()
        ->where('key', CertificateDesignSettingsService::SETTING_KEY)
        ->exists())->toBeFalse();
});

test('a v1 gender design remains the fallback while the first center save is promoted to v2', function () {
    $firstCenter = certificateDesignPreviewCenter();
    $secondCenter = certificateDesignPreviewCenter([
        'name' => 'مركز إناث ثانٍ',
    ]);
    $service = app(CertificateDesignSettingsService::class);
    $legacyDesigns = $service->defaults();
    $legacyDesigns[Center::STUDENT_GENDER_FEMALE][Certificate::ACHIEVEMENT_PART] = [
        'theme' => 'purple',
        'font' => 'naskh',
        'heading_color' => '#123456',
        'student_name_color' => '#234567',
        'content_color' => '#345678',
        'accent_color' => '#456789',
    ];
    SystemSetting::query()->create([
        'key' => CertificateDesignSettingsService::SETTING_KEY,
        'value' => [
            'version' => 1,
            'designs' => $legacyDesigns,
        ],
    ]);
    $service->clearCache();

    expect($service->designsForCenter($firstCenter)['part']['theme'])->toBe('purple')
        ->and($service->designsForCenter($secondCenter)['part']['theme'])->toBe('purple');

    $firstDesigns = $service->designsForCenter($firstCenter);
    $firstDesigns['part'] = [
        'theme' => 'navy',
        'font' => 'amiri',
        'heading_color' => '#654321',
        'student_name_color' => '#765432',
        'content_color' => '#876543',
        'accent_color' => '#987654',
    ];
    $service->updateForCenter($firstCenter, $firstDesigns);
    $newCenter = certificateDesignPreviewCenter([
        'name' => 'مركز إناث جديد',
    ]);
    $stored = SystemSetting::query()
        ->where('key', CertificateDesignSettingsService::SETTING_KEY)
        ->sole();

    expect($stored->value['version'])->toBe(2)
        ->and($stored->value['defaults']['female']['part']['theme'])->toBe('purple')
        ->and($stored->value['centers'][$firstCenter->id]['part']['theme'])->toBe('navy')
        ->and($service->designsForCenter($firstCenter)['part']['theme'])->toBe('navy')
        ->and($service->designsForCenter($secondCenter)['part']['theme'])->toBe('purple')
        ->and($service->designsForCenter($newCenter)['part']['theme'])->toBe('purple');
});

test('sequential center saves preserve other centers and isolate certificate types', function () {
    $firstCenter = certificateDesignPreviewCenter([
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $secondCenter = certificateDesignPreviewCenter([
        'name' => 'مركز ذكور ثانٍ',
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $service = app(CertificateDesignSettingsService::class);
    $firstDesigns = $service->designsForCenter($firstCenter);
    $secondDesigns = $service->designsForCenter($secondCenter);
    $firstDesigns['part']['theme'] = 'purple';
    $secondDesigns['part']['theme'] = 'navy';

    $service->updateForCenter($firstCenter, $firstDesigns);
    $service->updateForCenter($secondCenter, $secondDesigns);

    expect($service->designsForCenter($firstCenter)['part']['theme'])->toBe('purple')
        ->and($service->designsForCenter($secondCenter)['part']['theme'])->toBe('navy')
        ->and($service->designsForCenter($firstCenter)['surah']['theme'])->toBe('blue')
        ->and($service->designsForCenter($secondCenter)['three_parts']['theme'])->toBe('blue');
});

test('scoped design users only see preview and update centers they can access', function () {
    foreach (['certificate_designs.view', 'certificate_designs.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    $user->givePermissionTo(['certificate_designs.view', 'certificate_designs.update']);
    $accessibleCenter = certificateDesignPreviewCenter([
        'name' => 'المركز المسموح',
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $hiddenCenter = certificateDesignPreviewCenter([
        'name' => 'المركز المخفي',
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    Student::factory()->create([
        'admin_id' => $user->id,
        'center_id' => $accessibleCenter->id,
    ]);
    $service = app(CertificateDesignSettingsService::class);

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('centers', 1)
            ->where('centers.0.id', $accessibleCenter->id)
            ->has("designs.{$accessibleCenter->id}")
            ->missing("designs.{$hiddenCenter->id}"));

    $this->actingAs($user, 'web')
        ->putJson(route('admin.certificate-designs.update'), [
            'center_id' => $hiddenCenter->id,
            'designs' => $service->designsForCenter($hiddenCenter),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('center_id');

    $this->actingAs($user, 'web')
        ->put(route('admin.certificate-designs.update'), [
            'center_id' => $accessibleCenter->id,
            'designs' => $service->designsForCenter($accessibleCenter),
        ])
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('cached designs are normalized when the theme catalog changes', function () {
    $service = app(CertificateDesignSettingsService::class);
    $designs = $service->defaults();
    $designs['male']['surah']['theme'] = 'purple';
    $service->update($designs);

    expect($service->get()['male']['surah']['theme'])->toBe('purple');

    $themes = config('certificates.themes');
    $catalogWithoutPurple = $themes;
    unset($catalogWithoutPurple['purple']);
    config()->set('certificates.themes', $catalogWithoutPurple);

    try {
        expect($service->resolve('male', Certificate::ACHIEVEMENT_SURAH)['theme'])->toBe('blue');
    } finally {
        config()->set('certificates.themes', $themes);
        $service->clearCache();
    }
});

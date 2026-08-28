<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\System\CertificateDesignSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Permission;

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
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function certificateDesignPdfPreviewPayload(int $planPointId = 1): array
{
    return [
        'gender' => Center::STUDENT_GENDER_FEMALE,
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

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CertificateDesigns', false)
            ->where('canUpdate', true)
            ->where('designs.male.surah.theme', 'blue')
            ->where('designs.male.part.theme', 'blue')
            ->where('designs.male.three_parts.theme', 'blue')
            ->where('designs.female.surah.theme', 'rose')
            ->where('designs.female.part.theme', 'rose')
            ->where('designs.female.three_parts.theme', 'rose')
            ->where('designs.male.surah.font', 'classic')
            ->where('catalog.genders.0.value', Center::STUDENT_GENDER_MALE)
            ->where('catalog.genders.1.value', Center::STUDENT_GENDER_FEMALE)
            ->where('catalog.achievementTypes.0.value', Certificate::ACHIEVEMENT_SURAH)
            ->where('catalog.achievementTypes.1.value', Certificate::ACHIEVEMENT_PART)
            ->where('catalog.achievementTypes.2.value', Certificate::ACHIEVEMENT_THREE_PARTS)
            ->has('catalog.themes', 20)
            ->has('catalog.fonts', 12));
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
    $point = certificateDesignPreviewPoint();

    $response = $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.preview'));

    $response->assertOk()
        ->assertSee('certificate-design-preview:update', false)
        ->assertSee('left-logo.png', false)
        ->assertSee('right-logo.png', false)
        ->assertSee('center-stamp.png', false)
        ->assertSee('center-signature.png', false)
        ->assertSee('project-stamp.png', false)
        ->assertSee('project-signature.png', false)
        ->assertDontSee('certificate--project-only', false)
        ->assertDontSee('certificate__logo--project-solo', false)
        ->assertDontSee('certificate__signing--project-solo', false)
        ->assertViewHas('certificate', function (array $certificate) use ($point): bool {
            $wording = $certificate['preview_samples']['wording'] ?? [];

            return ($certificate['show_center_manager_signature'] ?? false) === true
                && ($certificate['achievement_name'] ?? null) === 'السابع'
                && ($certificate['design']['achievement_type'] ?? null) === Certificate::ACHIEVEMENT_PART
                && ($certificate['preview_catalog']['achievements'][(string) $point->id]['achievement_name'] ?? null) === 'السابع'
                && ($wording[Center::STUDENT_GENDER_MALE]['achievement_intro'] ?? null)
                    === config('certificates.wording.male.achievement_intro')
                && ($wording[Center::STUDENT_GENDER_FEMALE]['achievement_intro'] ?? null)
                    === config('certificates.wording.female.achievement_intro')
                && ($wording[Center::STUDENT_GENDER_FEMALE]['closing_text'] ?? null)
                    === config('certificates.wording.female.closing_text');
        });

    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized, 'web')
        ->get(route('admin.certificate-designs.preview'))
        ->assertForbidden();
});

test('certificate design preview shows an explicit empty state when plans have no certificate achievements', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.preview'))
        ->assertOk()
        ->assertViewHas('certificate', fn (array $certificate): bool => ($certificate['achievement_name'] ?? null) === '—'
            && ($certificate['preview_catalog']['achievements'] ?? null) === []
        )
        ->assertDontSee('الجزء الأول')
        ->assertDontSee('الأجزاء الثلاثة الأولى');
});

test('a read only design user can download a real pdf preview of unsaved nested design values', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $point = certificateDesignPreviewPoint();
    Pdf::fake();

    $this->actingAs($user, 'web')
        ->postJson(
            route('admin.certificate-designs.preview.pdf'),
            certificateDesignPdfPreviewPayload($point->id),
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
            && ($certificate['show_center_manager_signature'] ?? false) === true
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
            && str_starts_with((string) ($certificate['images']['left_logo'] ?? ''), 'data:image/png;base64,')
            && str_starts_with((string) ($certificate['images']['center_stamp'] ?? ''), 'data:image/png;base64,')
            && str_starts_with((string) ($certificate['images']['center_signature'] ?? ''), 'data:image/png;base64,');
    });

    expect(SystemSetting::query()
        ->where('key', CertificateDesignSettingsService::SETTING_KEY)
        ->exists())->toBeFalse();
});

test('certificate design pdf preview strictly validates its whitelisted nested payload', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $point = certificateDesignPreviewPoint(['requires_certificate' => false]);

    $payload = certificateDesignPdfPreviewPayload($point->id);
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

test('certificate design pdf preview rejects a certificate point without achievement data', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
    $point = certificateDesignPreviewPoint([
        'surah_name' => null,
        'part_name' => '   ',
        'three_parts' => null,
    ]);

    $this->actingAs($user, 'web')
        ->postJson(
            route('admin.certificate-designs.preview.pdf'),
            certificateDesignPdfPreviewPayload($point->id),
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('plan_point_id');
});

test('certificate design pdf preview requires the view permission', function () {
    $user = User::factory()->create();
    $point = certificateDesignPreviewPoint();
    Pdf::fake();

    $this->actingAs($user, 'web')
        ->postJson(
            route('admin.certificate-designs.preview.pdf'),
            certificateDesignPdfPreviewPayload($point->id),
        )
        ->assertForbidden();
});

test('a read only user can view certificate designs but cannot update them', function () {
    $user = certificateDesignSettingsUser(['certificate_designs.view']);
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
    $service = app(CertificateDesignSettingsService::class);
    $designs = $service->defaults();
    $designs['male']['part'] = [
        'theme' => 'purple',
        'font' => 'naskh',
        'heading_color' => '#123456',
        'student_name_color' => '#234567',
        'content_color' => '#345678',
        'accent_color' => '#456789',
    ];

    $this->actingAs($user, 'web')
        ->from(route('admin.certificate-designs.index'))
        ->put(route('admin.certificate-designs.update'), ['designs' => $designs])
        ->assertRedirect(route('admin.certificate-designs.index'))
        ->assertSessionHas('success');

    $stored = SystemSetting::query()
        ->where('key', CertificateDesignSettingsService::SETTING_KEY)
        ->sole();

    expect($stored->value['version'])->toBe(1)
        ->and($stored->value['designs']['male']['part']['theme'])->toBe('purple')
        ->and($service->get()['male']['part'])->toMatchArray([
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
    $designs = app(CertificateDesignSettingsService::class)->defaults();
    $designs['female']['surah']['theme'] = 'unknown-theme';
    $designs['female']['surah']['font'] = 'unknown-font';
    $designs['female']['surah']['heading_color'] = 'red';
    $designs['female']['surah']['frame'] = '../../outside.svg';

    $this->actingAs($user, 'web')
        ->put(route('admin.certificate-designs.update'), ['designs' => $designs])
        ->assertSessionHasErrors([
            'designs.female.surah',
            'designs.female.surah.theme',
            'designs.female.surah.font',
            'designs.female.surah.heading_color',
        ]);

    expect(SystemSetting::query()
        ->where('key', CertificateDesignSettingsService::SETTING_KEY)
        ->exists())->toBeFalse();
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

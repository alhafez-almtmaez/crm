<?php

use App\Exports\PlanPointsExport;
use App\Imports\PlanPointsImport;
use App\Models\Center;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\StudentCertificateService;
use App\Services\System\CertificateAchievementService;
use App\Services\System\CertificateContentTemplateService;
use App\Services\System\CertificateDesignSettingsService;
use App\Services\System\CertificateWordingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/**
 * @return array{user: User, student: Student, center: Center, plan: Plan, bookPoint: PlanPoint, partPoint: PlanPoint}
 */
function sunnahCertificateFixture(): array
{
    $user = User::factory()->create();
    $center = Center::factory()->create([
        'name' => 'مركز السُنّة',
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => 'مجموعة السُنّة',
    ]);
    $plan = Plan::factory()->sunnah()->create(['name' => 'حفظ السُنّة']);
    $bookPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'name' => 'إتمام كتاب الطهارة',
        'requires_certificate' => true,
        'book_name' => 'كتاب الطهارة',
    ]);
    $partPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 20,
        'name' => 'إتمام الجزء الأول',
        'requires_certificate' => true,
        'part_name' => 'الجزء الأول',
    ]);
    $student = Student::factory()->active()->create([
        'full_name' => 'طالب السُنّة',
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $partPoint->id,
        'admin_id' => $user->id,
    ]);

    return compact('user', 'student', 'center', 'plan', 'bookPoint', 'partPoint');
}

test('sunnah plans issue independent book and part certificate types', function () {
    $fixture = sunnahCertificateFixture();
    Auth::guard('web')->login($fixture['user']);

    try {
        $bookCertificate = app(StudentCertificateService::class)->issue(
            $fixture['student'],
            $fixture['bookPoint']->id,
        );
        $partCertificate = app(StudentCertificateService::class)->issue(
            $fixture['student'],
            $fixture['partPoint']->id,
        );
    } finally {
        Auth::guard('web')->logout();
    }

    expect($bookCertificate->achievement_type)->toBe(Certificate::ACHIEVEMENT_SUNNAH_BOOK)
        ->and($bookCertificate->book_name)->toBe('كتاب الطهارة')
        ->and($bookCertificate->surah_name)->toBeNull()
        ->and($bookCertificate->wording_snapshot['achievement_type'])
        ->toBe(Certificate::ACHIEVEMENT_SUNNAH_BOOK)
        ->and($bookCertificate->wording_snapshot['template_key'])->toBe('sunnah-general')
        ->and($bookCertificate->wording_snapshot['rendered_sections']['achievement_line'])
        ->toContain((string) config('certificates.achievement_labels.sunnah_book'))
        ->toContain('الطهارة')
        ->and($bookCertificate->wording_snapshot['rendered_sections']['closing'])
        ->toContain('العَمَلَ بِسُنَّةِ نَبِيِّهِ ﷺ')
        ->not->toContain('إِتْمَامِ حِفْظِ كِتَابِهِ الكَرِيمِ')
        ->and($partCertificate->achievement_type)->toBe(Certificate::ACHIEVEMENT_SUNNAH_PART)
        ->and($partCertificate->part_name)->toBe('الجزء الأول')
        ->and($partCertificate->wording_snapshot['rendered_sections']['achievement_line'])
        ->toContain((string) config('certificates.achievement_labels.sunnah_part'))
        ->toContain('الأول');

    $bookListItem = app(StudentCertificateService::class)->listItem(
        $fixture['student'],
        $bookCertificate,
    );
    expect($bookListItem['achievement_type_label'])->toBe(__('certificates.types.sunnah_book'))
        ->and($bookListItem['achievement_name'])->toBe('كتاب الطهارة');

    $this->get(route('certificates.verify', ['public_id' => $bookCertificate->public_id]))
        ->assertOk()
        ->assertSee('كتاب من السُنّة')
        ->assertSee('الطهارة');

    $this->get(route('certificates.verify', ['public_id' => $partCertificate->public_id]))
        ->assertOk()
        ->assertSee('جزء من السُنّة')
        ->assertSee('الأول');
});

test('achievement resolution obeys the plan category and keeps quran and sunnah parts distinct', function () {
    $achievements = app(CertificateAchievementService::class);
    $quranPlan = Plan::factory()->quran()->create();
    $sunnahPlan = Plan::factory()->sunnah()->create();

    $quranPart = PlanPoint::factory()->create([
        'plan_id' => $quranPlan->id,
        'part_name' => 'الجزء الخامس',
    ]);
    $sunnahPart = PlanPoint::factory()->create([
        'plan_id' => $sunnahPlan->id,
        'part_name' => 'الجزء الخامس',
    ]);
    $invalidQuranBook = PlanPoint::factory()->create([
        'plan_id' => $quranPlan->id,
        'book_name' => 'كتاب الطهارة',
    ]);
    $invalidSunnahSurah = PlanPoint::factory()->create([
        'plan_id' => $sunnahPlan->id,
        'surah_name' => 'البقرة',
    ]);

    expect($achievements->resolve($quranPart))->toBe([
        'type' => Certificate::ACHIEVEMENT_PART,
        'name' => 'الجزء الخامس',
    ])->and($achievements->resolve($sunnahPart))->toBe([
        'type' => Certificate::ACHIEVEMENT_SUNNAH_PART,
        'name' => 'الجزء الخامس',
    ])->and($achievements->resolve($invalidQuranBook))->toBeNull()
        ->and($achievements->resolve($invalidSunnahSurah))->toBeNull();
});

test('certificate design and content catalogs expose all three quran and two sunnah types', function () {
    $fixture = sunnahCertificateFixture();
    $types = collect(app(CertificateDesignSettingsService::class)->catalog()['achievementTypes']);
    $designs = app(CertificateDesignSettingsService::class)->designsForCenter($fixture['center']);
    $effectiveTemplates = app(CertificateContentTemplateService::class)
        ->effectiveForCenters([$fixture['center']]);

    expect($types->pluck('value')->all())->toBe(Certificate::ACHIEVEMENT_TYPES)
        ->and($types->where('category', Plan::CATEGORY_QURAN))->toHaveCount(3)
        ->and($types->where('category', Plan::CATEGORY_SUNNAH))->toHaveCount(2)
        ->and(array_keys($designs))->toBe(Certificate::ACHIEVEMENT_TYPES)
        ->and(array_keys($effectiveTemplates[$fixture['center']->id]))
        ->toBe(Certificate::ACHIEVEMENT_TYPES);

    $preview = app(CertificateAchievementService::class)->previewAchievements();
    $legacyWording = app(CertificateWordingService::class)->resolve(
        Center::STUDENT_GENDER_MALE,
        Certificate::ACHIEVEMENT_SUNNAH_BOOK,
    );
    expect($preview[Certificate::ACHIEVEMENT_SUNNAH_BOOK][0]['id'])
        ->toBe($fixture['bookPoint']->id)
        ->and($preview[Certificate::ACHIEVEMENT_SUNNAH_PART][0]['id'])
        ->toBe($fixture['partPoint']->id)
        ->and($legacyWording['closing_text'])->toContain('العَمَلَ بِسُنَّةِ نَبِيِّهِ ﷺ')
        ->not->toContain('إِتْمَامِ حِفْظِ كِتَابِهِ الكَرِيمِ');
});

test('sunnah point import supports the new book column and legacy book values from the surah column', function () {
    $plan = Plan::factory()->sunnah()->create();
    $import = new PlanPointsImport($plan);
    $import->collection(collect([
        collect([
            'خطة التسميع',
            'النقاط',
            'أخذ الشهادة',
            'اسم السورة',
            'اسم الجزء',
            'رقم الثلاث أجزاء',
            'اسم الكتاب (السُنّة)',
        ]),
        collect(['كتاب الصلاة', 5, 1, null, null, null, 'كتاب الصلاة']),
        collect(['كتاب الطهارة قديم', 5, 1, 'كتاب الطهارة', null, null, null]),
        collect(['قيمة غير صالحة', 5, 1, null, null, 'ثلاثة أجزاء', null]),
    ]));

    expect($import->result()['imported'])->toBe(2)
        ->and($import->result()['skipped'])->toBe(1)
        ->and(PlanPoint::query()->where('plan_id', $plan->id)->pluck('book_name')->all())
        ->toBe(['كتاب الصلاة', 'كتاب الطهارة']);

    $points = PlanPoint::query()
        ->where('plan_id', $plan->id)
        ->orderBy('sort_order')
        ->get();
    $export = new PlanPointsExport($points);

    expect($export->headings())->toContain('اسم الكتاب (السُنّة)')
        ->and($export->collection()->first()[10])->toBe('كتاب الصلاة');
});

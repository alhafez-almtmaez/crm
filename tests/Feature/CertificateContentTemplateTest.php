<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\CertificateContentTemplate;
use App\Models\CertificateContentTemplateAssignment;
use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\StudentCertificateService;
use App\Services\System\CertificateContentTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** @return array<string, string> */
function certificateContentTestSections(string $marker = 'اختبار'): array
{
    return [
        'title' => "شهادة {$marker}",
        'quote_first' => "الاقتباس الأول {$marker}",
        'quote_second' => "الاقتباس الثاني {$marker}",
        'intro' => "مقدمة {$marker} من {{ center_name }}",
        'student_line' => '﴿ {{ student_name }} ﴾',
        'achievement_line' => "إنجاز {$marker}: {{ achievement_label }} ﴿ {{ achievement_name }} ﴾",
        'closing' => "خاتمة {$marker}",
    ];
}

/** @param list<string> $permissions */
function certificateContentTestUser(array $permissions, bool $admin = true): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create();
    if ($admin) {
        $user->assignRole(Role::findOrCreate('admin', 'web'));
    }
    $user->givePermissionTo($permissions);

    return $user;
}

/** @return array{user: User, student: Student, center: Center, point: PlanPoint} */
function certificateContentTestIssuanceFixture(): array
{
    $user = certificateContentTestUser(['students.view', 'students.update'], false);
    $center = Center::factory()->create([
        'name' => 'اسم المركز الداخلي',
        'certificate_name' => '<script>alert(2)</script>',
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => 'مجموعة اختبار القوالب',
    ]);
    $plan = Plan::factory()->create(['name' => 'خطة اختبار القوالب']);
    $point = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'name' => 'إتمام الجزء الخامس',
        'requires_certificate' => true,
        'surah_name' => null,
        'part_name' => 'الجزء الخامس',
        'three_parts' => null,
    ]);
    $student = Student::factory()->active()->create([
        'full_name' => '<img src=x onerror=alert(1)>',
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $point->id,
        'admin_id' => $user->id,
    ]);
    recordStudentPlanCompletion($student, $point);

    return compact('user', 'student', 'center', 'point');
}

/** @return array<string, mixed> */
function certificateContentTestPdfPayload(Center $center, PlanPoint $point): array
{
    return [
        'center_id' => $center->id,
        'plan_point_id' => $point->id,
        'design' => [
            'theme' => 'purple',
            'font' => 'naskh',
            'heading_color' => '#123456',
            'student_name_color' => '#234567',
            'content_color' => '#345678',
            'accent_color' => '#456789',
        ],
    ];
}

test('content template migrations seed quran and sunnah templates with gender assignments', function () {
    $general = CertificateContentTemplate::query()->where('key', 'general')->sole();
    $female = CertificateContentTemplate::query()->where('key', 'female')->sole();
    $sunnahGeneral = CertificateContentTemplate::query()->where('key', 'sunnah-general')->sole();
    $sunnahFemale = CertificateContentTemplate::query()->where('key', 'sunnah-female')->sole();

    expect($general->is_system)->toBeTrue()
        ->and($general->is_active)->toBeTrue()
        ->and($general->sections)->toBe([
            'title' => 'شَهادَةُ تَمَيُّزٍ وَتَقْدِيرٍ',
            'quote_first' => 'لَوْلَا المَشَقَّةُ سَادَ النَّاسُ كُلُّهُمُ',
            'quote_second' => 'الجُودُ يُفْقِرُ وَالإِقْدَامُ قَتَّالُ',
            'intro' => 'تَتَقَدَّمُ إِدَارَةُ {{ center_name }} بِالتَّهْنِئَةِ الحَارَّةِ لِطَالِبِ العِلْمِ المُتَمَيِّزِ:',
            'student_line' => '﴿ {{ student_name }} ﴾',
            'achievement_line' => 'وَذَلِكَ لِإِنْجَازِهِ {{ achievement_label }} ﴿ {{ achievement_name }} ﴾ بِإِتْقَانٍ عَالٍ بِفَضْلِ اللهِ تَعَالَى',
            'closing' => 'نَسْأَلُ اللهَ لَهُ التَّوْفِيقَ وَالثَّبَاتَ، وَأَنْ يَمُنَّ عَلَيْهِ بِإِتْمَامِ حِفْظِ كِتَابِهِ الكَرِيمِ وَالعَمَلِ بِهِ.',
        ])
        ->and($female->is_system)->toBeTrue()
        ->and($female->is_active)->toBeTrue()
        ->and($female->sections['intro'])
        ->toBe('تَتَقَدَّمُ إِدَارَةُ {{ center_name }} بِالتَّهْنِئَةِ الحَارَّةِ لِطَالِبَةِ العِلْمِ المُتَمَيِّزَةِ:')
        ->and($female->sections['achievement_line'])
        ->toBe('وَذَلِكَ لِإِنْجَازِهَا {{ achievement_label }} ﴿ {{ achievement_name }} ﴾ بِإِتْقَانٍ عَالٍ بِفَضْلِ اللهِ تَعَالَى')
        ->and($female->sections['closing'])
        ->toBe('نَسْأَلُ اللهَ لَهَا التَّوْفِيقَ وَالثَّبَاتَ، وَأَنْ يَمُنَّ عَلَيْهَا بِإِتْمَامِ حِفْظِ كِتَابِهِ الكَرِيمِ وَالعَمَلِ بِهِ.')
        ->and($sunnahGeneral->is_system)->toBeTrue()
        ->and($sunnahGeneral->sections['closing'])->toContain('العَمَلَ بِسُنَّةِ نَبِيِّهِ ﷺ')
        ->and($sunnahGeneral->sections['closing'])->not->toContain('إِتْمَامِ حِفْظِ كِتَابِهِ الكَرِيمِ')
        ->and($sunnahFemale->is_system)->toBeTrue()
        ->and($sunnahFemale->sections['intro'])->toContain('لِطَالِبَةِ العِلْمِ المُتَمَيِّزَةِ')
        ->and($sunnahFemale->sections['closing'])->toContain('العَمَلَ بِسُنَّةِ نَبِيِّهِ ﷺ');

    expect(CertificateContentTemplateAssignment::query()->count())->toBe(6)
        ->and(CertificateContentTemplateAssignment::query()
            ->where('scope_key', 'global:*|type:*')
            ->where('template_id', $general->id)
            ->exists())->toBeTrue()
        ->and(CertificateContentTemplateAssignment::query()
            ->where('scope_key', 'gender:female|type:*')
            ->where('template_id', $female->id)
            ->exists())->toBeTrue()
        ->and(CertificateContentTemplateAssignment::query()
            ->where('scope_key', 'gender:male|type:sunnah_book')
            ->where('template_id', $sunnahGeneral->id)
            ->exists())->toBeTrue()
        ->and(CertificateContentTemplateAssignment::query()
            ->where('scope_key', 'gender:female|type:sunnah_part')
            ->where('template_id', $sunnahFemale->id)
            ->exists())->toBeTrue();
});

test('resolver applies center type center all gender type gender all global type and global all priority', function () {
    $service = app(CertificateContentTemplateService::class);
    $globalType = $service->create([
        'name' => 'عام للجزء',
        'sections' => certificateContentTestSections('عام للجزء'),
    ], null);
    $genderType = $service->create([
        'name' => 'إناث للجزء',
        'sections' => certificateContentTestSections('إناث للجزء'),
    ], null);
    $centerAll = $service->create([
        'name' => 'المركز لكل الأنواع',
        'sections' => certificateContentTestSections('المركز لكل الأنواع'),
    ], null);
    $centerType = $service->create([
        'name' => 'المركز للجزء',
        'sections' => certificateContentTestSections('المركز للجزء'),
    ], null);
    $targetFemale = Center::factory()->create(['student_gender' => Center::STUDENT_GENDER_FEMALE]);
    $otherFemale = Center::factory()->create(['student_gender' => Center::STUDENT_GENDER_FEMALE]);
    $otherMale = Center::factory()->create(['student_gender' => Center::STUDENT_GENDER_MALE]);

    $service->upsertAssignment([
        'template_id' => $globalType->id,
        'scope_type' => CertificateContentTemplateAssignment::SCOPE_GLOBAL,
        'achievement_type' => Certificate::ACHIEVEMENT_PART,
    ]);
    $service->upsertAssignment([
        'template_id' => $genderType->id,
        'scope_type' => CertificateContentTemplateAssignment::SCOPE_GENDER,
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
        'achievement_type' => Certificate::ACHIEVEMENT_PART,
    ]);
    $service->upsertAssignment([
        'template_id' => $centerAll->id,
        'scope_type' => CertificateContentTemplateAssignment::SCOPE_CENTER,
        'center_id' => $targetFemale->id,
        'achievement_type' => 'all',
    ]);
    $service->upsertAssignment([
        'template_id' => $centerType->id,
        'scope_type' => CertificateContentTemplateAssignment::SCOPE_CENTER,
        'center_id' => $targetFemale->id,
        'achievement_type' => Certificate::ACHIEVEMENT_PART,
    ]);

    $resolved = [
        'center_type' => $service->resolve($targetFemale, Certificate::ACHIEVEMENT_PART),
        'center_all' => $service->resolve($targetFemale, Certificate::ACHIEVEMENT_SURAH),
        'gender_type' => $service->resolve($otherFemale, Certificate::ACHIEVEMENT_PART),
        'gender_all' => $service->resolve($otherFemale, Certificate::ACHIEVEMENT_SURAH),
        'global_type' => $service->resolve($otherMale, Certificate::ACHIEVEMENT_PART),
        'global_all' => $service->resolve($otherMale, Certificate::ACHIEVEMENT_SURAH),
    ];

    expect($resolved['center_type']['source'])->toBe('center_type')
        ->and($resolved['center_type']['template']->is($centerType))->toBeTrue()
        ->and($resolved['center_all']['source'])->toBe('center_all')
        ->and($resolved['center_all']['template']->is($centerAll))->toBeTrue()
        ->and($resolved['gender_type']['source'])->toBe('gender_type')
        ->and($resolved['gender_type']['template']->is($genderType))->toBeTrue()
        ->and($resolved['gender_all']['source'])->toBe('gender_all')
        ->and($resolved['gender_all']['template']->key)->toBe('female')
        ->and($resolved['global_type']['source'])->toBe('global_type')
        ->and($resolved['global_type']['template']->is($globalType))->toBeTrue()
        ->and($resolved['global_all']['source'])->toBe('global_all')
        ->and($resolved['global_all']['template']->key)->toBe('general');

    $fallback = $service->resolve(
        ['id' => $otherFemale->id],
        Certificate::ACHIEVEMENT_PART,
        Center::STUDENT_GENDER_FEMALE,
    );
    $effective = $service->effectiveForCenters([$targetFemale, $otherFemale, $otherMale]);

    expect($fallback['source'])->toBe('gender_type')
        ->and($fallback['template']->is($genderType))->toBeTrue()
        ->and($effective[$targetFemale->id][Certificate::ACHIEVEMENT_PART]['source'])->toBe('center_type')
        ->and($effective[$otherFemale->id][Certificate::ACHIEVEMENT_SURAH]['source'])->toBe('gender_all')
        ->and($effective[$otherMale->id][Certificate::ACHIEVEMENT_PART]['source'])->toBe('global_type');

    $inactive = $service->create([
        'name' => 'غير فعال',
        'sections' => certificateContentTestSections('غير فعال'),
        'is_active' => false,
    ], null);

    expect(fn () => $service->upsertAssignment([
        'template_id' => $inactive->id,
        'scope_type' => CertificateContentTemplateAssignment::SCOPE_GLOBAL,
        'achievement_type' => 'all',
    ]))->toThrow(ValidationException::class)
        ->and(fn () => $service->upsertAssignment([
            'template_id' => $globalType->id,
            'scope_type' => 'invalid',
            'achievement_type' => 'all',
        ]))->toThrow(ValidationException::class)
        ->and(fn () => $service->upsertAssignment([
            'template_id' => $globalType->id,
            'scope_type' => CertificateContentTemplateAssignment::SCOPE_GLOBAL,
            'achievement_type' => 'invalid',
        ]))->toThrow(ValidationException::class);
});

test('admin receives the template props and can create update assign unassign and delete custom templates', function () {
    $admin = certificateContentTestUser([
        'certificate_designs.view',
        'certificate_designs.update',
    ]);
    $center = Center::factory()->create();

    $this->actingAs($admin, 'web')
        ->get(route('admin.certificate-designs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManageContentTemplates', true)
            ->where('canManageGlobalContentAssignments', true)
            ->has('contentTemplates', 4)
            ->has('contentTemplates.0.sections', 7)
            ->has('contentTemplateAssignments', 6)
            ->has('templateVariables', 9)
            ->where('templateVariables', function ($variables): bool {
                $keys = collect($variables)->pluck('key');

                return $keys->contains('student_name')
                    && $keys->contains('center_name')
                    && $keys->contains('achievement_name');
            })
            ->has("effectiveContentTemplates.{$center->id}.surah")
            ->has("effectiveContentTemplates.{$center->id}.part")
            ->has("effectiveContentTemplates.{$center->id}.three_parts")
            ->has("effectiveContentTemplates.{$center->id}.sunnah_book")
            ->has("effectiveContentTemplates.{$center->id}.sunnah_part"));

    $this->actingAs($admin, 'web')
        ->postJson(route('admin.certificate-content-templates.store'), [
            'name' => 'قالب CRUD',
            'sections' => certificateContentTestSections('CRUD'),
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('template.name', 'قالب CRUD')
        ->assertJsonPath('template.is_system', false)
        ->assertJsonPath('template.is_active', true);

    $template = CertificateContentTemplate::query()->where('name', 'قالب CRUD')->sole();
    expect($template->key)->toStartWith('custom-')
        ->and($template->created_by)->toBe($admin->id)
        ->and(Activity::query()
            ->where('log_name', 'certificate_content_templates')
            ->where('subject_type', CertificateContentTemplate::class)
            ->where('subject_id', $template->id)
            ->where('event', 'created')
            ->where('causer_id', $admin->id)
            ->exists())->toBeTrue();

    $updatedSections = certificateContentTestSections('معدل');
    $this->actingAs($admin, 'web')
        ->putJson(route('admin.certificate-content-templates.update', $template), [
            'name' => 'قالب CRUD معدل',
            'sections' => $updatedSections,
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('template.name', 'قالب CRUD معدل')
        ->assertJsonPath('template.sections.closing', 'خاتمة معدل');

    $this->actingAs($admin, 'web')
        ->putJson(route('admin.certificate-content-template-assignments.update'), [
            'template_id' => $template->id,
            'scope_type' => CertificateContentTemplateAssignment::SCOPE_CENTER,
            'center_id' => $center->id,
            'achievement_type' => 'all',
        ])
        ->assertOk()
        ->assertJsonPath('assignment.template_id', $template->id)
        ->assertJsonPath('assignment.scope_type', 'center')
        ->assertJsonPath('assignment.center_id', $center->id)
        ->assertJsonPath('assignment.achievement_type', 'all');

    $assignment = CertificateContentTemplateAssignment::query()
        ->where('scope_key', "center:{$center->id}|type:*")
        ->sole();

    $this->actingAs($admin, 'web')
        ->deleteJson(route('admin.certificate-content-templates.destroy', $template))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('template');

    $this->actingAs($admin, 'web')
        ->deleteJson(route('admin.certificate-content-template-assignments.destroy', $assignment))
        ->assertOk();

    $this->actingAs($admin, 'web')
        ->deleteJson(route('admin.certificate-content-templates.destroy', $template))
        ->assertOk();

    expect(CertificateContentTemplate::query()->whereKey($template->id)->exists())->toBeFalse()
        ->and(CertificateContentTemplate::withTrashed()->whereKey($template->id)->exists())->toBeTrue();

    $systemTemplate = CertificateContentTemplate::query()->where('key', 'general')->sole();
    $this->actingAs($admin, 'web')
        ->deleteJson(route('admin.certificate-content-templates.destroy', $systemTemplate))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('template');
});

test('template requests reject html blade malformed unknown and misplaced required variables', function () {
    $admin = certificateContentTestUser(['certificate_designs.update']);
    $sections = certificateContentTestSections('غير صالح');
    $sections['title'] = '<script>alert(1)</script>';
    $sections['quote_first'] = 'متغير غير مكتمل {{ student_name }';
    $sections['intro'] = 'مقدمة بلا اسم المركز';
    $sections['student_line'] = '{{ unknown_student }}';
    $sections['achievement_line'] = 'إنجاز {{ achievement_label }} فقط';
    $sections['closing'] = '@php echo "unsafe"; @endphp';

    $this->actingAs($admin, 'web')
        ->postJson(route('admin.certificate-content-templates.store'), [
            'key' => 'client-controlled-key',
            'name' => 'قالب غير صالح',
            'sections' => $sections,
            'is_active' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'key',
            'sections.title',
            'sections.quote_first',
            'sections.intro',
            'sections.student_line',
            'sections.achievement_line',
            'sections.closing',
        ]);

    $service = app(CertificateContentTemplateService::class);
    expect(fn () => $service->create([
        'name' => 'تجاوز مباشر للخدمة',
        'sections' => $sections,
    ], $admin->id))->toThrow(ValidationException::class);

    $valid = certificateContentTestSections('snapshot');
    $snapshot = $service->draftSnapshot($valid, [
        'student_name' => '<img src=x onerror=alert(1)>',
        'center_name' => '<script>alert(2)</script>',
        'achievement_label' => 'الجزء',
        'achievement_name' => '<b>الخامس</b>',
    ], Certificate::ACHIEVEMENT_PART, Center::STUDENT_GENDER_MALE);
    $corrupt = $snapshot;
    unset($corrupt['rendered_segments']['closing']);
    $emptyRenderedSections = $valid;
    $emptyRenderedSections['title'] = '{{ plan_name }}';
    $emptyRenderedSnapshot = $service->draftSnapshot(
        $emptyRenderedSections,
        [
            'student_name' => 'طالب الاختبار',
            'center_name' => 'مركز الاختبار',
            'achievement_label' => 'الجزء',
            'achievement_name' => 'الخامس',
        ],
        Certificate::ACHIEVEMENT_PART,
        Center::STUDENT_GENDER_MALE,
    );

    expect($snapshot['schema_version'])->toBe(3)
        ->and($snapshot['rendered_sections']['student_line'])->toContain('<img src=x onerror=alert(1)>')
        ->and($snapshot['rendered_segments']['student_line'])->toContain([
            'type' => 'variable',
            'key' => 'student_name',
            'text' => '<img src=x onerror=alert(1)>',
        ])
        ->and($service->snapshot($snapshot))->not->toBeNull()
        ->and($emptyRenderedSnapshot['rendered_sections']['title'])->toBe('')
        ->and($service->snapshot($emptyRenderedSnapshot))->not->toBeNull()
        ->and($service->snapshot($corrupt))->toBeNull()
        ->and($service->snapshot([...$snapshot, 'schema_version' => 4]))->toBeNull();
});

test('identity and achievement variables are restricted to their semantic sections', function () {
    $admin = certificateContentTestUser(['certificate_designs.update']);
    $misplaced = certificateContentTestSections('مواضع خاطئة');
    $misplaced['title'] .= ' {{ student_name }}';
    $misplaced['quote_first'] .= ' {{ center_name }}';
    $misplaced['quote_second'] .= ' {{ achievement_label }}';
    $misplaced['closing'] .= ' {{ achievement_name }}';

    $response = $this->actingAs($admin, 'web')
        ->postJson(route('admin.certificate-content-templates.store'), [
            'name' => 'قالب بمتغيرات في أقسام خاطئة',
            'sections' => $misplaced,
            'is_active' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'sections.title',
            'sections.quote_first',
            'sections.quote_second',
            'sections.closing',
        ]);

    $errors = $response->json('errors');

    expect($errors['sections.title'][0])
        ->toContain('{{ student_name }}')
        ->toContain('سطر اسم الطالب')
        ->and($errors['sections.quote_first'][0])
        ->toContain('{{ center_name }}')
        ->toContain('مقدمة التهنئة')
        ->and($errors['sections.quote_second'][0])
        ->toContain('{{ achievement_label }}')
        ->toContain('سطر الإنجاز')
        ->and($errors['sections.closing'][0])
        ->toContain('{{ achievement_name }}')
        ->toContain('سطر الإنجاز');

    $flexible = certificateContentTestSections('متغيرات مرنة');
    $flexible['title'] .= ' {{ certificate_number }}';
    $flexible['quote_first'] .= ' {{ plan_name }}';
    $flexible['quote_second'] .= ' {{ plan_point_name }}';
    $flexible['closing'] .= ' {{ hijri_date }} {{ gregorian_date }}';

    $this->actingAs($admin, 'web')
        ->postJson(route('admin.certificate-content-templates.store'), [
            'name' => 'قالب بمتغيرات مرنة',
            'sections' => $flexible,
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('template.sections', $flexible);
});

test('scoped supervisors may only assign an existing active template to an accessible center', function () {
    $supervisor = certificateContentTestUser([
        'certificate_designs.view',
        'certificate_designs.update',
    ], false);
    $accessibleCenter = Center::factory()->create(['name' => 'المركز المسموح']);
    $hiddenCenter = Center::factory()->create(['name' => 'المركز المخفي']);
    Student::factory()->create([
        'admin_id' => $supervisor->id,
        'center_id' => $accessibleCenter->id,
    ]);
    $service = app(CertificateContentTemplateService::class);
    $inactive = $service->create([
        'name' => 'قالب غير فعال لا يظهر',
        'sections' => certificateContentTestSections('غير فعال'),
        'is_active' => false,
    ], null);
    $general = CertificateContentTemplate::query()->where('key', 'general')->sole();
    $hiddenAssignment = $service->upsertAssignment([
        'template_id' => $general->id,
        'scope_type' => CertificateContentTemplateAssignment::SCOPE_CENTER,
        'center_id' => $hiddenCenter->id,
        'achievement_type' => 'all',
    ]);

    $this->actingAs($supervisor, 'web')
        ->get(route('admin.certificate-designs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canManageContentTemplates', false)
            ->where('canManageGlobalContentAssignments', false)
            ->has('centers', 1)
            ->where('centers.0.id', $accessibleCenter->id)
            ->where('contentTemplates', fn ($templates): bool => collect($templates)
                ->pluck('id')
                ->doesntContain($inactive->id))
            ->where('contentTemplates', fn ($templates): bool => (int) collect($templates)
                ->firstWhere('id', $general->id)['assignments_count'] === 1)
            ->where('contentTemplateAssignments', fn ($assignments): bool => collect($assignments)
                ->pluck('id')
                ->doesntContain($hiddenAssignment->id)));

    $this->actingAs($supervisor, 'web')
        ->postJson(route('admin.certificate-content-templates.store'), [
            'name' => 'غير مسموح',
            'sections' => certificateContentTestSections('غير مسموح'),
        ])
        ->assertForbidden();

    foreach ([
        [
            'scope_type' => CertificateContentTemplateAssignment::SCOPE_GLOBAL,
            'achievement_type' => 'all',
        ],
        [
            'scope_type' => CertificateContentTemplateAssignment::SCOPE_GENDER,
            'student_gender' => Center::STUDENT_GENDER_MALE,
            'achievement_type' => 'all',
        ],
    ] as $forbiddenScope) {
        $this->actingAs($supervisor, 'web')
            ->putJson(route('admin.certificate-content-template-assignments.update'), [
                'template_id' => $general->id,
                ...$forbiddenScope,
            ])
            ->assertForbidden();
    }

    $this->actingAs($supervisor, 'web')
        ->putJson(route('admin.certificate-content-template-assignments.update'), [
            'template_id' => $general->id,
            'scope_type' => CertificateContentTemplateAssignment::SCOPE_CENTER,
            'center_id' => $hiddenCenter->id,
            'achievement_type' => Certificate::ACHIEVEMENT_PART,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('center_id');

    $this->actingAs($supervisor, 'web')
        ->putJson(route('admin.certificate-content-template-assignments.update'), [
            'template_id' => $general->id,
            'scope_type' => CertificateContentTemplateAssignment::SCOPE_CENTER,
            'center_id' => $accessibleCenter->id,
            'achievement_type' => Certificate::ACHIEVEMENT_PART,
        ])
        ->assertOk();

    $accessibleAssignment = CertificateContentTemplateAssignment::query()
        ->where('scope_key', "center:{$accessibleCenter->id}|type:part")
        ->sole();
    $hiddenAssignment = $service->upsertAssignment([
        'template_id' => $general->id,
        'scope_type' => CertificateContentTemplateAssignment::SCOPE_CENTER,
        'center_id' => $hiddenCenter->id,
        'achievement_type' => Certificate::ACHIEVEMENT_PART,
    ]);
    $globalAssignment = CertificateContentTemplateAssignment::query()
        ->where('scope_key', 'global:*|type:*')
        ->sole();

    $this->actingAs($supervisor, 'web')
        ->deleteJson(route('admin.certificate-content-template-assignments.destroy', $hiddenAssignment))
        ->assertNotFound();
    $this->actingAs($supervisor, 'web')
        ->deleteJson(route('admin.certificate-content-template-assignments.destroy', $globalAssignment))
        ->assertForbidden();
    $this->actingAs($supervisor, 'web')
        ->deleteJson(route('admin.certificate-content-template-assignments.destroy', $accessibleAssignment))
        ->assertOk();

    $readOnly = certificateContentTestUser(['certificate_designs.view'], false);
    $this->actingAs($readOnly, 'web')
        ->putJson(route('admin.certificate-content-template-assignments.update'), [
            'template_id' => $general->id,
            'scope_type' => CertificateContentTemplateAssignment::SCOPE_CENTER,
            'center_id' => $accessibleCenter->id,
            'achievement_type' => 'all',
        ])
        ->assertForbidden();
});

test('pdf preview renders unsaved plain text sections with the issuance tokenizer and rejects unsafe drafts', function () {
    $user = certificateContentTestUser(['certificate_designs.view']);
    $center = Center::factory()->create([
        'name' => 'مركز PDF',
        'certificate_name' => 'الاسم الرسمي في PDF',
        'student_gender' => Center::STUDENT_GENDER_FEMALE,
    ]);
    $plan = Plan::factory()->create(['name' => 'خطة PDF']);
    $point = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'name' => 'نقطة سورة مريم',
        'requires_certificate' => true,
        'surah_name' => 'مريم',
        'part_name' => null,
        'three_parts' => null,
    ]);
    $draft = certificateContentTestSections('PDF غير محفوظ');
    $payload = [
        ...certificateContentTestPdfPayload($center, $point),
        'content_template_sections' => $draft,
    ];
    Pdf::fake();

    $this->actingAs($user, 'web')
        ->postJson(route('admin.certificate-designs.preview.pdf'), $payload)
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($draft): bool {
        $content = $pdf->viewData['certificate']['content_template'] ?? [];

        return ($content['schema_version'] ?? null) === 3
            && ($content['assignment_source'] ?? null) === 'draft'
            && ($content['source_sections'] ?? null) === $draft
            && str_contains(
                (string) data_get($content, 'rendered_sections.intro'),
                'الاسم الرسمي في PDF',
            )
            && collect(data_get($content, 'rendered_segments.student_line', []))
                ->contains(fn (array $segment): bool => ($segment['key'] ?? null) === 'student_name');
    });

    $longDraft = [
        'title' => str_repeat('عنوان ', 10),
        'quote_first' => str_repeat('بيت أول ', 12),
        'quote_second' => str_repeat('بيت ثانٍ ', 12),
        'intro' => str_repeat('مقدمة طويلة ', 24).'{{ center_name }}',
        'student_line' => str_repeat('تكريم ', 10).'{{ student_name }}',
        'achievement_line' => str_repeat('إنجاز مميز ', 20).'{{ achievement_label }} {{ achievement_name }}',
        'closing' => str_repeat('دعاء ختامي ', 22),
    ];
    $longPreviewCertificate = null;
    Pdf::fake();
    $this->actingAs($user, 'web')
        ->postJson(route('admin.certificate-designs.preview.pdf'), [
            ...certificateContentTestPdfPayload($center, $point),
            'content_template_sections' => $longDraft,
        ])
        ->assertOk();
    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use (&$longPreviewCertificate): bool {
        $longPreviewCertificate = $pdf->viewData['certificate'] ?? null;

        return is_array($longPreviewCertificate);
    });
    $longPreviewHtml = view('certificates.show', ['certificate' => $longPreviewCertificate])->render();
    expect($longPreviewHtml)
        ->toContain('certificate__title--very-long')
        ->toContain('certificate__quote--very-long')
        ->toContain('certificate__intro--very-long')
        ->toContain('certificate__student--extra-long')
        ->toContain('certificate__achievement--very-long')
        ->toContain('certificate__closing--very-long');

    $unsafe = $draft;
    $unsafe['intro'] = '<strong>{{ center_name }}</strong>';
    $unsafe['achievement_line'] = '{{ achievement_label }} {{ unknown_name }}';

    $this->actingAs($user, 'web')
        ->postJson(route('admin.certificate-designs.preview.pdf'), [
            ...certificateContentTestPdfPayload($center, $point),
            'content_template_sections' => $unsafe,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'content_template_sections.intro',
            'content_template_sections.achievement_line',
        ]);

    $tooLong = $draft;
    $tooLong['closing'] = str_repeat('أ', CertificateContentTemplateService::SECTION_MAX_LENGTHS['closing'] + 1);
    $this->actingAs($user, 'web')
        ->postJson(route('admin.certificate-designs.preview.pdf'), [
            ...certificateContentTestPdfPayload($center, $point),
            'content_template_sections' => $tooLong,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content_template_sections.closing');

    $female = CertificateContentTemplate::query()->where('key', 'female')->sole();
    Pdf::fake();
    $this->actingAs($user, 'web')
        ->postJson(route('admin.certificate-designs.preview.pdf'), [
            ...certificateContentTestPdfPayload($center, $point),
            'content_template_id' => $female->id,
            'content_template_sections' => null,
        ])
        ->assertOk();
    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf): bool => data_get($pdf->viewData, 'certificate.content_template.template_key') === 'female'
        && data_get($pdf->viewData, 'certificate.content_template.assignment_source') === 'selected_preview');

    $this->actingAs($user, 'web')
        ->get(route('admin.certificate-designs.preview'))
        ->assertOk()
        ->assertSee('data.content_sections', false)
        ->assertSee('document.createTextNode', false)
        ->assertSee('document.createElement', false)
        ->assertSee('element.replaceChildren', false)
        ->assertDontSee('.innerHTML', false);
});

test('issuance resolves templates only on the server escapes variables and keeps snapshots immutable until redesign', function () {
    $fixture = certificateContentTestIssuanceFixture();
    $service = app(CertificateContentTemplateService::class);
    $template = $service->create([
        'name' => 'قالب إصدار المركز',
        'sections' => certificateContentTestSections('الإصدار الأول'),
    ], $fixture['user']->id);
    $service->upsertAssignment([
        'template_id' => $template->id,
        'scope_type' => CertificateContentTemplateAssignment::SCOPE_CENTER,
        'center_id' => $fixture['center']->id,
        'achievement_type' => Certificate::ACHIEVEMENT_PART,
    ]);
    $female = CertificateContentTemplate::query()->where('key', 'female')->sole();

    $this->actingAs($fixture['user'], 'web')
        ->postJson(route('admin.students.certificates.store', $fixture['student']), [
            'plan_point_id' => $fixture['point']->id,
            'content_template_id' => $female->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('content_template_id');
    expect(Certificate::query()->count())->toBe(0);

    $this->actingAs($fixture['user'], 'web')
        ->postJson(route('admin.students.certificates.store', $fixture['student']), [
            'plan_point_id' => $fixture['point']->id,
        ])
        ->assertCreated();

    $certificate = Certificate::query()->sole();
    $issuedSnapshot = $certificate->wording_snapshot;
    $preserved = Arr::only($certificate->getAttributes(), [
        'certificate_number',
        'student_name',
        'center_name',
        'achievement_type',
        'achievement_name',
        'issued_at',
    ]);

    expect($issuedSnapshot)->toMatchArray([
        'schema_version' => 3,
        'template_id' => $template->id,
        'template_key' => $template->key,
        'assignment_source' => 'center_type',
        'student_gender' => Center::STUDENT_GENDER_MALE,
        'achievement_type' => Certificate::ACHIEVEMENT_PART,
    ])->and($issuedSnapshot['rendered_sections']['student_line'])
        ->toContain('<img src=x onerror=alert(1)>')
        ->and($issuedSnapshot['rendered_sections']['intro'])
        ->toContain('<script>alert(2)</script>')
        ->and($issuedSnapshot['rendered_sections']['achievement_line'])->toContain('الخامس');

    $issuedHtml = $this->actingAs($fixture['user'], 'web')
        ->get(route('admin.students.certificates.show', [$fixture['student'], $certificate]))
        ->assertOk()
        ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
        ->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertDontSee('<script>alert(2)</script>', false)
        ->assertDontSee('﴿ ﴿', false)
        ->getContent();
    expect(substr_count($issuedHtml, '﴿'))->toBe(2)
        ->and(substr_count($issuedHtml, '﴾'))->toBe(2);

    $updatedSections = $template->sections;
    $updatedSections['closing'] = 'الخاتمة الجديدة بعد التعديل';
    $service->update($template, [
        'name' => $template->name,
        'sections' => $updatedSections,
        'is_active' => true,
    ], $fixture['user']->id);

    $beforeRedesign = app(StudentCertificateService::class)
        ->viewPayload($fixture['student'], $certificate->refresh());
    expect($beforeRedesign['content_template']['template_revision'])
        ->toBe($issuedSnapshot['template_revision'])
        ->and($beforeRedesign['closing_text'])->toBe('خاتمة الإصدار الأول');

    $this->actingAs($fixture['user'], 'web')
        ->putJson(route('admin.students.certificates.redesign', [$fixture['student'], $certificate]))
        ->assertOk();

    $certificate->refresh();
    expect($certificate->wording_snapshot['template_revision'])
        ->not->toBe($issuedSnapshot['template_revision'])
        ->and($certificate->wording_snapshot['source_sections']['closing'])
        ->toBe('الخاتمة الجديدة بعد التعديل')
        ->and($certificate->closing_text)->toBe('الخاتمة الجديدة بعد التعديل')
        ->and(Arr::only($certificate->getAttributes(), array_keys($preserved)))->toBe($preserved);

    $corrupt = $certificate->wording_snapshot;
    unset($corrupt['rendered_segments']['closing']);
    $certificate->forceFill([
        'wording_snapshot' => $corrupt,
        'closing_text' => 'خاتمة legacy آمنة',
    ])->save();
    $legacyPayload = app(StudentCertificateService::class)
        ->viewPayload($fixture['student'], $certificate->refresh());

    expect($legacyPayload['content_template'])->toBeNull()
        ->and($legacyPayload['closing_text'])->toBe('خاتمة legacy آمنة');
});

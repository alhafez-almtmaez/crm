<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\CertificateContentTemplate;
use App\Models\Group;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Models\User;
use App\Services\Admin\StudentCertificateService;
use App\Services\System\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * @return array{
 *     certificate: Certificate,
 *     evidence: StudentPointTransaction,
 *     whatsapp: array<string, mixed>,
 *     original_attributes: array<string, mixed>,
 *     original_wording: array<string, mixed>
 * }
 */
function syncCertificateAchievementDatesFixture(): array
{
    app(SystemSettingsService::class)->update(['timezone' => 'Asia/Amman']);
    Carbon::setTestNow(Carbon::parse('2026-09-03 12:00:00', 'UTC'));

    $template = CertificateContentTemplate::query()->where('key', 'general')->sole();
    $sections = $template->sections;
    $sections['closing'] = 'تاريخ الإنجاز الميلادي: {{ gregorian_date }} | تاريخ الإنجاز الهجري: {{ hijri_date }}';
    $template->forceFill(['sections' => $sections])->save();

    $user = User::factory()->create();
    $center = Center::factory()->create([
        'name' => 'مركز مزامنة تواريخ الشهادات',
        'student_gender' => Center::STUDENT_GENDER_MALE,
    ]);
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'name' => 'مجموعة مزامنة تواريخ الشهادات',
    ]);
    $plan = Plan::factory()->quran()->create([
        'name' => 'خطة مزامنة تواريخ الشهادات',
    ]);
    $checkpoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'name' => 'إتمام سورة مريم',
        'requires_certificate' => true,
        'surah_name' => 'مريم',
    ]);
    $laterPoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 20,
        'name' => 'نقطة لاحقة مثبتة للإنجاز',
        'requires_certificate' => false,
    ]);
    $student = Student::factory()->active()->create([
        'full_name' => 'طالب مزامنة التواريخ',
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'current_plan_point_id' => $laterPoint->id,
        'admin_id' => $user->id,
    ]);

    syncCertificateAchievementDatesTransaction(
        $student,
        $checkpoint,
        '2026-08-30 09:00:00',
    );

    Auth::guard('web')->login($user);

    try {
        $certificate = app(StudentCertificateService::class)->issue(
            $student,
            (int) $checkpoint->id,
        );
    } finally {
        Auth::guard('web')->logout();
    }

    // This historical row is inserted after issuance to reproduce a legacy
    // certificate whose saved date is later than its earliest proving event.
    $evidence = syncCertificateAchievementDatesTransaction(
        $student,
        $laterPoint,
        '2026-08-20 21:30:00',
    );
    $whatsappSentAt = Carbon::parse('2026-09-01 08:15:00', 'UTC');
    $whatsapp = [
        'whatsapp_delivery_status' => Certificate::WHATSAPP_DELIVERY_SENT,
        'whatsapp_sent_at' => $whatsappSentAt,
        'whatsapp_sent_by' => $user->id,
        'whatsapp_image_filename' => 'شهادة-سورة-مريم.pdf',
    ];
    $certificate->forceFill($whatsapp)->save();
    $certificate->refresh();

    return [
        'certificate' => $certificate,
        'evidence' => $evidence,
        'whatsapp' => $whatsapp,
        'original_attributes' => $certificate->getAttributes(),
        'original_wording' => $certificate->wording_snapshot,
    ];
}

function syncCertificateAchievementDatesTransaction(
    Student $student,
    PlanPoint $point,
    string $createdAtUtc,
): StudentPointTransaction {
    $transaction = new StudentPointTransaction([
        'student_id' => $student->id,
        'plan_point_id' => $point->id,
        'type' => StudentPointTransaction::TYPE_HOMEWORK_COMPLETED,
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

function syncCertificateAchievementDatesExpectedHijri(Carbon $date): ?string
{
    if (! class_exists(IntlDateFormatter::class)) {
        return null;
    }

    $localDate = $date->copy()->setTimezone('Asia/Amman');
    $formatter = new IntlDateFormatter(
        'ar_JO@calendar=islamic',
        IntlDateFormatter::NONE,
        IntlDateFormatter::NONE,
        'Asia/Amman',
        IntlDateFormatter::TRADITIONAL,
        'd/MMMM/y',
    );
    $formatted = $formatter->format($localDate);

    if (! is_string($formatted) || trim($formatted) === '') {
        return null;
    }

    return strtr(trim($formatted), [
        '0' => '٠',
        '1' => '١',
        '2' => '٢',
        '3' => '٣',
        '4' => '٤',
        '5' => '٥',
        '6' => '٦',
        '7' => '٧',
        '8' => '٨',
        '9' => '٩',
    ]);
}

test('achievement date synchronization dry run reports the correction without changing the certificate', function () {
    $fixture = syncCertificateAchievementDatesFixture();
    $certificate = $fixture['certificate'];
    $syncActivitiesBefore = Activity::query()
        ->where('log_name', 'certificates')
        ->where('subject_type', Certificate::class)
        ->where('subject_id', $certificate->id)
        ->where('properties->action', 'synchronize_achievement_date')
        ->count();

    $this->artisan('certificates:sync-achievement-dates', [
        '--certificate' => $certificate->ulid,
    ])
        ->expectsOutputToContain('Dry run complete; no certificate was changed.')
        ->expectsOutputToContain('needs_update')
        ->expectsOutputToContain((string) $certificate->certificate_number)
        ->expectsOutputToContain('Run the same command with --apply')
        ->assertSuccessful();

    expect($certificate->refresh()->getAttributes())->toBe($fixture['original_attributes'])
        ->and(Activity::query()
            ->where('log_name', 'certificates')
            ->where('subject_type', Certificate::class)
            ->where('subject_id', $certificate->id)
            ->where('properties->action', 'synchronize_achievement_date')
            ->count())->toBe($syncActivitiesBefore);
});

test('achievement date synchronization applies all frozen date fields without changing WhatsApp delivery state', function () {
    $fixture = syncCertificateAchievementDatesFixture();
    $certificate = $fixture['certificate'];
    $evidence = $fixture['evidence'];
    $originalWording = $fixture['original_wording'];
    $expectedGregorian = '٢٠٢٦/٠٨/٢١';
    $expectedHijri = syncCertificateAchievementDatesExpectedHijri($evidence->created_at);
    $expectedRenderedHijri = $expectedHijri ?? '—';
    $preserved = Arr::only($fixture['original_attributes'], [
        'ulid',
        'public_id',
        'certificate_number',
        'issued_at',
        'status',
        'whatsapp_delivery_status',
        'whatsapp_sent_at',
        'whatsapp_sent_by',
        'whatsapp_image_filename',
    ]);

    $this->artisan('certificates:sync-achievement-dates', [
        '--apply' => true,
        '--certificate' => $certificate->certificate_number,
    ])
        ->expectsOutputToContain('Certificate achievement dates synchronized.')
        ->expectsOutputToContain('updated')
        ->expectsOutputToContain((string) $certificate->certificate_number)
        ->assertSuccessful();

    $certificate->refresh();
    $wording = $certificate->wording_snapshot;
    $closingSegments = collect(data_get($wording, 'rendered_segments.closing', []));

    expect($certificate->achieved_at?->toISOString())
        ->toBe($evidence->created_at?->toISOString())
        ->and($certificate->gregorian_date)->toBe($expectedGregorian)
        ->and($certificate->hijri_date)->toBe($expectedHijri)
        ->and(Arr::only($certificate->getAttributes(), array_keys($preserved)))->toBe($preserved)
        ->and(data_get($wording, 'source_sections'))->toBe(data_get($originalWording, 'source_sections'))
        ->and(data_get($wording, 'template_id'))->toBe(data_get($originalWording, 'template_id'))
        ->and(data_get($wording, 'template_key'))->toBe(data_get($originalWording, 'template_key'))
        ->and(data_get($wording, 'template_revision'))->toBe(data_get($originalWording, 'template_revision'))
        ->and(data_get($wording, 'rendered_sections.closing'))
        ->toContain($expectedGregorian)
        ->toContain($expectedRenderedHijri)
        ->not->toBe(data_get($originalWording, 'rendered_sections.closing'))
        ->and($closingSegments->firstWhere('key', 'gregorian_date')['text'] ?? null)
        ->toBe($expectedGregorian)
        ->and($closingSegments->firstWhere('key', 'hijri_date')['text'] ?? null)
        ->toBe($expectedRenderedHijri)
        ->and($certificate->title)->toBe(data_get($wording, 'rendered_sections.title'))
        ->and($certificate->quote_first)->toBe(data_get($wording, 'rendered_sections.quote_first'))
        ->and($certificate->quote_second)->toBe(data_get($wording, 'rendered_sections.quote_second'))
        ->and($certificate->closing_text)->toBe(data_get($wording, 'rendered_sections.closing'));

    $activities = Activity::query()
        ->where('log_name', 'certificates')
        ->where('subject_type', Certificate::class)
        ->where('subject_id', $certificate->id)
        ->where('properties->action', 'synchronize_achievement_date')
        ->get();

    expect($activities)->toHaveCount(1);

    $activity = $activities->sole();
    $properties = $activity->properties->toArray();
    expect($activity->description)->toBe('certificate achievement date synchronized')
        ->and($activity->causer_id)->toBeNull()
        ->and(data_get($properties, 'student_id'))->toBe($certificate->student_id)
        ->and(data_get($properties, 'plan_point_id'))->toBe($certificate->plan_point_id)
        ->and(data_get($properties, 'evidence_transaction_id'))->toBe($evidence->id)
        ->and(data_get($properties, 'whatsapp_delivery_state_preserved'))->toBeTrue()
        ->and(data_get($properties, 'corrected_achieved_at'))
        ->toBe($evidence->created_at?->toISOString());
});

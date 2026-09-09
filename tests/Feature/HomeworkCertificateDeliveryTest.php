<?php

use App\Models\Center;
use App\Models\Certificate;
use App\Models\Device;
use App\Models\Group;
use App\Models\Homework;
use App\Models\HomeworkStudent;
use App\Models\HomeworkStudentPoint;
use App\Models\Plan;
use App\Models\PlanPoint;
use App\Models\Student;
use App\Models\StudentPointTransaction;
use App\Models\User;
use App\Services\Admin\CertificatePdfRenderer;
use App\Services\Auth\PermissionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionSyncService::class)->sync();
    config([
        'services.whatsapp_api.url' => 'https://whatsapp.test',
        'services.whatsapp_api.key' => 'test-key',
        'services.whatsapp_api.message_delay_seconds' => 0,
    ]);
});

function recordHomeworkCertificateCompletion(
    Homework $homework,
    Student $student,
    Plan $plan,
    PlanPoint $point,
    User $actor,
): StudentPointTransaction {
    $homeworkStudent = HomeworkStudent::query()->create([
        'homework_id' => $homework->id,
        'student_id' => $student->id,
        'plan_id' => $plan->id,
        'current_plan_point_id' => $point->id,
        'points_balance_before' => 0,
        'points_adjustment' => 0,
        'points_balance_after' => $point->points,
    ]);
    $homeworkPoint = HomeworkStudentPoint::query()->create([
        'homework_student_id' => $homeworkStudent->id,
        'homework_id' => $homework->id,
        'student_id' => $student->id,
        'plan_point_id' => $point->id,
        'sort_order' => 1,
        'is_done' => true,
        'is_next_homework' => false,
        'awarded_points' => $point->points,
        'awarded_at' => now(),
    ]);

    $student->update(['current_plan_point_id' => $point->id]);

    return StudentPointTransaction::query()->create([
        'student_id' => $student->id,
        'homework_id' => $homework->id,
        'homework_student_point_id' => $homeworkPoint->id,
        'plan_point_id' => $point->id,
        'type' => StudentPointTransaction::TYPE_HOMEWORK_COMPLETED,
        'points' => $point->points,
        'balance_before' => 0,
        'balance_after' => $point->points,
        'created_by' => $actor->id,
    ]);
}

function fakeHomeworkCertificateWhatsApp(): void
{
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/client/isRegisteredUser/')) {
            return Http::response(['success' => true, 'result' => true]);
        }

        if (str_contains($request->url(), '/client/sendMessage/')) {
            return Http::response(['success' => true]);
        }

        return Http::response([], 404);
    });
}

test('a homework action issues due certificates and sends the PDF files through WhatsApp', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $center = Center::factory()->create(['name' => 'مركز الإتقان']);
    $group = Group::factory()->create(['center_id' => $center->id]);
    $plan = Plan::factory()->quran()->create();
    $checkpoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 10,
        'requires_certificate' => true,
        'name' => 'إتمام سورة مريم',
        'surah_name' => 'مريم',
    ]);
    $evidencePoint = PlanPoint::factory()->create([
        'plan_id' => $plan->id,
        'sort_order' => 20,
        'requires_certificate' => false,
        'name' => 'مراجعة سورة مريم',
    ]);
    $student = Student::factory()->active()->create([
        'full_name' => 'أحمد محمد عبدالله',
        'center_id' => $center->id,
        'group_id' => $group->id,
        'plan_type_id' => $plan->id,
        'admin_id' => $admin->id,
        'parent_phone_number' => '0791234567',
        'phone_number' => null,
    ]);
    $homework = Homework::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $admin->id,
        'date' => '2026-09-06',
    ]);
    recordHomeworkCertificateCompletion($homework, $student, $plan, $evidencePoint, $admin);
    Device::factory()->connected()->create(['session_id' => 'homework-certificate-session']);

    $pdf = "%PDF-1.7\nhomework-certificate";
    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock) use ($pdf): void {
        $mock->shouldReceive('render')->once()->andReturn($pdf);
    });
    fakeHomeworkCertificateWhatsApp();

    $url = route('admin.homeworks.certificates.deliver', $homework);
    $this->actingAs($admin, 'web')
        ->postJson($url)
        ->assertOk()
        ->assertJsonPath('meta.candidates', 1)
        ->assertJsonPath('meta.issued', 1)
        ->assertJsonPath('meta.sent', 1)
        ->assertJsonPath('meta.already_sent', 0)
        ->assertJsonPath('meta.has_issues', false);

    $certificate = Certificate::query()->sole();
    expect($certificate->student_id)->toBe($student->id)
        ->and($certificate->plan_point_id)->toBe($checkpoint->id)
        ->and($certificate->whatsapp_delivery_status)->toBe(Certificate::WHATSAPP_DELIVERY_SENT)
        ->and($certificate->whatsapp_sent_at)->not->toBeNull();

    $sendRequest = collect(Http::recorded())
        ->map(static fn (array $record): Request => $record[0])
        ->first(static fn (Request $request): bool => str_contains($request->url(), '/client/sendMessage/'));

    expect($sendRequest)->toBeInstanceOf(Request::class)
        ->and(data_get($sendRequest?->data(), 'contentType'))->toBe('MessageMedia')
        ->and(data_get($sendRequest?->data(), 'content.mimetype'))->toBe('application/pdf')
        ->and(data_get($sendRequest?->data(), 'content.data'))->toBe(base64_encode($pdf))
        ->and(data_get($sendRequest?->data(), 'options.sendMediaAsDocument'))->toBeTrue()
        ->and(data_get($sendRequest?->data(), 'options.caption'))->not->toContain('http');

    $requestCount = count(Http::recorded());

    $this->actingAs($admin, 'web')
        ->postJson($url)
        ->assertOk()
        ->assertJsonPath('meta.candidates', 1)
        ->assertJsonPath('meta.issued', 0)
        ->assertJsonPath('meta.existing', 1)
        ->assertJsonPath('meta.sent', 0)
        ->assertJsonPath('meta.already_sent', 1);

    expect(Certificate::query()->count())->toBe(1)
        ->and(Http::recorded())->toHaveCount($requestCount);
});

test('homework certificate delivery is limited to students in the signed-in supervisor scope', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('مشرف');
    $otherSupervisor = User::factory()->create();
    $center = Center::factory()->create();
    $group = Group::factory()->create(['center_id' => $center->id]);
    $homework = Homework::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $supervisor->id,
    ]);

    $students = collect([$supervisor, $otherSupervisor])->map(function (User $owner, int $index) use (
        $center,
        $group,
        $homework,
        $supervisor,
    ): Student {
        $plan = Plan::factory()->quran()->create();
        $point = PlanPoint::factory()->create([
            'plan_id' => $plan->id,
            'sort_order' => 10,
            'requires_certificate' => true,
            'surah_name' => $index === 0 ? 'يس' : 'الملك',
        ]);
        $student = Student::factory()->active()->create([
            'center_id' => $center->id,
            'group_id' => $group->id,
            'plan_type_id' => $plan->id,
            'admin_id' => $owner->id,
            'parent_phone_number' => '079000000'.($index + 1),
            'phone_number' => null,
        ]);
        recordHomeworkCertificateCompletion($homework, $student, $plan, $point, $supervisor);

        return $student;
    });
    Device::factory()->connected()->create(['session_id' => 'scoped-homework-certificate-session']);

    $this->mock(CertificatePdfRenderer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')->once()->andReturn("%PDF-1.7\nscoped-certificate");
    });
    fakeHomeworkCertificateWhatsApp();

    $this->actingAs($supervisor, 'web')
        ->postJson(route('admin.homeworks.certificates.deliver', $homework))
        ->assertOk()
        ->assertJsonPath('meta.candidates', 1)
        ->assertJsonPath('meta.issued', 1)
        ->assertJsonPath('meta.sent', 1);

    expect(Certificate::query()->pluck('student_id')->all())
        ->toBe([$students[0]->id])
        ->not->toContain($students[1]->id);
});

test('homework certificate delivery requires both issue and WhatsApp permissions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['homeworks.view', 'students.update']);
    $homework = Homework::factory()->create(['admin_id' => $user->id]);

    $this->actingAs($user, 'web')
        ->postJson(route('admin.homeworks.certificates.deliver', $homework))
        ->assertForbidden();
});

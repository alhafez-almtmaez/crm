<?php

use App\Exceptions\WhatsAppMessageSendException;
use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationAbsenceAlertRun;
use App\Models\EvaluationStudent;
use App\Models\Group;
use App\Models\MessageTemplate;
use App\Models\Student;
use App\Models\User;
use App\Services\Admin\WhatsAppMessagingService;
use App\Services\Auth\PermissionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function attendanceQueueFixture(bool $supervisor = false): array
{
    app(PermissionSyncService::class)->sync();
    $actor = User::factory()->create();
    if ($supervisor) {
        $actor->assignRole('مشرف');
        $actor->givePermissionTo(['evaluations.view', 'evaluations.update']);
    } else {
        $actor->assignRole('admin');
    }
    $center = Center::factory()->create();
    $group = Group::factory()->create([
        'center_id' => $center->id,
        'group_serialized' => '120363999999999999@g.us',
    ]);
    $student = Student::factory()->active()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $actor->id,
        'parent_phone_number' => '0791234567',
        'phone_number' => null,
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'group_id' => $group->id,
        'admin_id' => $actor->id,
        'date' => '2026-10-12',
        'is_send_absence_alerts' => false,
    ]);
    $item = EvaluationStudent::factory()->absence()->create([
        'evaluation_id' => $evaluation->id,
        'student_id' => $student->id,
        'user_id' => $student->id,
    ]);
    $template = MessageTemplate::factory()->create(['content' => 'Alert for {{full_name}}.']);
    $rule = AbsenceRule::factory()->sendMessageAction()->create([
        'center_id' => $center->id,
        'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
        'occurrence_number' => 1,
        'message_template_id' => $template->id,
        'send_to_center_group' => true,
        'deduction_points_count' => 0,
    ]);

    return compact('actor', 'center', 'group', 'student', 'evaluation', 'item', 'rule');
}

function runNextAttendanceQueueJob(): void
{
    Artisan::call('queue:work', [
        'connection' => 'attendance_alerts',
        '--queue' => 'attendance-alerts',
        '--once' => true,
        '--sleep' => 0,
        '--tries' => 1,
    ]);
}

test('alert requests return immediately and repeated clicks enqueue just one job', function () {
    $fixture = attendanceQueueFixture();
    Http::fake();
    $url = route('admin.evaluations.absence-alerts', $fixture['evaluation']);

    $response = $this->actingAs($fixture['actor'], 'web')->postJson($url)
        ->assertStatus(202)
        ->assertJsonPath('run.status', EvaluationAbsenceAlertRun::STATUS_QUEUED);
    $runId = $response->json('run.id');

    $this->postJson($url)->assertStatus(202)->assertJsonPath('run.id', $runId);
    expect(DB::table('jobs')->where('queue', 'attendance-alerts')->count())->toBe(1)
        ->and(EvaluationAbsenceAlertRun::query()->count())->toBe(1)
        ->and(AbsenceRuleExecutionLog::query()->count())->toBe(0);
    Http::assertNothingSent();

    $this->getJson($response->json('run.status_url'))->assertOk()->assertJsonPath('run.status', 'queued');
    $this->getJson(route('admin.evaluations.records'))
        ->assertOk()->assertJsonPath('data.0.absence_alert_run.id', $runId);
});

test('the queue worker preserves supervisor scope and clears its authentication context', function () {
    $fixture = attendanceQueueFixture(supervisor: true);
    $otherStudent = Student::factory()->active()->create([
        'center_id' => $fixture['center']->id,
        'group_id' => $fixture['group']->id,
        'admin_id' => User::factory()->create()->id,
    ]);
    EvaluationStudent::factory()->absence()->create([
        'evaluation_id' => $fixture['evaluation']->id,
        'student_id' => $otherStudent->id,
        'user_id' => $otherStudent->id,
    ]);
    $messaging = new class extends WhatsAppMessagingService
    {
        public array $calls = [];

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls[] = compact('phones', 'content', 'groupSerialized');
        }
    };
    app()->instance(WhatsAppMessagingService::class, $messaging);

    $response = $this->actingAs($fixture['actor'], 'web')
        ->postJson(route('admin.evaluations.absence-alerts', $fixture['evaluation']))->assertStatus(202);
    Auth::guard('web')->forgetUser();
    runNextAttendanceQueueJob();

    $run = EvaluationAbsenceAlertRun::query()->findOrFail($response->json('run.id'));
    expect($run->status)->toBe('completed')
        ->and($run->result['processed'])->toBe(1)
        ->and($run->result['errors'])->toBe([])
        ->and($messaging->calls)->toHaveCount(1)
        ->and($fixture['evaluation']->refresh()->is_send_absence_alerts)->toBeFalse()
        ->and(Auth::user())->toBeNull()
        ->and(AbsenceRuleExecutionLog::query()->sole()->student_id)->toBe($fixture['student']->id)
        ->and(AbsenceRuleExecutionLog::query()->sole()->executed_by)->toBe($fixture['actor']->id)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('the worker stops safely if the requester loses permission or is deleted', function (bool $deleteActor) {
    $fixture = attendanceQueueFixture();
    Http::fake();
    $response = $this->actingAs($fixture['actor'], 'web')
        ->postJson(route('admin.evaluations.absence-alerts', $fixture['evaluation']))->assertStatus(202);
    if ($deleteActor) {
        $fixture['actor']->delete();
    } else {
        $fixture['actor']->removeRole('admin');
    }
    Auth::guard('web')->forgetUser();
    runNextAttendanceQueueJob();

    $run = EvaluationAbsenceAlertRun::query()->findOrFail($response->json('run.id'));
    expect($run->status)->toBe('failed')
        ->and($run->error)->not->toBeNull()
        ->and(AbsenceRuleExecutionLog::query()->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(Auth::user())->toBeNull();
    Http::assertNothingSent();
})->with(['permission revoked' => [false], 'requester deleted' => [true]]);

test('status is private to its requester and must match the evaluation', function () {
    $fixture = attendanceQueueFixture();
    $response = $this->actingAs($fixture['actor'], 'web')
        ->postJson(route('admin.evaluations.absence-alerts', $fixture['evaluation']))->assertStatus(202);
    $otherEvaluation = Evaluation::factory()->create();
    $this->getJson(route('admin.evaluations.absence-alerts.status', [
        $otherEvaluation,
        $response->json('run.id'),
    ]))->assertNotFound();
    $otherActor = User::factory()->create();
    $otherActor->assignRole('admin');
    $this->actingAs($otherActor, 'web')->getJson($response->json('run.status_url'))->assertNotFound();
    $this->getJson(route('admin.evaluations.records'))->assertOk()->assertJsonPath('data.0.absence_alert_run', null);

    $unprivileged = User::factory()->create();
    $this->actingAs($unprivileged, 'web')->getJson($response->json('run.status_url'))->assertForbidden();
    $this->postJson(route('admin.evaluations.absence-alerts', $fixture['evaluation']))->assertForbidden();
});

test('an interrupted run expires safely and does not enqueue an automatic resend', function () {
    $fixture = attendanceQueueFixture();
    $run = EvaluationAbsenceAlertRun::query()->create([
        'evaluation_id' => $fixture['evaluation']->id,
        'requested_by' => $fixture['actor']->id,
        'status' => 'processing',
        'started_at' => now()->subSeconds(EvaluationAbsenceAlertRun::TIMEOUT_SECONDS + 61),
    ]);
    $this->actingAs($fixture['actor'], 'web')->getJson($run->payload()['status_url'])
        ->assertOk()->assertJsonPath('run.status', 'failed');
    expect(DB::table('jobs')->count())->toBe(0)
        ->and($run->refresh()->finished_at)->not->toBeNull();
});

test('partial delivery remains protected when another background run is requested', function () {
    $fixture = attendanceQueueFixture();
    $messaging = new class extends WhatsAppMessagingService
    {
        public int $calls = 0;

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls++;
            throw new WhatsAppMessageSendException('Group delivery failed.', [$groupSerialized], deliveryAttempted: true);
        }
    };
    app()->instance(WhatsAppMessagingService::class, $messaging);
    $url = route('admin.evaluations.absence-alerts', $fixture['evaluation']);
    $this->actingAs($fixture['actor'], 'web')->postJson($url)->assertStatus(202);
    runNextAttendanceQueueJob();
    expect(AbsenceRuleExecutionLog::query()->sole()->meta['partial_delivery'])->toBeTrue();

    $this->postJson($url)->assertStatus(202);
    runNextAttendanceQueueJob();
    expect($messaging->calls)->toBe(1)
        ->and(EvaluationAbsenceAlertRun::query()->latest('id')->first()->result['errors'])
        ->toBe([__('evaluations.absence_alert_requires_review', ['item' => $fixture['item']->id])]);
});

test('completed evaluations do not queue duplicate messages', function () {
    $fixture = attendanceQueueFixture();
    $fixture['evaluation']->update(['is_send_absence_alerts' => true]);
    $this->actingAs($fixture['actor'], 'web')
        ->postJson(route('admin.evaluations.absence-alerts', $fixture['evaluation']))
        ->assertOk()->assertJsonPath('meta.alerts_marked_as_sent', true);
    expect(DB::table('jobs')->count())->toBe(0);
});

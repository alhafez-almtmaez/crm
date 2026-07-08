<?php

use App\Models\AbsenceRule;
use App\Models\AbsenceRuleExecutionLog;
use App\Models\Center;
use App\Models\Evaluation;
use App\Models\EvaluationStudent;
use App\Models\MessageTemplate;
use App\Models\Student;
use App\Models\StudentFreeze;
use App\Models\User;
use App\Services\Admin\AbsenceRules\AbsenceRuleEngine;
use App\Services\Admin\WhatsAppMessagingService;
use App\Services\Auth\PermissionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create an alert only absence rule', function () {
    app(PermissionSyncService::class)->sync();

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $center = Center::factory()->create();
    $template = MessageTemplate::factory()->create();

    $this->actingAs($admin, 'web')
        ->post('/admin/absence-rules', [
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 1,
            'action' => AbsenceRule::ACTION_SEND_MESSAGE,
            'message_template_id' => $template->id,
            'send_to_center_group' => false,
            'deduction_points_count' => 25,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin/absence-rules');

    $this->assertDatabaseHas('absence_rules', [
        'center_id' => $center->id,
        'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
        'occurrence_number' => 1,
        'action' => AbsenceRule::ACTION_SEND_MESSAGE,
        'message_template_id' => $template->id,
        'deduction_points_count' => 25,
        'freeze_reason' => null,
        'freeze_working_days_count' => 4,
        'is_active' => true,
    ]);
});

test('alert only absence rule sends the message and deducts configured points without freezing', function () {
    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, array{phones: array<int, string>, content: string, group_serialized: string|null}> */
        public array $calls = [];

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls[] = [
                'phones' => $phones,
                'content' => $content,
                'group_serialized' => $groupSerialized,
            ];
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create(['group_serialized' => null]);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'deducted_points_count' => 7,
        ]);
    $template = MessageTemplate::factory()->create([
        'content' => 'Alert for {{full_name}} on {{date}}.',
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    $rule = AbsenceRule::factory()
        ->sendMessageAction()
        ->create([
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 1,
            'message_template_id' => $template->id,
            'deduction_points_count' => 25,
            'is_active' => true,
        ]);

    $result = app(AbsenceRuleEngine::class)->process($evaluation);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($messaging->calls)->toHaveCount(1);

    $log = AbsenceRuleExecutionLog::query()->firstOrFail();

    expect($log->action)->toBe(AbsenceRule::ACTION_SEND_MESSAGE)
        ->and($log->absence_rule_id)->toBe($rule->id)
        ->and($log->was_message_sent)->toBeTrue()
        ->and($log->student_was_frozen)->toBeFalse()
        ->and($log->student_was_dismissed)->toBeFalse()
        ->and($log->deduction_points_count)->toBe(25)
        ->and(StudentFreeze::query()->count())->toBe(0)
        ->and($student->refresh()->is_active)->toBe(Student::STATUS_ACTIVE)
        ->and($student->deducted_points_count)->toBe(32);
});

test('dismiss absence rule sends the message deducts points and marks the student inactive', function () {
    app()->detectEnvironment(fn (): string => 'testing');

    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, array{phones: array<int, string>, content: string, group_serialized: string|null}> */
        public array $calls = [];

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls[] = [
                'phones' => $phones,
                'content' => $content,
                'group_serialized' => $groupSerialized,
            ];
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create(['group_serialized' => null]);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'deducted_points_count' => 4,
        ]);
    $template = MessageTemplate::factory()->create([
        'content' => 'قرار فصل للطالب: *{ student.full_name }* - الرصيد بعد الخصم: { student.deducted_points_after }',
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    $rule = AbsenceRule::factory()
        ->dismissAction()
        ->create([
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 1,
            'message_template_id' => $template->id,
            'deduction_points_count' => 30,
            'is_active' => true,
        ]);

    $result = app(AbsenceRuleEngine::class)->process($evaluation);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($result['alerts_marked_as_sent'])->toBeTrue()
        ->and($messaging->calls)->toHaveCount(1)
        ->and($messaging->calls[0]['content'])->toContain("*{$student->full_name}*")
        ->and($messaging->calls[0]['content'])->toContain('34');

    $log = AbsenceRuleExecutionLog::query()->firstOrFail();

    expect($log->action)->toBe(AbsenceRule::ACTION_DISMISS_STUDENT)
        ->and($log->absence_rule_id)->toBe($rule->id)
        ->and($log->was_message_sent)->toBeTrue()
        ->and($log->student_was_frozen)->toBeFalse()
        ->and($log->student_was_dismissed)->toBeTrue()
        ->and($log->deduction_points_count)->toBe(30)
        ->and($log->message_content)->toContain("*{$student->full_name}*")
        ->and($student->refresh()->is_active)->toBe(Student::STATUS_INACTIVE)
        ->and($student->deducted_points_count)->toBe(34)
        ->and($evaluation->refresh()->is_send_absence_alerts)->toBeTrue();
});

test('local environment creates absence alert previews without sending or mutating students', function () {
    app()->detectEnvironment(fn (): string => 'local');

    $messaging = new class extends WhatsAppMessagingService
    {
        public int $calls = 0;

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls++;
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create(['group_serialized' => '120363000000000000@g.us']);
    $student = Student::factory()
        ->active()
        ->create([
            'center_id' => $center->id,
            'deducted_points_count' => 7,
        ]);
    $template = MessageTemplate::factory()->create([
        'content' => 'تنبيه للطالب: *{ student.full_name }*',
    ]);
    $evaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $evaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    AbsenceRule::factory()
        ->sendMessageAction()
        ->create([
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 1,
            'message_template_id' => $template->id,
            'send_to_center_group' => true,
            'deduction_points_count' => 25,
            'is_active' => true,
        ]);

    $result = app(AbsenceRuleEngine::class)->process($evaluation);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($result['local_preview'])->toBeTrue()
        ->and($result['alerts_marked_as_sent'])->toBeFalse()
        ->and($result['preview_messages'])->toHaveCount(1)
        ->and($messaging->calls)->toBe(0);

    $log = AbsenceRuleExecutionLog::query()->firstOrFail();

    expect($log->was_message_sent)->toBeFalse()
        ->and($log->meta['local_preview'])->toBeTrue()
        ->and($log->meta['whatsapp_skipped'])->toBeTrue()
        ->and($log->message_content)->toContain("*{$student->full_name}*")
        ->and($log->message_content)->not->toContain('{ student.full_name }')
        ->and($evaluation->refresh()->is_send_absence_alerts)->toBeFalse()
        ->and($student->refresh()->deducted_points_count)->toBe(7);
});

test('absence rule occurrence number is counted within the evaluation month', function () {
    $messaging = new class extends WhatsAppMessagingService
    {
        /** @var array<int, array{phones: array<int, string>, content: string, group_serialized: string|null}> */
        public array $calls = [];

        public function sendMediaCaption(array $phones, string $content, ?string $groupSerialized = null): void
        {
            $this->calls[] = [
                'phones' => $phones,
                'content' => $content,
                'group_serialized' => $groupSerialized,
            ];
        }
    };

    app()->instance(WhatsAppMessagingService::class, $messaging);

    $center = Center::factory()->create(['group_serialized' => null]);
    $student = Student::factory()
        ->active()
        ->create(['center_id' => $center->id]);
    $template = MessageTemplate::factory()->create([
        'content' => 'تنبيه رقم {{ occurrence_number }} لهذا الشهر',
    ]);

    foreach (['2026-06-30', '2026-07-01', '2026-07-05'] as $date) {
        $priorEvaluation = Evaluation::factory()->create([
            'center_id' => $center->id,
            'date' => $date,
            'is_send_absence_alerts' => true,
        ]);

        EvaluationStudent::factory()
            ->absence()
            ->create([
                'evaluation_id' => $priorEvaluation->id,
                'student_id' => $student->id,
                'user_id' => $student->id,
            ]);
    }

    $currentEvaluation = Evaluation::factory()->create([
        'center_id' => $center->id,
        'date' => '2026-07-08',
        'is_send_absence_alerts' => false,
    ]);

    EvaluationStudent::factory()
        ->absence()
        ->create([
            'evaluation_id' => $currentEvaluation->id,
            'student_id' => $student->id,
            'user_id' => $student->id,
        ]);

    $rule = AbsenceRule::factory()
        ->sendMessageAction()
        ->create([
            'center_id' => $center->id,
            'attendance_type' => AbsenceRule::ATTENDANCE_TYPE_ABSENCE,
            'occurrence_number' => 3,
            'message_template_id' => $template->id,
            'is_active' => true,
        ]);

    $result = app(AbsenceRuleEngine::class)->process($currentEvaluation);

    expect($result['processed'])->toBe(1)
        ->and($result['errors'])->toBe([])
        ->and($messaging->calls)->toHaveCount(1)
        ->and($messaging->calls[0]['content'])->toContain('تنبيه رقم 3 لهذا الشهر');

    $log = AbsenceRuleExecutionLog::query()->firstOrFail();

    expect($log->absence_rule_id)->toBe($rule->id)
        ->and($log->occurrence_number)->toBe(3)
        ->and($log->message_content)->toContain('تنبيه رقم 3 لهذا الشهر');
});
